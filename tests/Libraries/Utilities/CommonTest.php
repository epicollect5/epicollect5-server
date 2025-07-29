<?php

namespace Tests\Libraries\Utilities;

use ec5\Libraries\Generators\ProjectDefinitionGenerator;
use ec5\Libraries\Utilities\Common;
use ec5\Libraries\Utilities\Generators;
use ec5\Traits\Assertions;
use Tests\TestCase;
use Mockery;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Config;
use stdClass;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class CommonTest extends TestCase
{
    use Assertions;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testFilenameShouldBeWithinSystemLength()
    {
        $systemMaxLength = 255;
        $formPrefix = 'form';
        $formIndex = rand(1, 5);
        $branchPrefix = 'branch';
        $branchIndex = rand(1, 300);

        //form name with length 50 (max)
        $formName = 'Far far away, behind the word mountains, far from.';
        //form name with length 255 (max)
        $branchName = 'Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by thei';

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($formPrefix . '-' . $formIndex, $formName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($branchPrefix . '-' . $branchIndex, $branchName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        //form name with length 50 (max)
        $formName = 'Person';

        //form name with length 255 (max)
        $branchName = 'Family Members';

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($formPrefix . '-' . $formIndex, $formName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        //generate filename (protected method, via reflection)
        $generatedFilename = Common::generateFilename($branchPrefix . '-' . $branchIndex, $branchName);
        //should return true
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
    }

    public function testGenerateFilenameWithEmptyInputs()
    {
        $systemMaxLength = 255;

        // Test with empty prefix
        $generatedFilename = Common::generateFilename('', 'Test Name');
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        $this->assertNotEmpty($generatedFilename);

        // Test with empty name
        $generatedFilename = Common::generateFilename('form-1', '');
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        $this->assertNotEmpty($generatedFilename);

        // Test with both empty
        $generatedFilename = Common::generateFilename('', '');
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        $this->assertNotEmpty($generatedFilename);
    }

    public function testGenerateFilenameWithSpecialCharacters()
    {
        $systemMaxLength = 255;
        $specialCharsName = 'Test@#$%^&*()_+{}|:"<>?[]\\;\',./ Name';

        $generatedFilename = Common::generateFilename('form-1', $specialCharsName);
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);

        // Should handle special characters gracefully by slugifying
        $this->assertIsString($generatedFilename);
        $this->assertNotEmpty($generatedFilename);
        $this->assertStringContainsString('form-1__', $generatedFilename);
    }

    public function testGenerateFilenameWithUnicodeCharacters()
    {
        $systemMaxLength = 255;
        $unicodeName = 'Test 测试 тест テスト العربية';

        $generatedFilename = Common::generateFilename('form-1', $unicodeName);
        $this->assertTrue(strlen($generatedFilename) < $systemMaxLength);
        $this->assertIsString($generatedFilename);
        $this->assertNotEmpty($generatedFilename);
    }

    public function testGenerateFilenameConsistency()
    {
        // Test that same inputs produce same outputs (deterministic behavior)
        $prefix = 'form-1';
        $name = 'Test Form Name';

        $filename1 = Common::generateFilename($prefix, $name);
        $filename2 = Common::generateFilename($prefix, $name);

        $this->assertEquals($filename1, $filename2);
    }

    public function testGenerateFilenameTruncation()
    {
        $longName = str_repeat('a', 200); // String longer than 100 chars
        $result = Common::generateFilename('prefix', $longName);

        // Should truncate to 100 chars plus prefix and separator
        $this->assertLessThan(255, strlen($result));
        $this->assertStringStartsWith('prefix__', $result);
    }

    public function testInvalidTimestamp(): void
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

    public function testValidTimestamp(): void
    {
        $validTimestamps = [
            1639862400, // An example of a valid Unix timestamp (2021-12-19 00:00:00 UTC)
            1609459200, // Another valid Unix timestamp (2021-01-01 00:00:00 UTC)
        ];

        foreach ($validTimestamps as $timestamp) {
            $this->assertTrue(Common::isValidTimestamp($timestamp));
        }
    }

    public function testTimestampEdgeCases(): void
    {
        // Test zero timestamp
        $this->assertTrue(Common::isValidTimestamp(0)); // Unix epoch

        // Test negative timestamps (valid for dates before 1970)
        $this->assertTrue(Common::isValidTimestamp(-86400)); // One day before epoch

        // Test current timestamp
        $this->assertTrue(Common::isValidTimestamp(time()));

        // Test float values
        $this->assertFalse(Common::isValidTimestamp(1639862400.5));

        // Test null
        $this->assertFalse(Common::isValidTimestamp(null));

        // Test boolean
        $this->assertFalse(Common::isValidTimestamp(true));
        $this->assertFalse(Common::isValidTimestamp(false));

        // Test array
        $this->assertFalse(Common::isValidTimestamp([]));

        // Test object
        $this->assertFalse(Common::isValidTimestamp(new stdClass()));

        // Test very large valid timestamp (year 2038 problem edge case)
        $this->assertTrue(Common::isValidTimestamp(2147483647)); // Max 32-bit signed int
    }

    public function testTimestampStringNumbers(): void
    {
        // Test string representations of valid timestamps
        $this->assertTrue(Common::isValidTimestamp('1639862400'));
        $this->assertTrue(Common::isValidTimestamp('0'));

        // Test string with leading/trailing spaces
        $this->assertFalse(Common::isValidTimestamp(' 1639862400 '));

        // Test string with plus sign
        $this->assertFalse(Common::isValidTimestamp('+1639862400'));

        // Test hexadecimal string
        $this->assertFalse(Common::isValidTimestamp('0x123'));

        // Test octal string
        $this->assertFalse(Common::isValidTimestamp('0123'));
    }

    public function testReplaceRefInStructure()
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

    public function testReplaceRefInStructureEdgeCases()
    {
        // Test with empty structure
        $result = Common::replaceRefInStructure('old-ref', 'new-ref', []);
        $this->assertIsArray($result);
        $this->assertEquals([], $result);

        // Test with null structure
        $result = Common::replaceRefInStructure('old-ref', 'new-ref', null);
        $this->assertNull($result);

        // Test with same old and new ref
        $projectDefinition = ProjectDefinitionGenerator::createProject(1);
        $existingRef = $projectDefinition['data']['project']['ref'];
        $result = Common::replaceRefInStructure($existingRef, $existingRef, $projectDefinition);
        $this->assertIsArray($result);
        $this->assertEquals($projectDefinition, $result);
    }

    public function testReplaceRefInStructureNestedArrays()
    {
        $nestedStructure = [
            'data' => [
                'project' => [
                    'ref' => 'old-ref-123',
                    'forms' => [
                        'form1' => ['ref' => 'old-ref-123'],
                        'form2' => ['ref' => 'old-ref-123']
                    ]
                ],
                'entries' => [
                    ['project_ref' => 'old-ref-123'],
                    ['project_ref' => 'old-ref-123']
                ]
            ]
        ];

        $newRef = 'new-ref-456';
        $result = Common::replaceRefInStructure('old-ref-123', $newRef, $nestedStructure);

        $this->assertIsArray($result);
        $this->assertStringNotContainsString('old-ref-123', json_encode($result));
        $this->assertStringContainsString($newRef, json_encode($result));
    }

    public function testFormatBytes()
    {
        // Test zero bytes
        $this->assertEquals('0 B', Common::formatBytes(0));

        // Test bytes
        $this->assertEquals('512 B', Common::formatBytes(512));

        // Test kilobytes
        $this->assertEquals('1 KB', Common::formatBytes(1024));
        $this->assertEquals('1.5 KB', Common::formatBytes(1536));

        // Test megabytes
        $this->assertEquals('1 MB', Common::formatBytes(1048576));

        // Test gigabytes
        $this->assertEquals('1 GB', Common::formatBytes(1073741824));

        // Test terabytes
        $this->assertEquals('1 TB', Common::formatBytes(1099511627776));

        // Test precision
        $this->assertEquals('1.33 KB', Common::formatBytes(1365, 2));
        $this->assertEquals('1.3 KB', Common::formatBytes(1365, 1));

        // Test negative bytes (should be treated as 0)
        $this->assertEquals('0 B', Common::formatBytes(-100));
    }

    public function testRoundNumber()
    {
        // Test zero
        $this->assertEquals(0, Common::roundNumber(0));

        // Test numbers less than 10
        $this->assertEquals(5, Common::roundNumber(5));
        $this->assertEquals(9, Common::roundNumber(9));

        // Test numbers less than 1000
        $this->assertEquals('≈ 100', Common::roundNumber(95));
        $this->assertEquals('≈ 500', Common::roundNumber(487));
        $this->assertEquals('≈ 1000', Common::roundNumber(999));

        // Test thousands
        $this->assertEquals('1K', Common::roundNumber(1000));
        $this->assertEquals('1.5K', Common::roundNumber(1500));

        // Test millions
        $this->assertEquals('1M', Common::roundNumber(1000000));
        $this->assertEquals('2.35M', Common::roundNumber(2350000));

        // Test billions
        $this->assertEquals('1B', Common::roundNumber(1000000000));

        // Test trillions
        $this->assertEquals('1T', Common::roundNumber(1000000000000));

        // Test custom precision
        $this->assertEquals('1.235K', Common::roundNumber(1235, 3));
    }

    public function testGetPossibleAnswers()
    {
        // Test with possible answers
        $input = [
            'possible_answers' => [
                ['answer_ref' => 'answer1'],
                ['answer_ref' => 'answer2'],
                ['answer_ref' => 'answer3']
            ]
        ];

        $result = Common::getPossibleAnswers($input);
        $this->assertEquals(['answer1', 'answer2', 'answer3'], $result);

        // Test with empty possible answers
        $input = ['possible_answers' => []];
        $result = Common::getPossibleAnswers($input);
        $this->assertEquals([], $result);

        // Test with null possible answers
        $input = ['possible_answers' => null];
        $result = Common::getPossibleAnswers($input);
        $this->assertEquals([], $result);
    }

    public function testGetTemplateHeaders()
    {
        // Mock config values
        Config::shouldReceive('get')
            ->with('epicollect.strings.bulk_uploadables')
            ->andReturn(['text' => 'text', 'number' => 'number', 'location' => 'location']);

        Config::shouldReceive('get')
            ->with('epicollect.strings.inputs_type.location')
            ->andReturn('location');

        Config::shouldReceive('get')
            ->with('epicollect.strings.inputs_type.group')
            ->andReturn('group');

        $inputs = [
            [
                'ref' => 'input1',
                'type' => 'text'
            ],
            [
                'ref' => 'input2',
                'type' => 'location'
            ]
        ];

        $selectedMapping = [
            'input1' => ['map_to' => 'field1'],
            'input2' => ['map_to' => 'field2']
        ];

        $mapTos = ['existing_field'];

        $result = Common::getTemplateHeaders($inputs, $selectedMapping, $mapTos);

        $this->assertIsArray($result);
        $this->assertContains('existing_field', $result);
        $this->assertContains('field1', $result);
        $this->assertContains('lat_field2', $result);
        $this->assertContains('long_field2', $result);
        $this->assertContains('accuracy_field2', $result);
    }

    public function testGenerateRandomHex()
    {
        // Test default length (16)
        $hex = Common::generateRandomHex();
        $this->assertEquals(16, strlen($hex));
        $this->assertTrue(ctype_xdigit($hex));

        // Test custom length
        $hex = Common::generateRandomHex(32);
        $this->assertEquals(32, strlen($hex));
        $this->assertTrue(ctype_xdigit($hex));

        // Test odd length
        $hex = Common::generateRandomHex(7);
        $this->assertEquals(7, strlen($hex));
        $this->assertTrue(ctype_xdigit($hex));

        // Test zero length
        $hex = Common::generateRandomHex(0);
        $this->assertEquals(0, strlen($hex));

        // Test uniqueness
        $hex1 = Common::generateRandomHex(16);
        $hex2 = Common::generateRandomHex(16);
        $this->assertNotEquals($hex1, $hex2);
    }

    public function testGetMonthName()
    {
        $this->assertEquals('Jan', Common::getMonthName(1));
        $this->assertEquals('Feb', Common::getMonthName(2));
        $this->assertEquals('Mar', Common::getMonthName(3));
        $this->assertEquals('Apr', Common::getMonthName(4));
        $this->assertEquals('May', Common::getMonthName(5));
        $this->assertEquals('Jun', Common::getMonthName(6));
        $this->assertEquals('Jul', Common::getMonthName(7));
        $this->assertEquals('Aug', Common::getMonthName(8));
        $this->assertEquals('Sep', Common::getMonthName(9));
        $this->assertEquals('Oct', Common::getMonthName(10));
        $this->assertEquals('Nov', Common::getMonthName(11));
        $this->assertEquals('Dec', Common::getMonthName(12));
    }

    public function testConfigWithParams()
    {
        // Mock config
        Config::shouldReceive('get')
            ->with('test.message')
            ->andReturn('Hello :name, welcome to :site');

        $result = Common::configWithParams('test.message', [
            'name' => 'John',
            'site' => 'Epicollect5'
        ]);

        $this->assertEquals('Hello John, welcome to Epicollect5', $result);

        // Test with no parameters
        Config::shouldReceive('get')
            ->with('test.simple')
            ->andReturn('Simple message');

        $result = Common::configWithParams('test.simple');
        $this->assertEquals('Simple message', $result);
    }

    public function testGetDownloadEntriesCookie()
    {
        Config::shouldReceive('get')
            ->with('epicollect.setup.cookies.download_entries')
            ->andReturn('download_entries_cookie');

        $cookie = Common::getDownloadEntriesCookie('test_value');

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
        $this->assertEquals('download_entries_cookie', $cookie->getName());
        $this->assertEquals('test_value', $cookie->getValue());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertEquals('Lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testGetLocationInputRefs()
    {
        Config::shouldReceive('get')
            ->with('epicollect.strings.inputs_type.location')
            ->andReturn('location');

        Config::shouldReceive('get')
            ->with('epicollect.strings.inputs_type.group')
            ->andReturn('group');

        $projectDefinition = [
            'data' => [
                'project' => [
                    'forms' => [
                        [
                            'inputs' => [
                                ['ref' => 'input1', 'type' => 'text'],
                                ['ref' => 'input2', 'type' => 'location'],
                                [
                                    'ref' => 'input3',
                                    'type' => 'group',
                                    'group' => [
                                        ['ref' => 'group_input1', 'type' => 'location'],
                                        ['ref' => 'group_input2', 'type' => 'text']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = Common::getLocationInputRefs($projectDefinition, 0);

        $this->assertIsArray($result);
        $this->assertContains('input2', $result);
        $this->assertContains('group_input1', $result);
        $this->assertNotContains('input1', $result);
        $this->assertNotContains('group_input2', $result);
    }

    public function testGetBranchRefs()
    {
        Config::shouldReceive('get')
            ->with('epicollect.strings.inputs_type.branch')
            ->andReturn('branch');

        Config::shouldReceive('get')
            ->with('epicollect.strings.inputs_type.group')
            ->andReturn('group');

        $projectDefinition = [
            'data' => [
                'project' => [
                    'forms' => [
                        [
                            'inputs' => [
                                ['ref' => 'input1', 'type' => 'text'],
                                ['ref' => 'input2', 'type' => 'branch'],
                                [
                                    'ref' => 'input3',
                                    'type' => 'group',
                                    'group' => [
                                        ['ref' => 'group_input1', 'type' => 'branch'],
                                        ['ref' => 'group_input2', 'type' => 'text']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = Common::getBranchRefs($projectDefinition, 0);

        $this->assertIsArray($result);
        $this->assertContains('input2', $result);
        $this->assertContains('group_input1', $result);
        $this->assertNotContains('input1', $result);
        $this->assertNotContains('group_input2', $result);
    }

    /**
     * Test data provider for various invalid inputs to test robustness
     */
    public function invalidInputProvider(): array
    {
        return [
            'null' => [null],
            'boolean_true' => [true],
            'boolean_false' => [false],
            'empty_array' => [[]],
            'object' => [new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testMethodsHandleInvalidInputsGracefully($invalidInput)
    {
        // Test that methods don't throw exceptions with invalid inputs
        try {
            Common::isValidTimestamp($invalidInput);
            $this->assertTrue(true); // If no exception, test passes
        } catch (\Exception $e) {
            $this->fail('isValidTimestamp should handle invalid inputs gracefully: ' . $e->getMessage());
        }

        try {
            Common::replaceRefInStructure('old', 'new', $invalidInput);
            $this->assertTrue(true); // If no exception, test passes
        } catch (\Exception $e) {
            $this->fail('replaceRefInStructure should handle invalid inputs gracefully: ' . $e->getMessage());
        }
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}