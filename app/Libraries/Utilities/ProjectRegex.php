<?php

namespace ec5\Libraries\Utilities;

final class ProjectRegex
{
    private const array DELIMITERS = [
        '/', '#', '~', '%', '!', ';', ':', ',', '=', '@',
        '|', '^', '+', '*', '-', "'", '`', '?', '&',
    ];

    public static function compile(string $pattern): string
    {
        foreach (self::DELIMITERS as $delimiter) {
            if (!str_contains($pattern, $delimiter)) {
                return $delimiter . $pattern . $delimiter . 'u';
            }
        }

        return '/' . self::escapeDelimiter($pattern, '/') . '/u';
    }

    public static function toRule(string $pattern): string
    {
        return 'regex:' . self::compile($pattern);
    }

    public static function error(mixed $pattern): ?string
    {
        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        $warning = null;

        set_error_handler(function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });

        try {
            preg_match(self::compile($pattern), '');
        } finally {
            restore_error_handler();
        }

        if ($warning === null) {
            return null;
        }

        return str_replace(
            ['preg_match(): Compilation failed: ', 'preg_match(): '],
            '',
            $warning
        );
    }

    public static function isValid(mixed $pattern): bool
    {
        return self::error($pattern) === null;
    }

    private static function escapeDelimiter(string $pattern, string $delimiter): string
    {
        return preg_replace_callback(
            '/(\\\\*)' . preg_quote($delimiter, '/') . '/',
            static fn (array $matches): string => strlen($matches[1]) % 2 === 1
                ? $matches[0]
                : $matches[1] . '\\' . $delimiter,
            $pattern
        );
    }
}
