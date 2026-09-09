<?php

namespace ZumboPay\DTO;

class CheckoutRequest
{
    /**
     * @param  array<string>  $channels
     */
    public function __construct(
        public float $amount,
        public string $reference,
        public ?string $title = null,
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
        public string $currency = 'MZN',
        public array $channels = ['card', 'mpesa', 'emola', 'mkesh'],
        public ?string $walletId = null,
    ) {}
}
