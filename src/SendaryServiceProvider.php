<?php

namespace Sendary\Laravel;

use Illuminate\Support\ServiceProvider;

class SendaryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sendary.php', 'sendary');
        $this->app->singleton(Client::class, fn () => new Client((string) config('sendary.api_key'), (string) config('sendary.url')));
    }
}
