<?php

namespace ec5\Libraries\Utilities;

final class ProjectRegex
{
    private const array DELIMITERS = [
        '/', '#', '~', '%', '!', ';', ':', ',', '=', '@',
        '|', '^', '+', '*', '-', "'", '`', '?', '&',
    ];

    // PCRE2_UTF without PCRE2_UCP, i.e. the same as the mobile app's pcre2-wasm
    // FLAGS.UTF. PHP's "u" modifier would additionally enable UCP, which makes
    // \d \w \s match non-ASCII characters; this mode keeps them ASCII while
    // preserving UTF-8 codepoint semantics for . and quantifiers.
    private const string UTF_MODE = '(*UTF)';

    public static function compile(string $pattern): string
    {
        foreach (self::DELIMITERS as $delimiter) {
            if (!str_contains($pattern, $delimiter)) {
                return $delimiter . self::UTF_MODE . $pattern . $delimiter;
            }
        }

        return '/' . self::UTF_MODE . self::escapeDelimiter($pattern, '/') . '/';
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
            $result = preg_match(self::compile($pattern), '');
        } finally {
            restore_error_handler();
        }

        // Reject on a failed match attempt, or on any captured warning (not only
        // "Compilation failed" ones). Valid patterns without a warning are accepted.
        if ($warning === null && $result !== false) {
            return null;
        }

        return str_replace(
            ['preg_match(): Compilation failed: ', 'preg_match(): '],
            '',
            $warning ?? 'invalid regular expression'
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
