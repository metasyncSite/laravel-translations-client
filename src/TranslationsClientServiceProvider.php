<?php

namespace TranslationsClient;

use Illuminate\Support\ServiceProvider;
use TranslationsClient\Commands\PullTranslationsCommand;
use TranslationsClient\Commands\PushTranslationsCommand;

class TranslationsClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/translations-client.php',
            'translations-client',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/translations-client.php' => config_path('translations-client.php'),
        ], 'translations-client-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PushTranslationsCommand::class,
                PullTranslationsCommand::class,
            ]);
        }
    }
}
