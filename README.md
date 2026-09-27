# Sendery for Laravel

Send Sendery templates with Laravel Mail and Notifications.

[Documentation](https://sendery.co/en/docs/laravel) · [API reference](https://sendery.co/en/docs/send-email) · [Changelog](CHANGELOG.md)

## Requirements

Laravel 13 and PHP 8.3+ with the cURL extension.

## Install

```bash
composer require sendery/laravel:^0.1
```

## Configure your application

Publish a `welcome` template with `name` and `action_url` variables, and create a [project API key](https://sendery.co/en/docs/authentication). Store it as `SENDERY_API_KEY` on your server. Add these values to `.env`. The package registers the `sendery` mailer automatically. `MAIL_FROM_ADDRESS` is required by Laravel; the actual sender comes from your Sendery project.

```dotenv
SENDERY_API_KEY=your_project_api_key
MAIL_FROM_ADDRESS=hello@your-domain.com
MAIL_FROM_NAME="Your app"
```

## Send an email

Use `TemplateMail` with the `sendery` mailer. It accepts one recipient and a published template. HTML mailables, attachments, and `cc` or `bcc` recipients are not supported.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::mailer('sendery')->to('alex@example.com')->send(
    new TemplateMail('welcome', [
        'name' => 'Alex',
        'action_url' => 'https://example.com/start',
    ])
);
```

## Queue an email

Use `queue()` in place of `send()`. Configure your Laravel queue connection and run a worker. A [retry of the same queued mailable](https://sendery.co/en/docs/queues) keeps its key and template data.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::mailer('sendery')->to('alex@example.com')->queue(
    new TemplateMail('welcome', [
        'name' => 'Alex',
        'action_url' => 'https://example.com/start',
    ])
);
```

## Password resets and verification

Publish `password-reset` and `email-verification` templates with `name` and `action_url`. Add the call below to `AppServiceProvider::boot()` to send Laravel’s standard reset and verification notifications through Sendery. Custom notification subclasses are unaffected. Templates use their default language; pass `useNotificationLocale: true` to select the notification language, and publish each language you use.

```php
// app/Providers/AppServiceProvider.php
use Sendery\Laravel\Sendery;

public function boot(): void
{
    Sendery::useBuiltInNotifications();
}
```

## Custom notifications

Return `SenderyChannel::class` from `via()` and define `toSendery()`. Pass the original event key and variables when creating this notification. You can also return `locale` to [select a published language](https://sendery.co/en/docs/languages). The notifiable must route mail to one email address.

```php
use Illuminate\Notifications\Notification;
use Sendery\Laravel\SenderyChannel;

class OrderConfirmation extends Notification
{
    public function __construct(
        private string $eventKey,
        private array $variables,
    ) {}

    public function via(object $notifiable): array
    {
        return [SenderyChannel::class];
    }

    public function toSendery(object $notifiable): array
    {
        return [
            'template' => 'order-confirmation',
            'data' => $this->variables,
            'idempotency_key' => $this->eventKey,
        ];
    }
}
```

## Handle failures

The mailer throws Symfony’s `TransportException`; its previous exception is `Sendery\ApiException` for API failures. The notification channel throws `Sendery\ApiException` directly. Neither adds automatic retries. [Retry temporary failures with the original key and data](https://sendery.co/en/docs/idempotency).

## More

See [idempotency and retries](https://sendery.co/en/docs/idempotency) for retry conditions, delays, and reusing a key across attempts.

## License

[MIT](LICENSE).
