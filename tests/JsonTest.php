<?php

declare(strict_types=1);

namespace DialClient\Tests;

use DialClient\Exception\JsonDecodingException;
use DialClient\Util\Json;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    public function testDecodeResponseDecodesJsonBody(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"ok":true,"n":1}'
        );

        $decoded = Json::decodeResponse($response);

        self::assertSame(['ok' => true, 'n' => 1], $decoded);
    }

    public function testDecodeStringThrowsOnInvalidJson(): void
    {
        $this->expectException(JsonDecodingException::class);
        Json::decodeString('{invalid json');
    }
}
