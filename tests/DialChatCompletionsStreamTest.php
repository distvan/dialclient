<?php

declare(strict_types=1);

namespace DialClient\Tests;

use DialClient\Dial\DialChatCompletions;
use DialClient\Dial\Message;
use DialClient\Dial\Role;
use DialClient\Http\DialHttpClientInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(DialChatCompletions::class)]
final class DialChatCompletionsStreamTest extends TestCase
{
    public function testStreamChunksAndStreamTextAndStreamAssemblesMessage(): void
    {
        $sse = implode("\n\n", [
            'data: {"choices":[{"delta":{"role":"assistant"},"finish_reason":null}]}',
            'data: {"choices":[{"delta":{"content":"Hel"},"finish_reason":null}]}',
            'data: {"choices":[{"delta":{"content":"lo"},"finish_reason":null}]}',
            'data: {"choices":[{"delta":{},"finish_reason":"stop"}]}',
            'data: [DONE]',
            '',
        ]);

        $client = new RecordingStreamClient($sse);
        $chat = new DialChatCompletions(client: $client, deploymentName: 'dep');

        $chunks = iterator_to_array($chat->streamChunks(['messages' => []]));
        self::assertCount(4, $chunks);
        self::assertSame('assistant', $chunks[0]['choices'][0]['delta']['role']);
        self::assertSame('Hel', $chunks[1]['choices'][0]['delta']['content']);
        self::assertSame('lo', $chunks[2]['choices'][0]['delta']['content']);
        self::assertSame('stop', $chunks[3]['choices'][0]['finish_reason']);

        $textParts = iterator_to_array($chat->streamText(['messages' => []]));
        self::assertSame(['Hel', 'lo'], $textParts);

        $message = $chat->stream(['messages' => []]);
        self::assertInstanceOf(Message::class, $message);
        self::assertSame(Role::AI, $message->role);
        self::assertSame('Hello', $message->content);

        // Ensures stream=true is automatically set on the JSON payload.
        self::assertIsArray($client->lastOptions['json'] ?? null);
        self::assertTrue($client->lastOptions['json']['stream'] ?? false);
    }
}

final class RecordingStreamClient implements DialHttpClientInterface
{
    public ?string $lastMethod = null;
    public ?string $lastUri = null;

    /** @var array<string, mixed> */
    public array $lastOptions = [];

    public function __construct(private readonly string $sseBody)
    {
    }

    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        throw new \LogicException('Not needed for this test.');
    }

    /** @return array<mixed> */
    public function requestJson(string $method, string $uri, array $options = []): array
    {
        throw new \LogicException('Not needed for this test.');
    }

    public function requestStream(string $method, string $uri, array $options = []): ResponseInterface
    {
        $this->lastMethod = $method;
        $this->lastUri = $uri;
        $this->lastOptions = $options;

        $stream = Utils::streamFor($this->sseBody);

        return new Response(200, ['Content-Type' => 'text/event-stream'], $stream);
    }
}
