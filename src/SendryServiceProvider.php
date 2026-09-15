<?php

namespace Sendry\Laravel;

use Illuminate\Support\ServiceProvider;

class SendryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sendry.php', 'sendry');
        $this->app->singleton(Client::class, fn () => new Client((string) config('sendry.api_key'), (string) config('sendry.url')));
    }
}
