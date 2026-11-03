<?php

namespace ec5\Libraries\Utilities;

final class ProjectRegex
{
    private const DELIMITERS = [
        '/', '#', '~', '%', '!', ';', ':', ',', '=', '@',
        '|', '^', '+', '*', '-', "'", '`', '?', '&',
    ];

    private const COMPILE_WARNING = 'Compilation failed';

    public static function error(mixed $pattern): ?string
    {
        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        foreach (self::DELIMITERS as $delimiter) {
            if (str_contains($pattern, $delimiter)) {
                continue;
            }

            $warning = null;

            set_error_handler(function (int $severity, string $message) use (&$warning): bool {
                $warning = $message;
                return true;
            });

            try {
                preg_match($delimiter . $pattern . $delimiter . 'u', '');
            } finally {
                restore_error_handler();
            }

            if ($warning === null || !str_contains($warning, self::COMPILE_WARNING)) {
                return null;
            }

            return str_replace('preg_match(): Compilation failed: ', '', $warning);
        }

        return 'cannot validate: pattern contains every candidate delimiter';
    }

    public static function isValid(mixed $pattern): bool
    {
        return self::error($pattern) === null;
    }
}
