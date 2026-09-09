<?php

namespace ZumboPay\DTO;

use ZumboPay\Enums\Channel;

class ChargeRequest
{
    public function __construct(
        public string $phone,
        public float $amount,
        public string $reference,
        public ?string $customerName = null,
        public ?Channel $channel = null,
        public ?string $walletId = null,
    ) {}
}
