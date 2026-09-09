<?php

namespace ZumboPay\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZumboPayPaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $reference,
        public float $amount,
        public ?string $channel,
        public array $payload,
    ) {}
}
