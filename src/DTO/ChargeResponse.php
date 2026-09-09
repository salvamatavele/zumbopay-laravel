<?php

namespace ZumboPay\DTO;

use ZumboPay\Enums\Status;

class ChargeResponse
{
    public function __construct(
        public bool $success,
        public Status $status,
        public ?string $reference = null,
        public ?string $message = null,
        public ?string $code = null,
        public array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status->isPaid();
    }

    public function isPending(): bool
    {
        return $this->status === Status::Pending;
    }
}
