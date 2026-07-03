<?php

namespace Tests\Support;

use ec5\Models\User\User;

/**
 * Process-wide monotonic counter for factory-created User IDs.
 *
 * The User factory's closure-scoped `static` is reset every time Laravel
 * rebuilds the application (which happens once per test via
 * TestCase::createApplication()). That resets the counter and causes
 * duplicate-key errors when multiple tests create users in the same
 * PHPUnit process.
 *
 * This class holds the counter in a true class-level static property,
 * which survives application re-creation but is reset between PHPUnit
 * invocations, giving each test a unique id without gaps that matter.
 */
final class TestUserIdSequence
{
    private static ?int $next = null;

    public static function next(): int
    {
        if (self::$next === null) {
            $base = (int) config('testing.TEST_USER_ID_BASE');
            $latest = User::query()
                ->where('id', '>=', $base)
                ->max('id');
            self::$next = $latest === null ? $base : ((int) $latest) + 1;
        }

        return self::$next++;
    }
}
