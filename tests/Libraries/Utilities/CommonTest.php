<?php

namespace Tests\Libraries\Utilities;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Traits\Assertions;
use Tests\TestCase;

class CommonTest extends TestCase
{
    use Assertions;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_filename_should_be_within_system_length()
    {
        $systemMaxLength = 255;
        $form_prefix = 'form';
        $form_index = rand(1, 5);
        $branch_prefix = 'branch';
        $branch_index = rand(1, 300);

        //form name with length 50 (max)
        $formName = 'Far far away, behind the word mountains, far from.';

        //form name with length 255 (max)
        $branchName = 'Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by thei';

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($form_prefix . '-' . $form_index, $formName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($branch_prefix . '-' . $branch_index, $branchName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        //form name with length 50 (max)
        $formName = 'Person';

        //form name with length 255 (max)
        $branchName = 'Family Members';

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($form_prefix . '-' . $form_index, $formName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($branch_prefix . '-' . $branch_index, $branchName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

    }

    public function test_invalid_timestamp(): void
    {
        $invalidTimestamps = [
            '2021-12-19', // Not a Unix timestamp
            'abc',        // Not a numeric value
            PHP_INT_MAX + 1, // Greater than PHP_INT_MAX
            PHP_INT_MIN - 1, // Less than ~PHP_INT_MAX
        ];

        foreach ($invalidTimestamps as $timestamp) {
            $this->assertFalse(Common::isValidTimestamp($timestamp));
        }
    }

    public function test_valid_timestamp(): void
    {
        $validTimestamps = [
            1639862400, // An example of a valid Unix timestamp (2021-12-19 00:00:00 UTC)
            1609459200, // Another valid Unix timestamp (2021-01-01 00:00:00 UTC)
        ];

        foreach ($validTimestamps as $timestamp) {
            $this->assertTrue(Common::isValidTimestamp($timestamp));
        }
    }

    public function test_replace_ref_in_structure()
    {
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $newRef = Generators::projectRef();
        $existingRef = $projectDefinition['data']['project']['ref'];

        $result = Common::replaceRefInStructure($existingRef, $newRef, $projectDefinition);
        // Assert that the result is an array
        $this->assertIsArray($result);
        // Perform additional assertions
        // For example, you can check if the old ref is replaced with the new ref
        $this->assertStringNotContainsString($existingRef, json_encode($result));
        $this->assertStringContainsString($newRef, json_encode($result));
    }
}
