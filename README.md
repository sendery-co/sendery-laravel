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
MAIL_MAILER=sendery
SENDERY_API_KEY=your_project_api_key
MAIL_FROM_ADDRESS=hello@your-domain.com
MAIL_FROM_NAME="Your app"
```

Use `MAIL_MAILER=sendery` to send through Sendery, or `MAIL_MAILER=log` for local testing in your application log. Both use your published Sendery templates.

## Send an email

Send a published template with `TemplateMail`. Replace `your-template` with your published template’s key and `data` with its variables. Set the sender in your Sendery project.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::to('steven@example.com')->send(
    new TemplateMail('your-template', [
        'name' => 'Steven',
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
    'name' => 'Steven',
    'action_url' => 'https://example.com/start',
]))->version(3);

Mail::to('steven@example.com')->send($email);
```

## Attachments

Attach files to `TemplateMail` with Laravel’s `attachData()` method.

Send up to 10 files totaling 5 MB. See the [attachment reference](https://sendery.co/en/docs/send-email#section-6) for supported formats and limits.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

$email = new TemplateMail('your-template', [
    'name' => 'Steven',
    'action_url' => 'https://example.com/start',
], idempotencyKey: 'your-idempotency-key');

$email->attachData(file_get_contents('/path/document.pdf'), 'document.pdf', [
    'mime' => 'application/pdf',
]);

Mail::to('steven@example.com')->send($email);
```

## Queue an email

Use `queue()` to send in the background. The Sendery transport keeps the same idempotency key when a Laravel queue worker retries a failed job. Other mail transports follow their own delivery and retry behavior.

```php
use Illuminate\Support\Facades\Mail;
use Sendery\Laravel\TemplateMail;

Mail::to('steven@example.com')->queue(
    new TemplateMail('your-template', [
        'name' => 'Steven',
        'action_url' => 'https://example.com/start',
    ])
);
```

## Password resets and verification

Choose a published template for each email, then add this to `AppServiceProvider::boot()`. Laravel supplies `name` and `action_url`, so use `{{ action_url }}` for the link in your templates. The hook returns a `TemplateMail` through Laravel’s mail channel and preserves Laravel’s reset tokens and verification URLs.

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

Return a `TemplateMail` from `toMail()` and use Laravel’s normal mail channel.

```php
use Illuminate\Notifications\Notification;
use Sendery\Laravel\TemplateMail;

class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): TemplateMail
    {
        return (new TemplateMail('welcome', ['name' => $notifiable->name],
            idempotencyKey: $this->id))->to($notifiable->email);
    }
}
```

`SenderyChannel` also respects the configured Laravel mailer for notifications that implement `toSendery()`.

## Handle failures

A failed send throws an exception. Use [queue retries](https://sendery.co/en/docs/queues) for timeouts and temporary failures. Fix [API key, template, or billing errors](https://sendery.co/en/docs/errors) before trying again.

## More

Learn how to [retry emails without duplicate sends](https://sendery.co/en/docs/idempotency).

## License

[MIT](LICENSE).

## Events and workflows

Send events such as account signups or orders from your app. Your team can use them to automate emails in Sendery, choosing the content, recipients, and timing. Available on all plans.

Start with [Connecting your application](https://sendery.co/en/docs/connect-events) to define the data Sendery can expect, then [send events](https://sendery.co/en/docs/send-events) as they happen. Define at least one email address field and include an address when sending events.

```php
$receipt = app(\Sendery\Laravel\Client::class)
    ->event(
        name: 'account.created',
        language: 'en',
        objectType: 'account',
        objectId: '842',
        data: ['email' => 'steven@example.com', 'name' => 'Steven'],
    )->retry()->send();
```

`language` selects a published template translation; omit it to use the template’s default.

Retries reuse the same event data and idempotency key. To retry across jobs or process restarts, supply and persist your own key. A successful response confirms that Sendery accepted the event. [Learn what you can automate with workflows](https://sendery.co/en/docs/build-workflow).
