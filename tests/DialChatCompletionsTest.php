<?php

declare(strict_types=1);

namespace DialClient\Tests;

use DialClient\Dial\DialChatCompletions;
use DialClient\Dial\Message;
use DialClient\Dial\Role;
use DialClient\Exception\DialClientException;
use DialClient\Http\DialAsyncHttpClientInterface;
use DialClient\Http\DialHttpClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

const CONTENT_TYPE_JSON = 'application/json';

#[CoversClass(DialChatCompletions::class)]
final class DialChatCompletionsTest extends TestCase
{

    public function testCreateAddsContentTypeHeader(): void
    {
        $client = new RecordingAsyncClient();

        $chat = new DialChatCompletions(
            client: $client,
            deploymentName: 'dep',
        );

        $chat->create(['messages' => []]);

        self::assertIsArray($client->lastOptions['headers'] ?? null);
        self::assertSame(CONTENT_TYPE_JSON, $client->lastOptions['headers']['Content-Type'] ?? null);
    }

    public function testCreateDoesNotOverrideCallerProvidedContentTypeHeader(): void
    {
        $client = new RecordingAsyncClient();

        $chat = new DialChatCompletions(
            client: $client,
            deploymentName: 'dep',
        );

        $chat->create(
            payload: ['messages' => []],
            options: ['headers' => ['content-type' => 'text/plain']]
        );

        self::assertSame('text/plain', $client->lastOptions['headers']['content-type'] ?? null);
        self::assertArrayNotHasKey('Content-Type', $client->lastOptions['headers']);
    }

    public function testCreateAddsApiKeyHeaderWhenProvided(): void
    {
        $client = new RecordingAsyncClient();

        $chat = new DialChatCompletions(
            client: $client,
            deploymentName: 'dep',
            apiKeyHeaderValue: 'secret',
        );

        $chat->create(['messages' => []]);

        self::assertIsArray($client->lastOptions['headers'] ?? null);
        self::assertSame('secret', $client->lastOptions['headers']['api-key'] ?? null);
    }

    public function testCreateDoesNotOverrideCallerProvidedApiKeyHeader(): void
    {
        $client = new RecordingAsyncClient();

        $chat = new DialChatCompletions(
            client: $client,
            deploymentName: 'dep',
            apiKeyHeaderValue: 'secret',
        );

        $chat->create(
            payload: ['messages' => []],
            options: ['headers' => ['api-key' => 'caller']]
        );

        self::assertSame('caller', $client->lastOptions['headers']['api-key'] ?? null);
    }

    public function testCreateReplacesDeploymentNameFromConstructor(): void
    {
        $client = new RecordingAsyncClient();

        $chat = new DialChatCompletions(
            client: $client,
            deploymentName: 'my deploy',
        );

        $result = $chat->create([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        self::assertInstanceOf(Message::class, $result);
        self::assertSame(Role::AI, $result->role);
        self::assertSame('ok', $result->content);
        self::assertSame('POST', $client->lastMethod);
        self::assertSame('/openai/deployments/my%20deploy/chat/completions', $client->lastUri);
        self::assertArrayHasKey('json', $client->lastOptions);
    }

    public function testCreateUsesDeploymentNameFromPayloadAndRemovesKey(): void
    {
        $client = new RecordingAsyncClient();

        $chat = new DialChatCompletions(client: $client);

        $chat->create([
            'deployment_name' => 'dep-1',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        self::assertSame('/openai/deployments/dep-1/chat/completions', $client->lastUri);
        self::assertIsArray($client->lastOptions['json']);
        self::assertArrayNotHasKey('deployment_name', $client->lastOptions['json']);
    }

    public function testCreateThrowsWhenDeploymentMissing(): void
    {
        $client = new RecordingAsyncClient();
        $chat = new DialChatCompletions(client: $client);

        $this->expectException(DialClientException::class);
        $chat->create(['messages' => []]);
    }

    public function testCreateAsyncThrowsWhenClientDoesNotSupportAsync(): void
    {
        $client = new RecordingSyncClient();
        $chat = new DialChatCompletions(client: $client, deploymentName: 'dep');

        $this->expectException(DialClientException::class);
        $chat->createAsync(['messages' => []]);
    }
}

final class RecordingAsyncClient implements DialAsyncHttpClientInterface
{
    private const NOT_NEEDED_FOR_THIS_TEST = 'Not needed for this test.';
    private const MOCK_OK_BODY = '{"choices":[{"message":{"role":"assistant","content":"ok"}}]}';

    public ?string $lastMethod = null;
    public ?string $lastUri = null;

    /** @var array<string, mixed> */
    public array $lastOptions = [];

    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $this->lastMethod = $method;
        $this->lastUri = $uri;
        $this->lastOptions = $options;

        return new Response(200, ['Content-Type' => CONTENT_TYPE_JSON], self::MOCK_OK_BODY);
    }

    /** @return array<mixed> */
    public function requestJson(string $method, string $uri, array $options = []): array
    {
        $this->lastMethod = $method;
        $this->lastUri = $uri;
        $this->lastOptions = $options;

        return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]];
    }

    public function requestStream(string $method, string $uri, array $options = []): ResponseInterface
    {
        throw new \LogicException(self::NOT_NEEDED_FOR_THIS_TEST);
    }

    public function requestJsonAsync(string $method, string $uri, array $options = []): PromiseInterface
    {
        $this->lastMethod = $method;
        $this->lastUri = $uri;
        $this->lastOptions = $options;

        throw new \LogicException(self::NOT_NEEDED_FOR_THIS_TEST);
    }
}

final class RecordingSyncClient implements DialHttpClientInterface
{
    private const NOT_NEEDED_FOR_THIS_TEST = 'Not needed for this test.';
    private const MOCK_OK_BODY = '{"choices":[{"message":{"role":"assistant","content":"ok"}}]}';

    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        return new Response(200, ['Content-Type' => CONTENT_TYPE_JSON], self::MOCK_OK_BODY);
    }

    /** @return array<mixed> */
    public function requestJson(string $method, string $uri, array $options = []): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]]];
    }

    public function requestStream(string $method, string $uri, array $options = []): ResponseInterface
    {
        throw new \LogicException(self::NOT_NEEDED_FOR_THIS_TEST);
    }
}
