<?php

namespace Tests;

use PHPUnit_Framework_BaseTestListener;
use PHPUnit_Framework_Test;
use PHPUnit_Framework_TestCase;

class MemoryLoggerListener extends PHPUnit_Framework_BaseTestListener
{

    public function startTest(PHPUnit_Framework_Test $test)
    {
        echo 'Running: ' . $test->getName() . PHP_EOL;
    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        // Check if the test is an instance of PHPUnit_Framework_TestCase
        if ($test instanceof PHPUnit_Framework_TestCase) {
            $memoryUsage = memory_get_usage();
            $memoryPeakUsage = memory_get_peak_usage();

            echo sprintf(
                " Memory usage after %s, Peak memory usage: %s\n",
                $this->formatBytes($memoryUsage),
                $this->formatBytes($memoryPeakUsage)
            );
            echo PHP_EOL;

        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

