<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Contracts\CommunicationProviderInterface;
use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\Contracts\WebhookLogRepositoryInterface;
use App\Modules\Communication\Repositories\EloquentIntegrationAccountRepository;
use App\Modules\Communication\Repositories\EloquentWebhookLogRepository;
use App\Modules\Communication\Services\Providers\MetaWhatsAppProvider;
use Illuminate\Support\ServiceProvider;

class CommunicationModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 1. Bind Interfaces to Eloquent Implementations
        $this->app->bind(
            IntegrationAccountRepositoryInterface::class,
            EloquentIntegrationAccountRepository::class
        );

        $this->app->bind(
            WebhookLogRepositoryInterface::class,
            EloquentWebhookLogRepository::class
        );

        $this->app->bind(
            \App\Modules\Communication\Contracts\WhatsAppContactRepositoryInterface::class,
            \App\Modules\Communication\Repositories\EloquentWhatsAppContactRepository::class
        );

        $this->app->bind(
            \App\Modules\Communication\Contracts\WhatsAppMessageRepositoryInterface::class,
            \App\Modules\Communication\Repositories\EloquentWhatsAppMessageRepository::class
        );

        // 2. Bind the Provider Interface to Meta's Implementation
        $this->app->bind(
            CommunicationProviderInterface::class,
            MetaWhatsAppProvider::class
        );

        // 3. Configure the dynamic logger channel for the Communication module
        // This avoids modifying the global config/logging.php configuration.
        config(['logging.channels.communication' => [
            'driver' => 'single',
            'path' => storage_path('logs/communication.log'),
            'level' => 'debug',
        ]]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load module routes
        $this->loadRoutesFrom(base_path('routes/integrations/whatsapp.php'));

        // Load module views
        $this->loadViewsFrom(__DIR__.'/../views', 'communication');
    }
}
