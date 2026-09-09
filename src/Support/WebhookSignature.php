<?php

namespace ZumboPay\Support;

class WebhookSignature
{
    /**
     * Valida a assinatura HMAC-SHA256 recebida no webhook da ZumboPay
     */
    public static function validate(string $rawPayload, ?string $signature, ?string $secret): bool
    {
        if (empty($secret)) {
            return true; // Se o secret não estiver configurado (ex: dev local)
        }

        if (empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signature) || hash_equals('sha256='.$expected, $signature);
    }
}
