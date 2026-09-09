<?php

namespace ZumboPay\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZumboPayPaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $reference,
        public ?string $reason,
        public array $payload,
    ) {}
}
