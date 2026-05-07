<?php

namespace Tests;

use Countable;
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
use Faker\Factory as Faker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Log;
use Throwable;

class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected string $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     */
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Logs detailed error information for a failing test.
     *
     * This method outputs the exception message along with any available expected and actual values
     * obtained from a comparison failure. It further processes the provided response to display
     * additional context—such as a JSON representation of response details—if applicable. Finally,
     * it marks the test as failed with a formatted error message that includes the relative file path.
     *
     * @param mixed $e The exception encountered during the test.
     * @param mixed     $response Additional context for the error, which may include a test response
     *                            object with diagnostic details.
     */
    public function logTestError(mixed $e, mixed $response): void
    {
        $expected = '';
        $actual = '';

        echo "\e[0;31m" . $e->getMessage() . "\e[0m" . PHP_EOL;

        // Get the expected and actual values from the ComparisonFailure object
        if (method_exists($e, 'getComparisonFailure') && $e->getComparisonFailure() !== null) {
            $expected = print_r($e->getComparisonFailure()->getExpected(), true) . PHP_EOL;
            $actual = print_r($e->getComparisonFailure()->getActual(), true) . PHP_EOL;
        }

        echo 'Expected: ', $expected;
        echo 'Actual: ' . $actual ?? PHP_EOL;

        // Ensure $response is an array or a Countable object before using sizeof()
        if (is_array($response) || $response instanceof Countable) {
            if (sizeof($response) > 0) {
                if (is_array($response)) {
                    $response = $response[0];
                }
            }
        }

        if (is_array($response) || is_object($response)) {
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

        // Log error for failed assertion
        $this->fail("Error in $filePath:\n\n{$e->getMessage()}");
    }

    /**
     * Clears test-related database records manually.
     *
     * This method deletes entries from various tables to remove test artifacts. It targets users whose emails match
     * specific patterns, as well as records related to a provided user, project, or client ID. The deletions include
     * test users, associated user providers, OAuth clients, projects, project roles, project structures, stats, entries,
     * branch entries, and OAuth tokens.
     *
     * @param array $params Associative array that may include:
     *   - 'user': (optional) A user object whose records in users, user providers, and OAuth clients will be deleted.
     *   - 'project': (optional) A project object whose records in projects, project roles, project structures, stats,
     *                entries, branch entries, and OAuth client projects will be deleted.
     *   - 'client_id': (optional) A client ID used to delete corresponding OAuth access tokens.
     */
    public function clearDatabase(array $params): void
    {
        $user = $params['user'] ?? null;
        $project = $params['project'] ?? null;
        $clientId = $params['client_id'] ?? null;

        try {
            $testProjectIds = $this->testProjectIdsForCleanup($project);
            $testUserIds = $this->testUserIdsForCleanup($user);

            if (!empty($testProjectIds)) {
                $testProjectClientIds = OAuthClientProject::whereIn(
                    'project_id',
                    $testProjectIds
                )->pluck('client_id')->filter()->all();

                if (!empty($testProjectClientIds)) {
                    OAuthAccessToken::whereIn(
                        'client_id',
                        $testProjectClientIds
                    )->delete();
                }

                ProjectRole::whereIn('project_id', $testProjectIds)->delete();
                ProjectStructure::whereIn('project_id', $testProjectIds)->delete();
                ProjectStats::whereIn('project_id', $testProjectIds)->delete();
                Entry::whereIn('project_id', $testProjectIds)->delete();
                BranchEntry::whereIn('project_id', $testProjectIds)->delete();
                OAuthClientProject::whereIn('project_id', $testProjectIds)->delete();
                Project::whereIn('id', $testProjectIds)->delete();

                if (!empty($testProjectClientIds)) {
                    OAuthClient::whereIn('id', $testProjectClientIds)->delete();
                }
            }

            $this->clearUsersByIds($testUserIds);

            if ($clientId) {
                OAuthAccessToken::where('client_id', $clientId)->delete();
            }
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Gets known test project IDs that should be removed during cleanup.
     *
     * @param Project|null $project Explicit project passed by a test.
     *
     * @return array
     */
    private function testProjectIdsForCleanup(?Project $project): array
    {
        $projectIds = [];

        if ($project) {
            $projectIds[] = $project->id;
        }

        $testProjectSlugs = $this->testProjectSlugs();

        if (!empty($testProjectSlugs)) {
            $projectIds = array_merge(
                $projectIds,
                Project::whereIn('slug', $testProjectSlugs)->pluck('id')->all()
            );
        }

        return array_values(array_unique(array_filter($projectIds)));
    }

    /**
     * Gets known test user IDs that should be removed during cleanup.
     *
     * @param User|null $user Explicit user passed by a test.
     *
     * @return array
     */
    private function testUserIdsForCleanup(?User $user): array
    {
        $userIds = User::where('email', 'like', '%@example.com')
            ->orWhere('email', 'like', '%@example.net')
            ->orWhere('email', 'like', '%@example.org')
            ->orWhere('email', 'random@unit.tests')
            ->pluck('id')
            ->all();

        if ($user) {
            $userIds[] = $user->id;
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    /**
     * Clears users and their OAuth artifacts in dependency order.
     *
     * @param array $userIds User IDs to remove.
     *
     * @return void
     */
    private function clearUsersByIds(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $clientIds = OAuthClient::whereIn('user_id', $userIds)->pluck('id')->all();

        if (!empty($clientIds)) {
            OAuthAccessToken::whereIn('client_id', $clientIds)->delete();
            OAuthClientProject::whereIn('client_id', $clientIds)->delete();
            OAuthClient::whereIn('id', $clientIds)->delete();
        }

        UserProvider::whereIn('user_id', $userIds)->delete();
        User::whereIn('id', $userIds)->delete();
    }

    /**
     * Gets configured test project slugs.
     *
     * @return array
     */
    private function testProjectSlugs(): array
    {
        $cleanupProjectSlugs = config('testing.cleanup_project_slugs');

        if (is_array($cleanupProjectSlugs)) {
            return array_values(array_unique(array_filter(
                $cleanupProjectSlugs,
                fn ($slug) => is_string($slug)
            )));
        }

        $slugs = [];
        $testingConfig = config('testing');

        if (!is_array($testingConfig)) {
            return [];
        }

        array_walk_recursive(
            $testingConfig,
            function ($value, $key) use (&$slugs) {
                if ($key === 'slug' && is_string($value)) {
                    $slugs[] = $value;
                }
            }
        );

        return array_values(array_unique($slugs));
    }

    protected function tearDown(): void
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

    public static function multipleRunProvider(): array
    {
        // Define how many times you want to run the test
        $runs = 1;
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

    protected function generateStringOfLength(int $length): string
    {
        // Define the characters that can be used in the string
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public static function outOfBoundsIntDataProvider(): array
    {
        $tests = [];

        for ($i = 0; $i < 100; $i++) {
            $min = rand(-100, 100);
            $max = rand($min + 1, $min + 200);

            $tests[] = [$min, $max, $min - rand(1, 20)];  // too low
            $tests[] = [$min, $max, $max + rand(1, 20)];  // too high
        }

        return $tests;
    }

    public static function outOfBoundsFloatDataProvider(): array
    {
        $tests = [];

        for ($i = 0; $i < 100; $i++) {
            $min = round(mt_rand(-1000, 1000) / 10, 5);  // e.g., -45.3
            $max = round($min + mt_rand(1, 200) / 10, 5); // e.g., 55.6

            $tooLow = round($min - mt_rand(1, 20) / 10, 5);
            $tooHigh = round($max + mt_rand(1, 20) / 10, 5);

            $tests[] = [$min, $max, $tooLow];
            $tests[] = [$min, $max, $tooHigh];
        }

        return $tests;
    }

    public function overrideStorageDriver($diskOverride): void
    {
        if ($diskOverride === 'local') {
            config([
                'filesystems.default' => $diskOverride,
                'filesystems.disks.photo.driver' => $diskOverride,
                'filesystems.disks.photo.root' => storage_path('app/entries/photo/entry_original'),
                'filesystems.disks.project.driver' => $diskOverride,
                'filesystems.disks.project.root' => storage_path('app/projects/project_thumb'),
                'filesystems.disks.audio.driver' => $diskOverride,
                'filesystems.disks.audio.root' => storage_path('app/entries/audio'),
                'filesystems.disks.video.driver' => $diskOverride,
                'filesystems.disks.video.root' => storage_path('app/entries/video'),
            ]);
        } elseif ($diskOverride === 's3') {
            config([
                'filesystems.default' => $diskOverride,
                'filesystems.disks.photo.driver' => $diskOverride,
                'filesystems.disks.photo.root' => 'app/entries/photo',
                'filesystems.disks.project.driver' => $diskOverride,
                'filesystems.disks.project.root' => 'app/projects',
                'filesystems.disks.audio.driver' => $diskOverride,
                'filesystems.disks.audio.root' => 'app/entries/audio',
                'filesystems.disks.video.driver' => $diskOverride,
                'filesystems.disks.video.root' => 'app/entries/video',
            ]);
        }
    }

}
