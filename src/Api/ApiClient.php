<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Api;

use Shopware\Core\Framework\Util\Random;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private RequestSigner $requestSigner,
        private string $baseUrl,
        private string $instance,
        private string $userAgent,
        private int $timeout = 30,
    ) {
    }

    public function request(string $method, string $url, ?array $query = null, ?array $data = null): ResponseInterface
    {
        $body = null !== $data ? json_encode($data, \JSON_PRETTY_PRINT) : null;

        $headers = array_merge([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Makaira-Instance' => $this->instance,
        ], $this->getAuthenticationHeaders($body));

        return $this->httpClient->request($method, $url, [
            'base_uri' => $this->baseUrl,
            'headers' => $headers,
            'query' => $query,
            'body' => $body,
            'http_version' => '1.1',
            'timeout' => $this->timeout,
        ]);
    }

    private function getAuthenticationHeaders(?string $body): array
    {
        $nonce = Random::getString(32);

        return [
            'X-Makaira-Nonce' => $nonce,
            'X-Makaira-Hash' => $this->requestSigner->sign($nonce, $body ?? ''),
        ];
    }
}
