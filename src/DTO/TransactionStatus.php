<?php

namespace ZumboPay\DTO;

use ZumboPay\Enums\Status;

class TransactionStatus
{
    public function __construct(
        public string $reference,
        public Status $status,
        public bool $isPaid,
        public ?float $amount = null,
        public ?string $channel = null,
        public ?string $paidAt = null,
        public array $raw = [],
    ) {}
}
