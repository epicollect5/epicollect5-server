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
use Faker\Factory as Faker;
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
            if (isset($response->baseResponse)) {
                $jsonResponse = $response->baseResponse->exception === null
                    ? json_encode(['response' => $response])
                    : json_encode(['exception' => $response->baseResponse->exception->getMessage()]);

                echo "\e[1;34m" . $jsonResponse . "\e[0m" . PHP_EOL;
            } else {
                echo "\e[1;34m" . $e->getMessage() . "\e[0m" . PHP_EOL;
            }
        } else {
            echo "\e[1;34m" . $e->getTraceAsString() . "\e[0m" . PHP_EOL;
        }

        // Mark the test as failed with expected and actual values
        $filePath = str_replace(base_path(), '', $e->getFile());
        // Get the full stack trace as a string
        $stackTrace = $e->getTraceAsString();
        // Convert the stack trace into an array of lines
        $stackTraceLines = explode("\n", $stackTrace);
        //log error for failed assertion
        $this->fail("Error in {$stackTraceLines}:\n\n{$e->getMessage()}");
    }

    //clear database manually as we are not using database transactions
    public function clearDatabase($params)
    {
        $user = $params['user'] ?? null;
        $project = $params['project'] ?? null;
        $clientId = $params['client_id'] ?? null;

        try {

            // Delete users with an email that ends with '@example.com'
            User::where('email', 'like', '%@example.com')->delete();
            User::where('email', 'like', '%random@unit.tests%')->delete();

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

    protected function tearDown()
    {
//        // Remove properties defined during the test
//        $refl = new \ReflectionObject($this);
//        foreach ($refl->getProperties() as $prop) {
//            if (!$prop->isStatic() && 0 !== strpos($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
//                $prop->setAccessible(true);
//                $prop->setValue($this, null);
//            }
//        }

        // Clean up your resources here
        parent::tearDown();
        gc_collect_cycles(); // Invoke garbage collection
    }

    public function multipleRunProvider(): array
    {
        // Define how many times you want to run the test
        $runs = 2;
        $testCases = [];

        for ($i = 0; $i < $runs; $i++) {
            $testCases[] = [$i]; // Provide index or any other data you need
        }

        return $testCases;
    }

    protected function getModifiedMapping($mapping): array
    {
        $faker = Faker::create();
        //regex to generate a valid map_to (avoiding '0' as that could cause errors)
        //also, we use 10 even though it could accept 20 chars.
        //ec5_ is prepended below
        $regex = '[A-Za-z1-9\_]{1,10}';

        foreach ($mapping['forms'] as $formRef => $inputRefs) {
            // Check if the value is an array
            if (is_array($inputRefs)) {
                // Loop through the nested array
                foreach ($inputRefs as $inputRef => $input) {
                    // Check if "map_to" key exists in the nested array
                    if (isset($input['map_to'])) {
                        //update original map_to to a valid value
                        $mapping['forms'][$formRef][$inputRef]['map_to'] = 'ec5_' . $faker->unique()->regexify($regex);

                        //update possible answers map_to, if any
                        $possibleAnswers = $mapping['forms'][$formRef][$inputRef]['possible_answers'];
                        if (sizeof($possibleAnswers) > 0) {
                            foreach ($possibleAnswers as $answerRef => $possibleAnswer) {
                                $mapTo = 'ec5_' . $faker->unique()->regexify($regex);
                                $mapping['forms'][$formRef][$inputRef]['possible_answers'][$answerRef]['map_to'] = $mapTo;
                            }
                        }
                    }

                    //if is branch, update the branch questions map_to
                    if (sizeof($input['branch']) > 0) {
                        foreach ($input['branch'] as $branchRef => $branchInput) {
                            $mapping['forms'][$formRef][$inputRef]['branch'][$branchRef]['map_to'] = 'ec5_' . $faker->unique()->regexify($regex);
                            $possibleAnswers = $mapping['forms'][$formRef][$inputRef]['branch'][$branchRef]['possible_answers'];
                            if (sizeof($possibleAnswers) > 0) {
                                foreach ($possibleAnswers as $answerRef => $possibleAnswer) {
                                    $mapTo = 'ec5_' . $faker->unique()->regexify($regex);
                                    $mapping['forms'][$formRef][$inputRef]['branch'][$branchRef]['possible_answers'][$answerRef]['map_to'] = $mapTo;
                                }
                            }
                        }
                    }

                    //if is group, update the group questions map_to
                    if (sizeof($input['group']) > 0) {
                        foreach ($input['group'] as $groupRef => $groupInput) {
                            $mapping['forms'][$formRef][$inputRef]['group'][$groupRef]['map_to'] = 'ec5_' . $faker->unique()->regexify($regex);
                            $possibleAnswers = $mapping['forms'][$formRef][$inputRef]['group'][$groupRef]['possible_answers'];
                            if (sizeof($possibleAnswers) > 0) {
                                foreach ($possibleAnswers as $answerRef => $possibleAnswer) {
                                    $mapTo = 'ec5_' . $faker->unique()->regexify($regex);
                                    $mapping['forms'][$formRef][$inputRef]['group'][$groupRef]['possible_answers'][$answerRef]['map_to'] = $mapTo;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $mapping;
    }
}
