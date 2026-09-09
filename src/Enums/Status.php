<?php

namespace ZumboPay\Enums;

enum Status: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Completed = 'completed';
    case Failed = 'failed';
    case Declined = 'declined';
    case Timeout = 'timeout';
    case Error = 'error';

    public function isPaid(): bool
    {
        return in_array($this, [self::Success, self::Completed], true);
    }
}
