<?php

namespace ZumboPay\DTO;

class CheckoutResponse
{
    public function __construct(
        public bool $success,
        public ?string $checkoutUrl = null,
        public ?string $reference = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
