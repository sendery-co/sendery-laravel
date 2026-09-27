<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\TestCase;
use Sendery\Laravel\SenderyServiceProvider;
use Sendery\Laravel\TemplateMail;

class LaravelTransportTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SenderyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sendery.api_key', 'test-key');
        $app['config']->set('sendery.url', 'https://sendery.example');
        $app['config']->set('mail.mailers.sendery', ['transport' => 'sendery']);
        $app['config']->set('mail.from', ['address' => 'hello@example.com', 'name' => 'Example']);
    }

    public function test_serialized_mailable_sends_frozen_variables_and_key(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sendery.example/*' => Http::response(['id' => 'email-one', 'status' => 'queued'], 202)]);
        $data = ['name' => 'Original'];
        $mail = new TemplateMail('welcome', $data);
        $data['name'] = 'Changed';
        $restored = unserialize(serialize($mail));
        Mail::mailer('sendery')->to('alex@example.com')->send($restored);
        Http::assertSent(fn ($request) => $request['data']['name'] === 'Original' && $request['to'] === 'alex@example.com' && $request->hasHeader('Idempotency-Key', $mail->idempotencyKey));
    }
}
