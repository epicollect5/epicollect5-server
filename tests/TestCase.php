<?php

namespace Tests;

use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\OAuth\OAuthAccessToken;
use ec5\Models\OAuth\OAuthClient;
use ec5\Models\OAuth\OAuthClientProject;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Exception;
use Log;

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
        $expected = '';
        $actual = '';

        echo "\e[0;31m" . $e->getMessage() . "\e[0m" . PHP_EOL;
        // Get the expected and actual values from the ComparisonFailure object
        if (method_exists($e, 'getComparisonFailure') && $e->getComparisonFailure() !== null) {
            $expected = print_r($e->getComparisonFailure()->getExpected(), true) . PHP_EOL;
            $actual = print_r($e->getComparisonFailure()->getActual(), true) . PHP_EOL;
        }

        echo 'Expected: ', $expected ?? PHP_EOL;
        echo 'Actual: ' . $actual ?? PHP_EOL;
        if (is_array($response)) {
            if (sizeof($response) > 0) {
                $response = $response[0];
            }
        }
        if (sizeof($response) > 0) {
            $jsonResponse = $response->baseResponse->exception === null
                ? json_encode(['response' => $response])
                : json_encode(['exception' => $response->baseResponse->exception->getMessage()]);

            echo "\e[1;34m" . $jsonResponse . "\e[0m" . PHP_EOL;
        } else {
            echo "\e[1;34m" . $e->getTraceAsString() . "\e[0m" . PHP_EOL;
        }

        // Mark the test as failed with expected and actual values
        $this->fail($e->getMessage());
    }

    //clear database manually as we are not using database transactions
    public function clearDatabase($params)
    {
        $user = $params['user'];
        $project = $params['project'];
        $clientId = $params['client_id'] ?? null;

        try {
            if ($user) {
                User::where('id', $user->id)->delete();
                UserProvider::where('id', $user->id)->delete();
                OAuthClient::where('user_id', $user->id)->delete();
            }
            if ($project) {
                Project::where('id', $project->id)->delete();
                ProjectRole::where('project_id', $project->id)->delete();
                ProjectStructure::where('project_id', $project->id)->delete();
                ProjectStats::where('project_id', $project->id)->delete();
                Entry::where('project_id', $project->id)->delete();
                BranchEntry::where('project_id', $project->id)->delete();
                OAuthClientProject::where('project_id', $project->id)->delete();
            }

            if ($clientId) {
                OAuthAccessToken::where('client_id', $clientId)->delete();
            }

            //also remove leftover users from other tests or failures
            User::where('email', 'LIKE', '%@example.org%')->delete();
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
        }
    }
}
