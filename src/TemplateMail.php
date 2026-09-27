<?php

namespace Sendery\Laravel;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class TemplateMail extends Mailable
{
    use Queueable;

    private string $variablesJson;

    public readonly string $idempotencyKey;

    public function __construct(private string $templateKey, array $variables, private ?string $language = null, ?string $idempotencyKey = null)
    {
        $this->variablesJson = json_encode((object) $variables, JSON_THROW_ON_ERROR);
        $this->idempotencyKey = $idempotencyKey ?? bin2hex(random_bytes(16));
    }

    public function build(): static
    {
        return $this->html(' ')->withSymfonyMessage(function (Email $message): void {
            foreach (['X-Sendery-Template', 'X-Sendery-Data', 'X-Sendery-Locale', 'X-Sendery-Key'] as $name) {
                $message->getHeaders()->remove($name);
            }
            $message->getHeaders()->addTextHeader('X-Sendery-Template', $this->templateKey);
            $message->getHeaders()->addTextHeader('X-Sendery-Data', base64_encode($this->variablesJson));
            $message->getHeaders()->addTextHeader('X-Sendery-Key', $this->idempotencyKey);
            if ($this->language !== null) {
                $message->getHeaders()->addTextHeader('X-Sendery-Locale', $this->language);
            }
        });
    }
}
