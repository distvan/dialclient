<?php

declare(strict_types=1);

namespace DialClient\Util;

use DialClient\Exception\ConsoleInputException;
use function function_exists;
use function readline;
use function fgets;
use function trim;


final class Question
{
    /**
     * Asks a question in the terminal and returns the user's input.
     *
     * - Uses readline() when available.
     * - Falls back to STDIN.
     * - If $default is provided and the user enters an empty line, returns $default.
     */
    public function ask(string $prompt, ?string $default = null): string
    {
        $fullPrompt = $prompt;
        if ($default !== null && $default !== '') {
            $fullPrompt .= ' (Enter for default)';
        }
        $fullPrompt .= ': ';

        if (function_exists('readline')) {
            $line = (string) readline($fullPrompt);
        } else {
            echo $fullPrompt;
            $read = fgets(STDIN);
            if ($read === false) {
                if ($default !== null) {
                    return $default;
                }

                throw new ConsoleInputException('No input received (STDIN closed).');
            }
            $line = (string) $read;
        }

        $value = trim($line);
        if ($value === '' && $default !== null) {
            return $default;
        }

        return $value;
    }
}
