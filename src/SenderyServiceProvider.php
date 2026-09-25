<?php

namespace Sendery\Laravel;

use Illuminate\Support\ServiceProvider;

class SenderyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sendery.php', 'sendery');
        $this->app->singleton(Client::class, fn () => new Client((string) config('sendery.api_key'), (string) config('sendery.url')));
    }
}
