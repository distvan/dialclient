<?php

declare(strict_types=1);

namespace DialClient\Dial;

enum Role: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case AI = 'assistant';
}
