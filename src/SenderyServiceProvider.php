<?php

namespace Sendery\Laravel;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Sendery\Symfony\SenderyTransport;

class SenderyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sendery.php', 'sendery');
        if (! config('mail.mailers.sendery')) {
            config(['mail.mailers.sendery' => ['transport' => 'sendery']]);
        }
        $this->app->singleton(Client::class, fn () => new Client((string) config('sendery.api_key'), (string) config('sendery.url')));
    }

    public function boot(): void
    {
        Mail::extend('sendery', fn () => new SenderyTransport($this->app->make(Client::class)));
    }
}
