<?php

declare(strict_types=1);

namespace DialClient\Dial;

use DialClient\Exception\DialClientException;
use DialClient\Http\DialAsyncHttpClientInterface;
use DialClient\Http\DialHttpClientInterface;
use DialClient\Util\Json;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Generator;
use function is_array;
use function is_string;
use function str_replace;
use function str_contains;
use function trim;
use function ltrim;
use function implode;
use function strpos;
use function substr;
use function explode;
use function str_starts_with;
use function rawurlencode;
use function strtolower;

class DialChatCompletions
{
    protected string $endpoint = '/openai/deployments/{deployment_name}/chat/completions';

    protected string $method = 'POST';

    public function __construct(
        private readonly DialHttpClientInterface $client,
        private readonly ?string $deploymentName = null,
        private readonly ?string $apiKeyHeaderValue = null,
        ?string $endpoint = null,
    ) {
        if (!empty($endpoint)) {
            $this->endpoint = $endpoint;
        }
    }

    /**
     * Sends synchronous request to DIAL API with the given payload
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options Additional Guzzle request options.
     * @return Message
     */
    public function create(array $payload, array $options = []): Message
    {
        $endpoint = $this->resolveEndpoint($payload);

        $options['json'] = $payload;
        $options = $this->withJsonContentTypeHeader($options);
        $options = $this->withApiKeyHeader($options);
        $response = $this->client->request($this->method, $endpoint, $options);

        if ($response->getStatusCode() === 200) {
            $responseJson = Json::decodeResponse($response);
            $choices = $responseJson['choices'] ?? [];
            if ($choices) {
                $content = $choices[0]['message']['content'] ?? '';
                return new Message(Role::AI, (string) $content);
            }
        }

        throw new DialClientException('Unexpected HTTP status code: ' . $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options Additional Guzzle request options.
     * @return PromiseInterface Promise resolves to the decoded JSON array.
     */
    public function createAsync(array $payload, array $options = []): PromiseInterface
    {
        if (!($this->client instanceof DialAsyncHttpClientInterface)) {
            throw new DialClientException('Async requests require a client implementing ' . DialAsyncHttpClientInterface::class . '.');
        }

        $endpoint = $this->resolveEndpoint($payload);
        $options['json'] = $payload;
        $options = $this->withJsonContentTypeHeader($options);
        $options = $this->withApiKeyHeader($options);
        return $this->client->requestJsonAsync($this->method, $endpoint, $options);
    }

    /**
     * Consumes a streaming (SSE) response and returns the assembled assistant message.
     *
     * If you want incremental output, prefer iterating {@see streamText()} (or {@see streamChunks()}).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options Additional Guzzle request options.
     * @throws DialClientException
     */
    public function stream(array $payload, array $options = []): Message
    {
        $content = '';
        foreach ($this->streamText($payload, $options) as $delta) {
            $content .= $delta;
        }

        return new Message(Role::AI, $content);
    }

    /**
     * Iterates decoded SSE JSON chunks.
     *
     * Each SSE event's `data:` payload is expected to be a JSON object compatible with
     * OpenAI-style streaming responses. The stream ends with a `data: [DONE]` event.
     *
     * Note: many APIs send a final JSON chunk where `finish_reason` becomes non-null,
     * and then a separate `data: [DONE]` event. This iterator yields the final chunk
     * and stops when `[DONE]` is received.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return Generator<int, array<mixed>, void, void>
     * @throws DialClientException
     */
    public function streamChunks(array $payload, array $options = []): Generator
    {
        $response = $this->openStream($payload, $options);

        foreach ($this->iterateSseData($response->getBody()) as $data) {
            if ($data === '[DONE]') {
                return;
            }

            if ($data === '') {
                continue;
            }

            yield Json::decodeString($data);
        }
    }

    /**
     * Iterates only the incremental text fragments (choices[0].delta.content).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return Generator<int, string, void, void>
     * @throws DialClientException
     */
    public function streamText(array $payload, array $options = []): Generator
    {
        foreach ($this->streamChunks($payload, $options) as $chunk) {
            if (!isset($chunk['choices']) || !is_array($chunk['choices']) || $chunk['choices'] === []) {
                continue;
            }

            $choice0 = $chunk['choices'][0];
            if (!is_array($choice0)) {
                continue;
            }

            $delta = $choice0['delta'] ?? null;
            if (!is_array($delta)) {
                continue;
            }

            $content = $delta['content'] ?? null;
            if (is_string($content) && $content !== '') {
                yield $content;
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return ResponseInterface
     * @throws DialClientException
     */
    private function openStream(array $payload, array $options): ResponseInterface
    {
        $endpoint = $this->resolveEndpoint($payload);

        $payload['stream'] ??= true;

        $options['json'] = $payload;
        $options = $this->withJsonContentTypeHeader($options);
        $options = $this->withApiKeyHeader($options);

        $response = $this->client->requestStream($this->method, $endpoint, $options);

        if ($response->getStatusCode() !== 200) {
            throw new DialClientException('Unexpected HTTP status code: ' . $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Reads an SSE stream and yields concatenated `data:` payloads per event.
     *
     * @return Generator<int, string, void, void>
     */
    private function iterateSseData(StreamInterface $body): Generator
    {
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // Normalize CRLF to LF to simplify splitting.
            if (str_contains($buffer, "\r\n")) {
                $buffer = str_replace("\r\n", "\n", $buffer);
            }

            yield from $this->processBuffer($buffer);
        }

        // Process any trailing buffered event without a final separator.
        $buffer = trim(str_replace("\r\n", "\n", $buffer));
        if ($buffer !== '') {
            $dataLines = $this->extractDataLines($buffer);
            if ($dataLines !== []) {
                yield implode("\n", $dataLines);
            }
        }
    }

    /**
     * Processes buffered content and yields data lines from complete events.
     *
     * @param string $buffer
     * @return Generator<int, string, void, void>
     */
    private function processBuffer(string &$buffer): Generator
    {
        while (true) {
            $separatorPos = strpos($buffer, "\n\n");
            if ($separatorPos === false) {
                break;
            }

            $rawEvent = substr($buffer, 0, $separatorPos);
            $buffer = substr($buffer, $separatorPos + 2);

            $rawEvent = trim($rawEvent);
            if ($rawEvent === '') {
                continue;
            }

            $dataLines = $this->extractDataLines($rawEvent);
            if ($dataLines === []) {
                continue;
            }

            yield implode("\n", $dataLines);
        }
    }

    /**
     * Extracts data lines from a raw SSE event string.
     *
     * @param string $rawEvent
     * @return array<int, string>
     */
    private function extractDataLines(string $rawEvent): array
    {
        $dataLines = [];
        foreach (explode("\n", $rawEvent) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $dataLines[] = ltrim(substr($line, 5));
            }
        }
        return $dataLines;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveEndpoint(array &$payload): string
    {
        if (!str_contains($this->endpoint, '{deployment_name}')) {
            return $this->endpoint;
        }

        $deploymentName = $this->deploymentName;

        if ($deploymentName === null || $deploymentName === '') {
            $deploymentName = null;

            if (isset($payload['deployment_name']) && is_string($payload['deployment_name']) && $payload['deployment_name'] !== '') {
                $deploymentName = $payload['deployment_name'];
                unset($payload['deployment_name']);
            } elseif (isset($payload['deployment']) && is_string($payload['deployment']) && $payload['deployment'] !== '') {
                $deploymentName = $payload['deployment'];
                unset($payload['deployment']);
            }
        }

        if ($deploymentName === null) {
            throw new DialClientException('Missing deployment name. Provide it via constructor ($deploymentName) or payload key deployment_name/deployment.');
        }

        return str_replace('{deployment_name}', rawurlencode($deploymentName), $this->endpoint);
    }

    /**
     * Adds an `api-key` header for gateways that require it.
     * Caller-provided headers always win.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function withApiKeyHeader(array $options): array
    {
        if ($this->apiKeyHeaderValue === null || $this->apiKeyHeaderValue === '') {
            return $options;
        }

        $headers = [];
        if (isset($options['headers']) && is_array($options['headers'])) {
            $headers = $options['headers'];
        }

        $lower = [];
        foreach ($headers as $name => $_value) {
            if (is_string($name)) {
                $lower[strtolower($name)] = true;
            }
        }

        if (!isset($lower['api-key'])) {
            $headers['api-key'] = $this->apiKeyHeaderValue;
        }

        $options['headers'] = $headers;
        return $options;
    }

    /**
     * Adds `Content-Type: application/json`.
     * Caller-provided headers always win.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function withJsonContentTypeHeader(array $options): array
    {
        $headers = [];
        if (isset($options['headers']) && is_array($options['headers'])) {
            $headers = $options['headers'];
        }

        $lower = [];
        foreach ($headers as $name => $_value) {
            if (is_string($name)) {
                $lower[strtolower($name)] = true;
            }
        }

        if (!isset($lower['content-type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        $options['headers'] = $headers;
        return $options;
    }
}
