<?php

namespace Tests;

use Exception;

class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function logTestError(Exception $e, $response)
    {
        echo "\e[0;31m" . $e->getMessage() . "\e[0m" . PHP_EOL;
        // Get the expected and actual values from the ComparisonFailure object
        $expected = $e->getComparisonFailure()->getExpected();
        $actual = $e->getComparisonFailure()->getActual();

        echo 'Expected: ', $expected . PHP_EOL;
        echo 'Actual: ' . $actual . PHP_EOL;
        if (sizeof($response) > 0) {
            $jsonResponse = $response[0]->baseResponse->exception === null
                ? json_encode(['response' => $response[0]])
                : json_encode(['exception' => $response[0]->baseResponse->exception->getMessage()]);

            echo "\e[1;34m" . $jsonResponse . "\e[0m" . PHP_EOL;
        } else {
            echo "\e[1;34m" . $e->getTraceAsString() . "\e[0m" . PHP_EOL;
        }

        // Mark the test as failed with expected and actual values
        $this->fail($e->getMessage());
    }
}
