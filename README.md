# Sendery for Laravel

Send Sendery templates with Laravel Mail and Notifications.

[Documentation](https://sendery.co/en/docs/laravel) · [API reference](https://sendery.co/en/docs/send-email) · [Changelog](CHANGELOG.md)

## Requirements

Laravel 13 and PHP 8.3+ with the cURL extension.

## Install

```bash
composer require sendery/laravel:^0.1.1
```

## Configure your application

Create a [project API key](https://sendery.co/en/docs/authentication) and add these values to `.env`. The `sendery` mailer is ready to use after installation. Set `MAIL_FROM_ADDRESS` to your project’s sender address.

```bash
SENDERY_API_KEY=your_project_api_key
MAIL_FROM_ADDRESS=hello@your-domain.com
MAIL_FROM_NAME="Your app"
```

## Send an email

Send a published template with `TemplateMail`. Replace `your-template` with your published template’s key and `data` with its variables. Set the sender in your Sendery project.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::mailer('sendery')->to('alex@example.com')->send(
    new TemplateMail('your-template', [
        'name' => 'Alex',
        'action_url' => 'https://example.com/start',
    ])
);
```

## Send a specific version

Choose a [published template version](https://sendery.co/en/docs/send-email#section-5) to keep sending it after newer versions are published. By default, Sendery uses the latest version.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

$email = (new TemplateMail('your-template', [
    'name' => 'Alex',
    'action_url' => 'https://example.com/start',
]))->version(3);

Mail::mailer('sendery')->to('alex@example.com')->send($email);
```

## Attachments

Attach files to `TemplateMail` with Laravel’s `attachData()` method.

Send up to 10 files totaling 5 MB. See the [attachment reference](https://sendery.co/en/docs/send-email#section-6) for supported formats and limits.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

$email = new TemplateMail('your-template', [
    'name' => 'Alex',
    'action_url' => 'https://example.com/start',
], idempotencyKey: 'your-idempotency-key');

$email->attachData(file_get_contents('/path/document.pdf'), 'document.pdf', [
    'mime' => 'application/pdf',
]);

Mail::mailer('sendery')->to('alex@example.com')->send($email);
```

## Queue an email

Use `queue()` to send in the background. With a Laravel queue worker running, failed jobs can [retry the same email safely](https://sendery.co/en/docs/queues).

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::mailer('sendery')->to('alex@example.com')->queue(
    new TemplateMail('your-template', [
        'name' => 'Alex',
        'action_url' => 'https://example.com/start',
    ])
);
```

## Password resets and verification

Choose a published template for each email, then add this to `AppServiceProvider::boot()`. Laravel supplies `name` and `action_url`, so use `{{ action_url }}` for the link in your templates.

To send in the notification’s language, add `useNotificationLocale: true` and [publish the translations](https://sendery.co/en/docs/languages).

```php
// app/Providers/AppServiceProvider.php
use Sendery\Laravel\Sendery;

public function boot(): void
{
    Sendery::useBuiltInNotifications(
        passwordResetTemplate: 'your-reset-template',
        emailVerificationTemplate: 'your-verification-template',
    );
}
```

## Custom notifications

Use `SenderyChannel` to send from a Laravel notification. Return the template key and its variables from `toSendery()`.

```php
use Illuminate\Notifications\Notification;
use Sendery\Laravel\SenderyChannel;

class TemplateNotification extends Notification
{
    public function __construct(
        public string $template,
        public array $data,
    ) {}

    public function via(object $notifiable): array
    {
        return [SenderyChannel::class];
    }

    public function toSendery(object $notifiable): array
    {
        return ['template' => $this->template, 'data' => $this->data];
    }
}

$user->notify(new TemplateNotification('your-template', ['name' => 'Alex']));
```

## Handle failures

A failed send throws an exception. Use [queue retries](https://sendery.co/en/docs/queues) for timeouts and temporary failures. Fix [API key, template, or billing errors](https://sendery.co/en/docs/errors) before trying again.

## More

Learn how to [retry emails without duplicate sends](https://sendery.co/en/docs/idempotency).

## License

[MIT](LICENSE).
