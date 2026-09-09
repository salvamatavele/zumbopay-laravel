<?php

namespace ZumboPay\Enums;

enum Channel: string
{
    case Mpesa = 'mpesa';
    case Emola = 'emola';
    case Mkesh = 'mkesh';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Mpesa => 'M-Pesa (Vodacom)',
            self::Emola => 'e-Mola (Movitel)',
            self::Mkesh => 'mKesh (Tmcel)',
            self::Card => 'Cartão Bancário',
        };
    }
}
