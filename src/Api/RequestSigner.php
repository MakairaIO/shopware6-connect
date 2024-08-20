<?php

declare(strict_types=1);

namespace Makaira\Connect\Api;

final readonly class RequestSigner
{
    public function __construct(private string $sharedSecret)
    {
    }

    public function sign(string $nonce, string $body): string
    {
        return hash_hmac('sha256', $nonce . ':' . $body, $this->sharedSecret);
    }
}
