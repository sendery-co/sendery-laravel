<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\TestCase;
use Sendery\ApiException;
use Sendery\Attachment;
use Sendery\Laravel\Client;
use Sendery\Laravel\Sendery;
use Sendery\Laravel\SenderyChannel;
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
        $app['config']->set('mail.default', 'sendery');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
        $app['config']->set('mail.from', ['address' => 'hello@example.com', 'name' => 'Example']);
    }

    public function test_events_pass_language_and_declared_email_data_through_the_configured_client(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sendery.example/api/v1/events' => Http::response(['id' => 'event', 'status' => 'accepted'], 202)]);
        app(Client::class)->event(name: 'account.created', objectType: 'account', objectId: '42', data: ['email' => 'user@example.com'], language: 'ja')->send();
        Http::assertSent(fn ($request) => $request['language'] === 'ja' && $request['data']['email'] === 'user@example.com');
    }

    public function test_default_mailer_and_explicit_mailer_are_respected(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sendery.example/api/v1/emails/render' => Http::response(['subject' => 'Hello', 'html' => '<p>Hello</p>', 'text' => 'Hello']),
            'https://sendery.example/api/v1/emails' => Http::response(['id' => 'mail', 'status' => 'queued', 'sandbox_url' => 'https://sendery.example/email-history/mail'], 202),
        ]);
        config(['mail.default' => 'array']);
        $mail = (new TemplateMail('welcome', ['name' => 'Secret'], 'ja'))->version(2)->attachData('PDF', 'invoice.pdf');
        Mail::to('steven@example.com')->send(unserialize(serialize($mail)));
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('<p>Hello</p>', $message->getHtmlBody());
        $this->assertSame('Hello', $message->getTextBody());
        $this->assertCount(1, $message->getAttachments());
        $this->assertStringNotContainsString('X-Sendery-', $message->toString());
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://sendery.example/api/v1/emails/render' && $r['version'] === 2 && $r['locale'] === 'ja');
        Mail::mailer('sendery')->to('steven@example.com')->send($mail);
        Http::assertSent(fn ($r) => $r->url() === 'https://sendery.example/api/v1/emails' && $r['to'] === 'steven@example.com');
        Http::assertSentCount(2);
    }

    public function test_reusing_a_mailable_renders_only_once_per_send(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sendery.example/api/v1/emails/render' => Http::response(['subject' => 'Hello', 'html' => '<p>Hello</p>', 'text' => 'Hello'])]);
        config(['mail.default' => 'array']);
        $mail = (new TemplateMail('welcome', []))->to('steven@example.com');
        Mail::send($mail);
        Mail::send($mail);
        Http::assertSentCount(2);
        $this->assertCount(2, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_sendery_cannot_silently_fall_back_to_smtp(): void
    {
        Http::preventStrayRequests();
        config(['mail.mailers.fallback' => ['transport' => 'failover', 'mailers' => ['sendery', 'array']]]);
        $this->expectException(LogicException::class);
        Mail::mailer('fallback')->to('steven@example.com')->send(new TemplateMail('welcome', []));
    }

    public function test_mail_fake_intercepts_built_in_template_notifications_without_api_calls(): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        Sendery::useBuiltInNotifications();
        ResetPassword::createUrlUsing(fn ($user, $token) => 'https://example.com/reset/'.$token);
        $user = new class extends User
        {
            use Notifiable;
        };
        $user->email = 'steven@example.com';
        try {
            $user->notify(new ResetPassword('token'));
            Mail::assertSent(TemplateMail::class, fn ($mail) => $mail->hasTo('steven@example.com'));
            Http::assertNothingSent();
        } finally {
            ResetPassword::$createUrlCallback = null;
            ResetPassword::$toMailCallback = null;
            VerifyEmail::$toMailCallback = null;
        }
    }

    public function test_render_failure_does_not_fall_back_to_send_api_or_send_an_empty_message(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sendery.example/*' => Http::response(['code' => 'render_limit_exceeded'], 429)]);
        config(['mail.default' => 'array']);
        try {
            Mail::to('steven@example.com')->send(new TemplateMail('welcome', []));
            $this->fail('Expected a render error.');
        } catch (ApiException $error) {
            $this->assertSame(429, $error->status);
        }
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        Http::assertSentCount(1);
    }

    public function test_serialized_mailable_sends_frozen_variables_and_key(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sendery.example/*' => Http::response(['id' => 'email-one', 'status' => 'queued'], 202)]);
        $data = ['name' => 'Original'];
        $mail = (new TemplateMail('welcome', $data))->version(3)->attachData("PDF\x00bytes", 'invoice.pdf', ['mime' => 'application/pdf']);
        $data['name'] = 'Changed';
        $restored = unserialize(serialize($mail));
        Mail::mailer('sendery')->to('steven@example.com')->send($restored);
        Http::assertSent(fn ($request) => $request['attachments'][0]['content'] === base64_encode("PDF\x00bytes") && $request['version'] === 3 && $request['data']['name'] === 'Original' && $request['to'] === 'steven@example.com' && $request->hasHeader('Idempotency-Key', $mail->idempotencyKey));
    }

    public function test_notification_channel_forwards_attachments(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sendery.example/*' => Http::response(['id' => 'invoice', 'status' => 'queued'], 202)]);
        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'steven@example.com';
            }
        };
        $notification = new class extends Notification
        {
            public function toSendery(object $notifiable): array
            {
                return ['template' => 'invoice', 'data' => [], 'version' => 2, 'attachments' => [new Attachment('invoice.pdf', "PDF\x00bytes", 'application/pdf')]];
            }
        };
        $channel = new SenderyChannel;
        $channel->send($notifiable, $notification);
        Http::assertSent(fn ($request) => $request['version'] === 2 && $request['attachments'][0]['content'] === base64_encode("PDF\x00bytes"));
    }
}
