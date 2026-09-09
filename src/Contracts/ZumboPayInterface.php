<?php

namespace ZumboPay\Contracts;

use ZumboPay\DTO\ChargeRequest;
use ZumboPay\DTO\ChargeResponse;
use ZumboPay\DTO\CheckoutRequest;
use ZumboPay\DTO\CheckoutResponse;
use ZumboPay\DTO\TransactionStatus;

interface ZumboPayInterface
{
    public function charge(ChargeRequest $request): ChargeResponse;

    public function checkout(CheckoutRequest $request): CheckoutResponse;

    public function status(string $reference): TransactionStatus;

    public function validateWebhook(string $rawPayload, ?string $signature): bool;

    public function listWallets(): array;
}
