<?php

namespace ZumboPay\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use ZumboPay\Laravel\Events\ZumboPayPaymentSucceeded;
use ZumboPay\Laravel\Http\Controllers\WebhookController;
use ZumboPay\ZumboPayClient;

class WebhookControllerTest extends TestCase
{
    public function test_dispatches_payment_succeeded_event_on_valid_webhook(): void
    {
        Event::fake([ZumboPayPaymentSucceeded::class]);

        $secret = 'whsec_test_secret';
        $client = new ZumboPayClient(
            apiKey: 'key',
            merchantId: 'merchant',
            webhookSecret: $secret
        );

        $payload = [
            'event' => 'payment.succeeded',
            'data' => [
                'reference' => 'REF-SUCCESS-001',
                'amount' => 1500.00,
                'status' => 'completed',
                'channel' => 'mkesh',
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $secret);

        $controller = new WebhookController($client);

        $request = Request::create(
            uri: '/api/webhooks/zumbopay',
            method: 'POST',
            server: ['HTTP_X_SIGNATURE' => $signature],
            content: $rawBody
        );
        $request->headers->set('Content-Type', 'application/json');

        $response = $controller($request);

        $this->assertEquals(200, $response->getStatusCode());
        Event::assertDispatched(ZumboPayPaymentSucceeded::class, function ($event) {
            return $event->reference === 'REF-SUCCESS-001'
                && $event->amount === 1500.00
                && $event->channel === 'mkesh';
        });
    }

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        Event::fake();

        $client = new ZumboPayClient(
            apiKey: 'key',
            merchantId: 'merchant',
            webhookSecret: 'real_secret'
        );

        $controller = new WebhookController($client);

        $request = Request::create(
            uri: '/api/webhooks/zumbopay',
            method: 'POST',
            server: ['HTTP_X_SIGNATURE' => 'fake_signature'],
            content: json_encode(['event' => 'payment.succeeded'])
        );

        $response = $controller($request);

        $this->assertEquals(401, $response->getStatusCode());
        Event::assertNotDispatched(ZumboPayPaymentSucceeded::class);
    }
}
