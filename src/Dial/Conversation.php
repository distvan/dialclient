<?php

declare(strict_types=1);

namespace DialClient\Dial;

use InvalidArgumentException;
use function array_values;
use function bin2hex;
use function random_bytes;

class Conversation
{
    private string $id;

    /**
     * @var Message[]
     */
    private array $messages = [];

    /**
     * @param Message[] $messages
     */
    public function __construct(?string $id = null, array $messages = [])
    {
        $this->id = $id ?? bin2hex(random_bytes(16));

        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                throw new InvalidArgumentException('All messages must be instances of ' . Message::class);
            }
        }

        $this->messages = array_values($messages);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
