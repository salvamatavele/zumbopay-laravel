<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZumboPay API Configuration
    |--------------------------------------------------------------------------
    |
    | As credenciais de acesso à API pública do ZumboPay.
    |
    */
    'base_url' => env('ZUMBOPAY_BASE_URL', 'https://zumbopay.com/api/public/v1'),
    'api_key' => env('ZUMBOPAY_API_KEY'),
    'merchant_id' => env('ZUMBOPAY_MERCHANT_ID'),
    'webhook_secret' => env('ZUMBOPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Carteiras Pré-configuradas (Opcional)
    |--------------------------------------------------------------------------
    |
    | Se deixar vazio, o pacote consultará a sua conta ZumboPay via GET /wallets
    | e associará automaticamente o UUID correspondente a cada canal.
    |
    */
    'wallets' => [
        'mpesa' => env('ZUMBOPAY_WALLET_MPESA'),
        'emola' => env('ZUMBOPAY_WALLET_EMOLA'),
        'mkesh' => env('ZUMBOPAY_WALLET_MKESH'),
        'card' => env('ZUMBOPAY_WALLET_CARD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moeda Padrão
    |--------------------------------------------------------------------------
    */
    'currency' => env('ZUMBOPAY_CURRENCY', 'MZN'),

    /*
    |--------------------------------------------------------------------------
    | Rota de Webhook Automática
    |--------------------------------------------------------------------------
    |
    | Define se o pacote deve registar automaticamente a rota de webhook.
    |
    */
    'webhook' => [
        'enabled' => env('ZUMBOPAY_WEBHOOK_ENABLED', true),
        'path' => env('ZUMBOPAY_WEBHOOK_PATH', '/api/webhooks/zumbopay'),
    ],
];
