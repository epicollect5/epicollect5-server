<?php

namespace Tests;

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

    public function logTestError($e, $response)
    {
        echo "\e[0;31m" . $e->getMessage() . "\e[0m" . PHP_EOL;
        $jsonResponse = $response[0]->baseResponse->exception === null
            ? json_encode(['response' => $response[0]])
            : json_encode(['exception' => $response[0]->baseResponse->exception->getMessage()]);

        echo "\e[1;34m" . $jsonResponse . "\e[0m" . PHP_EOL;
    }
}
