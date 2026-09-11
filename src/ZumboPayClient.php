<?php

namespace ZumboPay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZumboPay\Contracts\ZumboPayInterface;
use ZumboPay\DTO\ChargeRequest;
use ZumboPay\DTO\ChargeResponse;
use ZumboPay\DTO\CheckoutRequest;
use ZumboPay\DTO\CheckoutResponse;
use ZumboPay\DTO\TransactionStatus;
use ZumboPay\Enums\Channel;
use ZumboPay\Enums\Status;
use ZumboPay\Support\PhoneNormalizer;
use ZumboPay\Support\WebhookSignature;

class ZumboPayClient implements ZumboPayInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $merchantId,
        protected string $baseUrl = 'https://zumbopay.com/api/public/v1',
        protected ?string $webhookSecret = null,
        protected array $wallets = [],
        protected bool $enabled = true,
        protected string $disabledMessage = 'Os pagamentos via ZumboPay encontram-se temporariamente suspensos para manutenção.',
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            apiKey: (string) ($config['api_key'] ?? ''),
            merchantId: (string) ($config['merchant_id'] ?? ''),
            baseUrl: (string) ($config['base_url'] ?? 'https://zumbopay.com/api/public/v1'),
            webhookSecret: $config['webhook_secret'] ?? null,
            wallets: (array) ($config['wallets'] ?? []),
            enabled: (bool) ($config['enabled'] ?? true),
            disabledMessage: (string) ($config['disabled_message'] ?? 'Os pagamentos via ZumboPay encontram-se temporariamente suspensos para manutenção.'),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function enable(): self
    {
        return $this->setEnabled(true);
    }

    public function disable(): self
    {
        return $this->setEnabled(false);
    }

    public function getDisabledMessage(): string
    {
        return $this->disabledMessage;
    }

    public function setDisabledMessage(string $message): self
    {
        $this->disabledMessage = $message;

        return $this;
    }

    public function isValidUuid(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($value));
    }

    /**
     * Consulta todas as carteiras e efetua cache por 10 minutos
     */
    public function listWallets(): array
    {
        if (empty($this->apiKey) || empty($this->merchantId)) {
            return [];
        }

        $cacheKey = 'zumbopay_wallets_'.md5($this->apiKey);

        return Cache::remember($cacheKey, 600, function (): array {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'X-Merchant-Id' => $this->merchantId,
                ])
                    ->timeout(10)
                    ->get($this->baseUrl.'/wallets');

                if ($response->successful()) {
                    return $response->json()['data'] ?? [];
                }

                return [];
            } catch (\Throwable $e) {
                Log::warning('ZumboPay listWallets failed', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * Resolve o UUID válido para o canal especificado
     */
    public function resolveWalletIdForChannel(string|Channel $channel): ?string
    {
        $channelKey = $channel instanceof Channel ? $channel->value : strtolower($channel);

        $configured = $this->wallets[$channelKey] ?? null;
        if ($this->isValidUuid($configured)) {
            return trim((string) $configured);
        }

        $wallets = $this->listWallets();

        // 1. Tenta correspondência direta pelo método
        foreach ($wallets as $wallet) {
            if (isset($wallet['method']) && strtolower((string) $wallet['method']) === $channelKey) {
                if (! empty($wallet['id']) && $this->isValidUuid($wallet['id'])) {
                    return $wallet['id'];
                }
            }
        }

        // 2. Fallback para qualquer carteira com UUID válido
        foreach ($wallets as $wallet) {
            if (! empty($wallet['id']) && $this->isValidUuid($wallet['id'])) {
                return $wallet['id'];
            }
        }

        return $configured ?: null;
    }

    /**
     * Resolve a carteira correspondente ao número de telefone (M-Pesa, e-Mola, mKesh)
     */
    public function resolveWalletIdForPhone(string $msisdn): ?string
    {
        $channel = PhoneNormalizer::detectChannel($msisdn);

        if ($channel !== null) {
            $wallet = $this->resolveWalletIdForChannel($channel);
            if ($wallet) {
                return $wallet;
            }
        }

        return $this->resolveWalletIdForChannel('mpesa')
            ?: $this->resolveWalletIdForChannel('emola')
            ?: $this->resolveWalletIdForChannel('mkesh')
            ?: $this->resolveWalletIdForChannel('card');
    }

    /**
     * Disparo de STK Push a partir do DTO ChargeRequest
     */
    public function charge(ChargeRequest $request): ChargeResponse
    {
        if (! $this->isEnabled()) {
            Log::info('ZumboPay payment charge skipped: gateway is disabled (silenciador ativo).', [
                'reference' => $request->reference,
                'phone' => $request->phone,
            ]);

            return new ChargeResponse(
                success: false,
                status: Status::Error,
                reference: null,
                message: $this->disabledMessage,
                code: 'GATEWAY_DISABLED',
                raw: ['disabled' => true],
            );
        }

        $msisdn = PhoneNormalizer::normalize($request->phone);
        $walletId = $request->walletId ?: $this->resolveWalletIdForPhone($msisdn);

        if (empty($walletId) || ! $this->isValidUuid($walletId)) {
            return new ChargeResponse(
                success: false,
                status: Status::Error,
                reference: null,
                message: 'Nenhuma carteira válida (UUID) foi encontrada na sua conta ZumboPay.',
            );
        }

        $payload = [
            'wallet_id' => $walletId,
            'amount' => round($request->amount, 2),
            'msisdn' => $msisdn,
            'customer_name' => $request->customerName ?? 'Cliente',
            'source_id' => $request->reference,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'X-Merchant-Id' => $this->merchantId,
                'Idempotency-Key' => $request->reference,
            ])
                ->timeout(45)
                ->post($this->baseUrl.'/charges', $payload);

            $data = $response->json() ?? [];

            if ($response->successful() || $response->status() === 202) {
                $ref = $data['data']['reference'] ?? $request->reference;
                $statusStr = strtolower((string) ($data['data']['status'] ?? 'pending'));
                $code = $data['data']['code'] ?? null;

                $isSuccess = in_array($statusStr, ['success', 'succeeded', 'completed'], true) || $code === 'INS-0';
                $finalStatus = $isSuccess ? Status::Success : Status::Pending;

                return new ChargeResponse(
                    success: true,
                    status: $finalStatus,
                    reference: $ref,
                    message: $isSuccess
                        ? 'Pagamento efetuado com sucesso!'
                        : 'Pedido de pagamento enviado para o telemóvel. Por favor confirme com o PIN.',
                    code: $code,
                    raw: $data,
                );
            }

            Log::warning('ZumboPay STK Push Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ref' => $request->reference,
            ]);

            return new ChargeResponse(
                success: false,
                status: Status::Declined,
                reference: null,
                message: $data['error']['message'] ?? $data['message'] ?? 'O pagamento foi recusado ou expirou no telemóvel.',
                raw: $data,
            );
        } catch (\Throwable $e) {
            // Se ocorreu timeout, o prompt USSD está no telemóvel do utilizador!
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                return new ChargeResponse(
                    success: true,
                    status: Status::Pending,
                    reference: $request->reference,
                    message: 'Pedido de pagamento enviado para o telemóvel. A aguardar confirmação do PIN...',
                    raw: ['timeout' => true],
                );
            }

            Log::error('ZumboPay STK Push Exception', ['error' => $e->getMessage(), 'ref' => $request->reference]);

            return new ChargeResponse(
                success: false,
                status: Status::Error,
                reference: null,
                message: 'Falha na comunicação com o gateway ZumboPay.',
                raw: ['error' => $e->getMessage()],
            );
        }
    }

    /**
     * Atalho fluente para STK Push
     */
    public function stkPush(string $phone, float $amount, string $reference, ?string $customerName = null, ?string $walletId = null): ChargeResponse
    {
        return $this->charge(new ChargeRequest(
            phone: $phone,
            amount: $amount,
            reference: $reference,
            customerName: $customerName,
            walletId: $walletId,
        ));
    }

    /**
     * Criação de Checkout Hospedado (Cartão e Multicanal)
     */
    public function checkout(CheckoutRequest $request): CheckoutResponse
    {
        if (! $this->isEnabled()) {
            Log::info('ZumboPay payment checkout skipped: gateway is disabled (silenciador ativo).', [
                'reference' => $request->reference,
                'amount' => $request->amount,
            ]);

            return new CheckoutResponse(
                success: false,
                checkoutUrl: null,
                reference: null,
                message: $this->disabledMessage,
                raw: ['disabled' => true],
            );
        }

        $walletId = $request->walletId ?: $this->resolveWalletIdForChannel('card') ?: $this->resolveWalletIdForChannel('mpesa');

        $payload = [
            'title' => $request->title ?? 'Pagamento #'.$request->reference,
            'amount' => round($request->amount, 2),
            'currency' => $request->currency,
            'channels' => $request->channels,
            'wallet_id' => $walletId,
            'reference' => $request->reference,
            'return_url' => $request->returnUrl,
            'redirect_url' => $request->returnUrl,
            'callback_url' => $request->returnUrl,
            'cancel_url' => $request->cancelUrl,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'X-Merchant-Id' => $this->merchantId,
            ])
                ->timeout(20)
                ->post($this->baseUrl.'/payments', $payload);

            $data = $response->json() ?? [];

            if ($response->successful() && ! empty($data['data']['checkout_url'])) {
                $ref = $data['data']['reference'] ?? $data['data']['id'] ?? $request->reference;

                return new CheckoutResponse(
                    success: true,
                    checkoutUrl: $data['data']['checkout_url'],
                    reference: $ref,
                    raw: $data,
                );
            }

            return new CheckoutResponse(
                success: false,
                checkoutUrl: null,
                reference: null,
                message: $data['error']['message'] ?? $data['message'] ?? 'Não foi possível gerar a página de checkout seguro.',
                raw: $data,
            );
        } catch (\Throwable $e) {
            return new CheckoutResponse(
                success: false,
                checkoutUrl: null,
                reference: null,
                message: 'Falha na comunicação com o gateway ZumboPay.',
                raw: ['error' => $e->getMessage()],
            );
        }
    }

    /**
     * Atalho fluente para Checkout Hospedado
     */
    public function createCheckout(float $amount, string $reference, ?string $returnUrl = null, ?string $cancelUrl = null, array $channels = ['card', 'mpesa', 'emola', 'mkesh']): CheckoutResponse
    {
        return $this->checkout(new CheckoutRequest(
            amount: $amount,
            reference: $reference,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            channels: $channels,
        ));
    }

    /**
     * Consulta o estado de uma transação
     */
    public function status(string $reference): TransactionStatus
    {
        try {
            $headers = [
                'Authorization' => 'Bearer '.$this->apiKey,
                'X-Merchant-Id' => $this->merchantId,
            ];

            $res = Http::withHeaders($headers)->timeout(15)->get($this->baseUrl.'/payments/'.$reference);
            $data = $res->successful() ? $res->json() : null;

            if (! $data) {
                $resCharges = Http::withHeaders($headers)->timeout(15)->get($this->baseUrl.'/charges/'.$reference);
                $data = $resCharges->json() ?? [];
            }

            $statusStr = strtolower((string) ($data['data']['status'] ?? $data['status'] ?? 'pending'));
            $transactions = $data['data']['transactions'] ?? [];
            $hasSuccess = in_array($statusStr, ['success', 'completed', 'succeeded'], true);
            $channel = $data['data']['channel'] ?? null;
            $amount = isset($data['data']['amount']) ? (float) $data['data']['amount'] : null;

            foreach ($transactions as $tx) {
                $txStatus = strtolower((string) ($tx['status'] ?? ''));
                if (in_array($txStatus, ['success', 'completed', 'succeeded'], true)) {
                    $hasSuccess = true;
                    $channel ??= $tx['method'] ?? null;
                    break;
                }
            }

            return new TransactionStatus(
                reference: $reference,
                status: $hasSuccess ? Status::Success : Status::Pending,
                isPaid: $hasSuccess,
                amount: $amount,
                channel: $channel,
                paidAt: $hasSuccess ? ($data['data']['paid_at'] ?? now()->toIso8601String()) : null,
                raw: $data,
            );
        } catch (\Throwable $e) {
            return new TransactionStatus(
                reference: $reference,
                status: Status::Error,
                isPaid: false,
                raw: ['error' => $e->getMessage()],
            );
        }
    }

    /**
     * Valida a assinatura de um webhook recebido
     */
    public function validateWebhook(string $rawPayload, ?string $signature): bool
    {
        return WebhookSignature::validate($rawPayload, $signature, $this->webhookSecret);
    }
}
