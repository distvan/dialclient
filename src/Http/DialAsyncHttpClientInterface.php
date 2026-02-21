<?php

declare(strict_types=1);

namespace DialClient\Http;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Optional async extension for DialHttpClientInterface.
 */
interface DialAsyncHttpClientInterface extends DialHttpClientInterface
{
    /**
     * @param array<string, mixed> $options
     * @return PromiseInterface Promise resolves to the decoded JSON array.
     */
    public function requestJsonAsync(string $method, string $uri, array $options = []): PromiseInterface;
}
