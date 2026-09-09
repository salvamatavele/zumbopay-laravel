<?php

namespace ZumboPay\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use ZumboPay\Contracts\ZumboPayInterface;
use ZumboPay\Laravel\Events\ZumboPayPaymentFailed;
use ZumboPay\Laravel\Events\ZumboPayPaymentSucceeded;

class WebhookController extends Controller
{
    public function __construct(protected ZumboPayInterface $zumboPay) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Signature') ?: $request->header('Signature');

        if (! $this->zumboPay->validateWebhook($rawPayload, $signature)) {
            Log::warning('ZumboPay Webhook: Assinatura HMAC inválida', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? $payload['type'] ?? 'payment.succeeded';
        $data = $payload['data'] ?? $payload;

        $reference = $data['reference'] ?? $data['id'] ?? $data['source_id'] ?? '';
        $status = strtolower((string) ($data['status'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);
        $channel = $data['channel'] ?? $data['method'] ?? null;

        $isSuccess = ($event === 'payment.succeeded' || $event === 'charge.succeeded' || in_array($status, ['success', 'completed', 'succeeded'], true));

        if ($isSuccess) {
            event(new ZumboPayPaymentSucceeded(
                reference: (string) $reference,
                amount: $amount,
                channel: $channel,
                payload: $payload,
            ));
        } else {
            event(new ZumboPayPaymentFailed(
                reference: (string) $reference,
                reason: $data['message'] ?? $status,
                payload: $payload,
            ));
        }

        return response()->json(['received' => true, 'status' => 'processed'], 200);
    }
}
