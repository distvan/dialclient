<?php

declare(strict_types=1);

namespace DialClient\Util;

use DialClient\Exception\JsonDecodingException;
use Psr\Http\Message\ResponseInterface;

final class Json
{
    private function __construct()
    {
        // Prevent instantiation of this utility class
    }

    /**
     * @return array<mixed>
     */
    public static function decodeResponse(ResponseInterface $response): array
    {
        return self::decodeString((string) $response->getBody());
    }

    /**
     * @return array<mixed>
     */
    public static function decodeString(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            /** @var array<mixed> $decoded */
            $decoded = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonDecodingException('Failed to decode JSON.', 0, $e);
        }

        return $decoded;
    }
}
