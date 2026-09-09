<?php

namespace ZumboPay\Tests;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZumboPay\Enums\Status;
use ZumboPay\ZumboPayClient;

class ZumboPayClientTest extends TestCase
{
    public function test_validates_uuid_format(): void
    {
        $client = new ZumboPayClient('key', 'merchant');

        $this->assertTrue($client->isValidUuid('e3b0c442-98fc-1c14-9afb-4c8996fb9242'));
        $this->assertFalse($client->isValidUuid('not-a-uuid'));
        $this->assertFalse($client->isValidUuid(null));
        $this->assertFalse($client->isValidUuid(''));
    }

    public function test_resolves_wallet_for_mozambican_phones(): void
    {
        $client = new ZumboPayClient(
            apiKey: 'key',
            merchantId: 'merchant',
            wallets: [
                'mpesa' => '11111111-1111-1111-1111-111111111111',
                'emola' => '22222222-2222-2222-2222-222222222222',
                'mkesh' => '33333333-3333-3333-3333-333333333333',
            ]
        );

        $this->assertEquals('11111111-1111-1111-1111-111111111111', $client->resolveWalletIdForPhone('841234567'));
        $this->assertEquals('11111111-1111-1111-1111-111111111111', $client->resolveWalletIdForPhone('851234567'));

        $this->assertEquals('22222222-2222-2222-2222-222222222222', $client->resolveWalletIdForPhone('861234567'));
        $this->assertEquals('22222222-2222-2222-2222-222222222222', $client->resolveWalletIdForPhone('871234567'));

        $this->assertEquals('33333333-3333-3333-3333-333333333333', $client->resolveWalletIdForPhone('821234567'));
        $this->assertEquals('33333333-3333-3333-3333-333333333333', $client->resolveWalletIdForPhone('831234567'));
    }

    public function test_stk_push_success(): void
    {
        Http::fake([
            'https://zumbopay.com/api/public/v1/charges' => Http::response([
                'data' => [
                    'reference' => 'ZP-TEST-123',
                    'status' => 'pending',
                ],
            ], 202),
        ]);

        $client = new ZumboPayClient(
            apiKey: 'key',
            merchantId: 'merchant',
            wallets: ['mpesa' => '11111111-1111-1111-1111-111111111111']
        );

        $response = $client->stkPush(
            phone: '841234567',
            amount: 1500.00,
            reference: 'REF-001',
            customerName: 'Manuel Cossa'
        );

        $this->assertTrue($response->success);
        $this->assertEquals(Status::Pending, $response->status);
        $this->assertEquals('ZP-TEST-123', $response->reference);
    }

    public function test_stk_push_immediate_ins0_success(): void
    {
        Http::fake([
            'https://zumbopay.com/api/public/v1/charges' => Http::response([
                'data' => [
                    'reference' => 'ZP-INS0-123',
                    'status' => 'success',
                    'code' => 'INS-0',
                ],
            ], 200),
        ]);

        $client = new ZumboPayClient(
            apiKey: 'key',
            merchantId: 'merchant',
            wallets: ['mpesa' => '11111111-1111-1111-1111-111111111111']
        );

        $response = $client->stkPush(
            phone: '841234567',
            amount: 2500.00,
            reference: 'REF-INS0'
        );

        $this->assertTrue($response->success);
        $this->assertTrue($response->isPaid());
        $this->assertEquals(Status::Success, $response->status);
    }

    public function test_checkout_generation(): void
    {
        Http::fake([
            'https://zumbopay.com/api/public/v1/payments' => Http::response([
                'data' => [
                    'checkout_url' => 'https://zumbopay.com/pay/xyz',
                    'reference' => 'ZP-CARD-123',
                ],
            ], 200),
        ]);

        $client = new ZumboPayClient(
            apiKey: 'key',
            merchantId: 'merchant',
            wallets: ['card' => '11111111-1111-1111-1111-111111111111']
        );

        $response = $client->createCheckout(
            amount: 5000.00,
            reference: 'REF-CARD',
            returnUrl: 'https://example.com/return'
        );

        $this->assertTrue($response->success);
        $this->assertEquals('https://zumbopay.com/pay/xyz', $response->checkoutUrl);
    }

    public function test_transaction_status(): void
    {
        Http::fake([
            'https://zumbopay.com/api/public/v1/payments/REF-999' => Http::response([
                'data' => [
                    'status' => 'completed',
                    'amount' => 1500.00,
                    'channel' => 'mkesh',
                ],
            ], 200),
        ]);

        $client = new ZumboPayClient('key', 'merchant');
        $status = $client->status('REF-999');

        $this->assertTrue($status->isPaid);
        $this->assertEquals(Status::Success, $status->status);
        $this->assertEquals('mkesh', $status->channel);
        $this->assertEquals(1500.00, $status->amount);
    }
}
