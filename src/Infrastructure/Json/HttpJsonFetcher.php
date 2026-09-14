<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpJsonFetcher implements JsonFetcher
{
    public function __construct(
        private HttpClientInterface $http,
    ) {}

    #[\Override]
    public function fetch(string $url): array
    {
        try {
            $payload = $this->http->request('GET', $url)->toArray();
        } catch (ExceptionInterface $e) {
            throw JsonFetchFailed::for($url, $e->getMessage(), $e);
        }

        return $payload;
    }
}
