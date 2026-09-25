<?php

namespace Sendery\Laravel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Client
{
    public function __construct(private string $apiKey, private string $url)
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! $apiKey || ! $host || ($scheme !== 'https' && ! ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)))) {
            throw new \InvalidArgumentException('Configure SENDERY_API_KEY and an HTTPS SENDERY_URL (HTTP allowed only on loopback).');
        }
    }

    public function send(string $to, string $template, array $data, ?string $locale = null, ?string $idempotencyKey = null): array
    {
        return Http::baseUrl(rtrim($this->url, '/'))->withToken($this->apiKey)->acceptJson()
            ->timeout(10)->connectTimeout(3)->withoutRedirecting()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()])
            ->post('/api/v1/emails', array_filter([
                'to' => $to, 'template' => $template, 'data' => (object) $data, 'locale' => $locale,
            ], fn ($value) => $value !== null))
            ->throw()->json();
    }
}
