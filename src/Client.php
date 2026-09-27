<?php

namespace Sendery\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Sendery\ApiException;

class Client extends \Sendery\Client
{
    public function __construct(string $apiKey, string $url = 'https://sendery.co')
    {
        parent::__construct($apiKey, $url, function (string $method, string $url, array $headers, ?string $body): array {
            try {
                $request = Http::withHeaders($headers)->timeout(10)->connectTimeout(3)->withoutRedirecting();
                $response = $request->send($method, $url, $body === null ? [] : ['body' => $body]);

                return ['status' => $response->status(), 'body' => $response->body(), 'retry_after' => $response->header('Retry-After') ?: null];
            } catch (ConnectionException) {
                throw new ApiException(0, 'connection_error');
            }
        });
    }
}
