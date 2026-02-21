<?php

declare(strict_types=1);

namespace DialClient\Http;

use DialClient\Exception\JsonDecodingException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Small convenience wrapper around Guzzle, with JSON + async + stream helpers.
 */
final class GuzzleClient implements DialAsyncHttpClientInterface
{
    private ClientInterface $client;

    /** @var array<string, string> */
    private array $defaultHeaders;

    /**
     * @param array<string, string> $defaultHeaders
     * @param array<string, mixed>  $guzzleOptions
     */
    public function __construct(
        string $baseUri,
        ?string $apiKey = null,
        array $defaultHeaders = [],
        array $guzzleOptions = [],
        ?ClientInterface $client = null,
    ) {
        $headers = $defaultHeaders;

        $headers['Accept'] ??= 'application/json';

        if ($apiKey !== null && $apiKey !== '') {
            $headers['Authorization'] ??= 'Bearer ' . $apiKey;
        }

        $this->defaultHeaders = $headers;

        $this->client = $client ?? new Client([
            'base_uri' => $baseUri,
            'headers' => $this->defaultHeaders,
        ] + $guzzleOptions);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $this->normalizeOptions($options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function requestAsync(string $method, string $uri, array $options = []): PromiseInterface
    {
        return $this->client->requestAsync($method, $uri, $this->normalizeOptions($options));
    }

    /**
     * @return array<mixed>
     */
    public function requestJson(string $method, string $uri, array $options = []): array
    {
        $response = $this->request($method, $uri, $options);
        return $this->decodeJsonResponse($response);
    }

    /**
     * @return PromiseInterface Promise resolves to the decoded JSON array.
     */
    public function requestJsonAsync(string $method, string $uri, array $options = []): PromiseInterface
    {
        return $this->requestAsync($method, $uri, $options)
            ->then(function (ResponseInterface $response): array {
                return $this->decodeJsonResponse($response);
            });
    }

    /**
     * Returns the raw streaming response body. Callers can read from
     * `$response->getBody()`.
     */
    public function requestStream(string $method, string $uri, array $options = []): ResponseInterface
    {
        $options['stream'] = true;
        return $this->request($method, $uri, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        $headers = $this->defaultHeaders;
        if (isset($options['headers']) && \is_array($options['headers'])) {
            /** @var array<string, string> $optHeaders */
            $optHeaders = $options['headers'];
            $headers = $optHeaders + $headers;
        }

        $options['headers'] = $headers;

        return $options;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJsonResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        try {
            /** @var array<mixed> $decoded */
            $decoded = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonDecodingException('Failed to decode JSON response.', 0, $e);
        }

        return $decoded;
    }
}
