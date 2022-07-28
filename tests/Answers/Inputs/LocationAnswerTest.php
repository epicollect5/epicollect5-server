<?php

namespace Tests\Answers\Inputs;

use ec5\Http\Validation\Entries\Upload\InputRules\RuleLocationInput as RuleLocationInput;
use ec5\Models\Entries\EntryStructure;
use Tests\TestCase;
use Config;

class LocationAnswerTest extends TestCase
{

    /**
     * @var \ec5\Models\Projects\Project
     */
    protected $project;

    /**
     * @var \ec5\Models\Entries\EntryStructure
     */
    protected $entryStructure;
    /**
     * @var RuleLocationInput
     */
    protected $validator;

    /**
     * @var array
     */
    protected $inputDetails;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();

        $this->validator = new RuleLocationInput();

        $this->inputDetails = [
            'ref' => 'xxx',
            'type' => 'location'
        ];

        $this->project = \Mockery::mock('\ec5\Models\Projects\Project');


        //create a fake EntryStructure instance.
        //I cannot mock it as the validation classes also generates objects (lol...shall we call it the "Validactory" pattern?)
        //This is what happens when you get a crucial part of an application done by a moron.
        $this->entryStructure = new EntryStructure();

        $entryData = Config::get('ec5ProjectStructures.entry_data');
        $entryData['id'] = 'xxx';
        $entryData['type'] = 'entry';
        $entryData[Config::get('ec5Strings.entry_types.entry')] = [
            'type' => 'entry',
            'name' => 'xxx',
            'input_ref' => 'xxx'
        ];

        //fwrite(STDOUT, print_r($entryData) . "\n");

        $this->entryStructure->createStructure($entryData);
    }

    /**
     *
     */
    public function testIsValidPositiveLatitude()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid positive latitude
        $answer = [
                'latitude' => 77,
                'longitude' => 0,
                'accuracy' => 4
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function testIsValidPositiveLongitude()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid positive latitude
        $answer = [
            'latitude' => 77,
            'longitude' => 156,
            'accuracy' => 4
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    /**
     * New Zealand
     */
    public function testIsValidNegativeLatitude()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid positive latitude
        $answer = [
                'latitude' => -42.538734,
                'longitude' => 172.485579,
                'accuracy' => 9

            ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    /**
     * Hawaii
     */
    public function testIsValidNegativeLongitude()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid positive latitude
        $answer = [
            'latitude' => 19.884310,
            'longitude' => 159.414515,
            'accuracy' => 9

        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function testInvalidLatitudeShouldNotPassThrough()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Invalid latitude
        $answer = [
                'latitude' => -89777,
                'longitude' => 0,
                'accuracy' => 4
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid latitude
        $answer = [
            'latitude' => 98,
            'longitude' => -1,
            'accuracy' => 30
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    public function testInvalidLongitudeShouldNotPassThrough()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Invalid longitude
        $answer = [
            'latitude' => 65.987634,
            'longitude' => -4736584,
            'accuracy' => 4
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid longitude
        $answer = [
            'latitude' => 65.987634,
            'longitude' => 555,
            'accuracy' => 4
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();
    }

    //
    public function testIsEmptyLocation()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid answer (empty, user did not answer)
        $answer = [
                'latitude' => '',
                'longitude' => '',
                'accuracy' => ''
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());

        $this->validator->resetErrors();
    }

    public function testIsNullLatitude()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid answer (empty, user did not answer)
        $answer = [
            'latitude' => null,
            'longitude' => 123,
            'accuracy' => 4
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->resetErrors();
    }

    public function testIsNullLongitude()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid answer (empty, user did not answer)
        $answer = [
            'latitude' => 34,
            'longitude' => null,
            'accuracy' => 8
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->resetErrors();
    }

    public function testIsNullAccuracy()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid answer (empty, user did not answer)
        $answer = [
            'latitude' => 34,
            'longitude' => 80,
            'accuracy' => null
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->resetErrors();
    }

    public function testIsIvalidAccuracy()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        //negative accuracy
        $answer = [
            'latitude' => 34,
            'longitude' => 80,
            'accuracy' => -5
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        //wrong comma
        $answer = [
            'latitude' => 34,
            'longitude' => 80,
            'accuracy' => 10,6
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        //wrong missing value
        $answer = [
            'latitude' => 34,
            'longitude' => 80,
            'accuracy' => ''
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        //wrong random value
        $answer = [
            'latitude' => 34,
            'longitude' => 80,
            'accuracy' => '#'
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

    }

    /**
     * Test if latitude and longitude do not pass oig they are not numeric
     * expect: error thrown
     */
    public function testHasNotNumericCoords()
    {
        fwrite(STDOUT, __FUNCTION__ . "\n");
        // Valid answer (empty, user did not answer)
        $this->validator->resetErrors();
        $answer = [
            'latitude' => 'xx',
            'longitude' => 'zz',
            'accuracy' => 7//MUST be numeric
        ];

        $this->inputDetails['answer'] = $answer;
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->resetErrors();
    }
}
