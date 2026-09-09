<?php

namespace ZumboPay\Support;

use ZumboPay\Enums\Channel;

class PhoneNormalizer
{
    /**
     * Normaliza qualquer número de telemóvel moçambicano para o formato 258XXXXXXXXX (12 dígitos)
     */
    public static function normalize(string $phone): string
    {
        $clean = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($clean, '258') && strlen($clean) === 12) {
            return $clean;
        }

        if (strlen($clean) === 9) {
            return '258'.$clean;
        }

        return $clean;
    }

    /**
     * Valida se é um número válido de Moçambique (9 dígitos locais ou 12 dígitos com 258)
     */
    public static function isValid(string $phone): bool
    {
        $normalized = self::normalize($phone);

        if (strlen($normalized) !== 12 || ! str_starts_with($normalized, '258')) {
            return false;
        }

        $local = substr($normalized, 3);
        $prefix = substr($local, 0, 2);

        return in_array($prefix, ['84', '85', '86', '87', '82', '83'], true);
    }

    /**
     * Deteta o canal de pagamento (Mpesa, Emola ou Mkesh) com base no prefixo
     */
    public static function detectChannel(string $phone): ?Channel
    {
        $normalized = self::normalize($phone);
        $local = substr($normalized, 3);
        $prefix = substr($local, 0, 2);

        return match ($prefix) {
            '84', '85' => Channel::Mpesa,
            '86', '87' => Channel::Emola,
            '82', '83' => Channel::Mkesh,
            default => null,
        };
    }
}
