<?php

declare(strict_types=1);

namespace DialClient\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Minimal HTTP abstraction used by Dial API resources.
 *
 * Implementations may use Guzzle, Symfony HttpClient, curl, etc.
 */
interface DialHttpClientInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface;

    /**
     * @param array<string, mixed> $options
     * @return array<mixed>
     */
    public function requestJson(string $method, string $uri, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     */
    public function requestStream(string $method, string $uri, array $options = []): ResponseInterface;
}
