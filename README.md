# Sendery — Laravel integration

Send template emails from Laravel with the Sendery SDK.

MIT licensed. Repository: https://github.com/sendery-co/sendery-laravel

Documentation: https://sendery.co/en/docs/laravel

## Install

```
composer require sendery/laravel:^0.1
```

## Install

Laravel 13 / PHP 8.3+

## Configure the mailer

Set SENDERY_API_KEY and a valid mail.from address. Package discovery registers the client and transport. Use Mail::mailer("sendery"), or set MAIL_MAILER=sendery as the default.

## Use Laravel queues

TemplateMail works with Mail::mailer("sendery")->to()->send() or queue(). Its generated key is preserved in the queued mailable. Configure your queue worker’s retry policy and timeout to allow the SDK request to complete.

## Built-in authentication emails

Call Sendery::useBuiltInNotifications() in AppServiceProvider::boot(). The package maps standard password-reset and verification notifications to password-reset and email-verification templates, retaining Laravel’s own secure URLs. Custom notification subclasses require an explicit integration.

## Custom notifications

Return SenderyChannel::class from via(). Add toSendery($notifiable) returning template, data, and optional locale or idempotency_key. Use a persisted business-event key for data that may change between job attempts.

## Configuration example

```
SENDERY_API_KEY=your_project_api_key
# Optional: route all template mail through Sendery by default.
MAIL_MAILER=sendery
```

## Example

```
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::mailer('sendery')->to($user->email)->queue(
    new TemplateMail('welcome', ['name' => $user->name])
);
```

## Retries and queues

Reuse a prepared email for retries. New requests receive new keys; when reconstructing a request in another process, supply the original key and unchanged data. Keep API keys server-side. Framework mailers send Sendery templates, not arbitrary HTML or attachments.
