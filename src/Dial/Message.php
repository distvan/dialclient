<?php

declare(strict_types=1);

namespace DialClient\Dial;

class Message
{
    public function __construct(
        public Role $role,
        public string $content,
    ) {
    }
}
