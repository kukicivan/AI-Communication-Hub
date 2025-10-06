<?php

namespace App\Providers;

use App\Services\Messaging\MessageService;
use Illuminate\Support\ServiceProvider;
use App\Services\NodeCommunicationService;
use App\Services\MessagePersistenceService;
use App\Services\MessageSyncService;

class MessagingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register NodeCommunicationService as singleton
        $this->app->singleton(NodeCommunicationService::class, function ($app) {
            return new NodeCommunicationService();
        });

        // Register MessagePersistenceService
        $this->app->singleton(MessagePersistenceService::class, function ($app) {
            return new MessagePersistenceService();
        });

        // Register MessageSyncService
        $this->app->singleton(MessageSyncService::class, function ($app) {
            return new MessageSyncService(
                $app->make(MessageService::class),
                $app->make(MessagePersistenceService::class)
            );
        });

        // Register MessageService
        $this->app->singleton(MessageService::class, function ($app) {
            return new MessageService();
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/messaging.php', 'messaging'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/messaging.php' => config_path('messaging.php'),
            ], 'messaging-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            NodeCommunicationService::class,
            MessagePersistenceService::class,
            MessageSyncService::class,
        ];
    }
}
