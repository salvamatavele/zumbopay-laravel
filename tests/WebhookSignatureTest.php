<?php

namespace ZumboPay\Tests;

use PHPUnit\Framework\TestCase;
use ZumboPay\Support\WebhookSignature;

class WebhookSignatureTest extends TestCase
{
    public function test_validates_correct_hmac_signature(): void
    {
        $payload = json_encode(['event' => 'payment.succeeded', 'amount' => 1500]);
        $secret = 'test_secret_key_123';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(WebhookSignature::validate($payload, $signature, $secret));
        $this->assertTrue(WebhookSignature::validate($payload, 'sha256='.$signature, $secret));
    }

    public function test_rejects_tampered_payload_or_signature(): void
    {
        $payload = json_encode(['event' => 'payment.succeeded', 'amount' => 1500]);
        $secret = 'test_secret_key_123';
        $invalidSignature = 'invalid_signature_hash';

        $this->assertFalse(WebhookSignature::validate($payload, $invalidSignature, $secret));
        $this->assertFalse(WebhookSignature::validate($payload, null, $secret));
        $this->assertFalse(WebhookSignature::validate($payload, '', $secret));
    }

    public function test_allows_when_secret_is_empty_in_local_dev(): void
    {
        $payload = json_encode(['event' => 'payment.succeeded']);
        $this->assertTrue(WebhookSignature::validate($payload, 'any_signature', null));
        $this->assertTrue(WebhookSignature::validate($payload, null, ''));
    }
}
