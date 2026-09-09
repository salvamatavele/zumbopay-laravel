<?php

namespace ZumboPay\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ZumboPay\Contracts\ZumboPayInterface;
use ZumboPay\Laravel\Http\Controllers\WebhookController;
use ZumboPay\ZumboPayClient;

class ZumboPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/zumbopay.php', 'zumbopay');

        $this->app->singleton(ZumboPayClient::class, function ($app): ZumboPayClient {
            return ZumboPayClient::fromConfig($app['config']['zumbopay'] ?? []);
        });

        $this->app->alias(ZumboPayClient::class, ZumboPayInterface::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publicação do ficheiro de configuração
            $this->publishes([
                __DIR__.'/../../config/zumbopay.php' => config_path('zumbopay.php'),
            ], 'zumbopay-config');

            // Publicação do componente React reutilizável
            $this->publishes([
                __DIR__.'/../../resources/js/components/ZumboPayModal.tsx' => resource_path('js/components/ZumboPayModal.tsx'),
            ], 'zumbopay-react');
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if (config('zumbopay.webhook.enabled', true)) {
            $path = config('zumbopay.webhook.path', '/api/webhooks/zumbopay');

            Route::post($path, WebhookController::class)
                ->name('zumbopay.webhook');
        }
    }
}
