<?php

namespace ZumboPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use ZumboPay\Contracts\ZumboPayInterface;
use ZumboPay\DTO\ChargeRequest;
use ZumboPay\DTO\ChargeResponse;
use ZumboPay\DTO\CheckoutRequest;
use ZumboPay\DTO\CheckoutResponse;
use ZumboPay\DTO\TransactionStatus;
use ZumboPay\ZumboPayClient;

/**
 * @method static ChargeResponse charge(ChargeRequest $request)
 * @method static ChargeResponse stkPush(string $phone, float $amount, string $reference, ?string $customerName = null, ?string $walletId = null)
 * @method static CheckoutResponse checkout(CheckoutRequest $request)
 * @method static CheckoutResponse createCheckout(float $amount, string $reference, ?string $returnUrl = null, ?string $cancelUrl = null, array $channels = ['card', 'mpesa', 'emola', 'mkesh'])
 * @method static TransactionStatus status(string $reference)
 * @method static bool validateWebhook(string $rawPayload, ?string $signature)
 * @method static array listWallets()
 * @method static ?string resolveWalletIdForChannel(string $channel)
 * @method static ?string resolveWalletIdForPhone(string $phone)
 *
 * @see ZumboPayClient
 */
class ZumboPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ZumboPayInterface::class;
    }
}
