<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Api;

final class RequestSigner
{
    public function __construct(private readonly string $sharedSecret)
    {
    }

    public function sign(string $nonce, string $body): string
    {
        return hash_hmac('sha256', $nonce . ':' . $body, $this->sharedSecret);
    }
}
