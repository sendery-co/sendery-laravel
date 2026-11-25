<?php

namespace Sendery\Laravel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;
use Sendery\Attachment;
use Sendery\Symfony\SenderyTransport;
use Sendery\Symfony\TemplateRenderer;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mime\Email;

class TemplateMail extends Mailable
{
    use Queueable;

    private string $variablesJson;

    private bool $renderForTransport = false;

    private bool $templateCallbackRegistered = false;

    private ?int $templateVersion = null;

    public readonly string $idempotencyKey;

    public function __construct(private string $templateKey, array $variables, private ?string $language = null, ?string $idempotencyKey = null)
    {
        $this->variablesJson = json_encode((object) $variables, JSON_THROW_ON_ERROR);
        $this->idempotencyKey = $idempotencyKey ?? bin2hex(random_bytes(16));
    }

    public static function fromNotification(object $notifiable, Notification $notification): static
    {
        $data = $notification->toSendery($notifiable);
        $recipient = $notifiable->routeNotificationFor('mail', $notification);
        if (! is_string($recipient)) {
            throw new \InvalidArgumentException('Sendery requires one email address.');
        }
        $notification->id ??= (string) Str::uuid();
        $mail = (new static($data['template'], $data['data'], $data['locale'] ?? null,
            $data['idempotency_key'] ?? hash('sha256', $notification->id.'|'.$data['template'].'|'.$recipient)))->to($recipient);
        if (isset($data['version'])) {
            $mail->version($data['version']);
        }
        foreach ($data['attachments'] ?? [] as $attachment) {
            if (! $attachment instanceof Attachment) {
                throw new \InvalidArgumentException('Use Attachment objects.');
            }
            $file = $attachment->jsonSerialize();
            $mail->attachData(base64_decode($file['content'], true), $file['filename'], ['mime' => $file['content_type']]);
        }

        return $mail;
    }

    public function send($mailer)
    {
        if ($mailer instanceof MailFake) {
            return $mailer->send($this);
        }
        $mailer = $mailer instanceof Factory ? $mailer->mailer($this->mailer) : $mailer;
        $transport = $mailer->getSymfonyTransport();
        if ($transport instanceof RoundRobinTransport && preg_match('/(?:[ (])sendery(?:[ )])/', (string) $transport)) {
            throw new \LogicException('Use the Sendery mailer directly; it cannot be combined with fallback transports.');
        }
        $this->renderForTransport = ! $transport instanceof SenderyTransport;

        return parent::send($mailer);
    }

    public function render()
    {
        return app(Client::class)->render($this->templateKey, json_decode($this->variablesJson, true, flags: JSON_THROW_ON_ERROR), $this->language, $this->templateVersion)['html'];
    }

    public function version(int $version): static
    {
        if ($version < 1) {
            throw new \InvalidArgumentException('Version must be a positive integer.');
        }
        $this->templateVersion = $version;

        return $this;
    }

    public function build(): static
    {
        $this->html(' ');
        if ($this->templateCallbackRegistered) {
            return $this;
        }
        $this->templateCallbackRegistered = true;

        return $this->withSymfonyMessage(function (Email $message): void {
            foreach (['X-Sendery-Template', 'X-Sendery-Data', 'X-Sendery-Locale', 'X-Sendery-Key', 'X-Sendery-Version'] as $name) {
                $message->getHeaders()->remove($name);
            }
            $message->getHeaders()->addTextHeader('X-Sendery-Template', $this->templateKey);
            $message->getHeaders()->addTextHeader('X-Sendery-Data', base64_encode($this->variablesJson));
            $message->getHeaders()->addTextHeader('X-Sendery-Key', $this->idempotencyKey);
            if ($this->templateVersion !== null) {
                $message->getHeaders()->addTextHeader('X-Sendery-Version', (string) $this->templateVersion);
            }
            if ($this->language !== null) {
                $message->getHeaders()->addTextHeader('X-Sendery-Locale', $this->language);
            }
            if ($this->renderForTransport) {
                (new TemplateRenderer(app(Client::class)))->render($message);
            }
        });
    }
}
