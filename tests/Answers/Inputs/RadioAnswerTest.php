<?php

namespace Tests\Answers\Inputs;

use ec5\Http\Validation\Entries\Upload\InputRules\RuleRadioInput;
use ec5\Models\Entries\EntryStructure;
use Tests\TestCase;
use Config;

class RadioAnswerTest extends TestCase
{

    /**
     * @var \ec5\Models\Projects\Project
     */
    protected $project;
    protected $validator;
    protected $entryStructure;
    protected $inputDetails;
    protected $type;

    public function setUp()
    {
        parent::setUp();

        $this->validator = new RuleRadioInput();

        $this->reset();

        $this->type = 'radio';

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
     * Reset test conditions
     */
    private function reset()
    {
        $this->inputDetails = [
            'ref' => 'xxx',
            'is_required' => false,
            'datetime_format' => '',
            'type' => $this->type,
            'possible_answers' => [
            ]
        ];
    }

    public function testIsRadioValid()
    {

        $this->inputDetails['possible_answers'] = [
            [
                'answer' => 'ciao',
                'answer_ref' => '5f24230ba0912',
            ],
            [
                'answer' => 'addio',
                'answer_ref' => '5ece4797eaf5e'
            ]
        ];


        $answer = '5f24230ba0912';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();


        $answer = '5ece4797eaf5e';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

    }

    public function testIsRadioInvalid()
    {

        $this->inputDetails['possible_answers'] = [
            [
                'answer' => 'ciao',
                'answer_ref' => '5f24230ba0912',
            ],
            [
                'answer' => 'addio',
                'answer_ref' => '5ece4797eaf5e'
            ]
        ];


        //invalid (bulk upload)
        $answer = null;

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        //only single answer is accepted
        $answer = '5ece4797eaf5e, 5f24230ba0912';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        //answer_ref does not exist, trigger error
        $answer = '5f24230ba0913';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->resetErrors();

        //invald answer
        $answer = 'zxczczxc';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        //empty answer does not trigger error
        $answer = '';

        $this->validator->setRules($this->inputDetails, $answer, $this->project);

        $data = [$this->inputDetails['ref'] => $answer];
        $this->validator->validate($data);
        $response = $this->validator->additionalChecks($this->inputDetails, $answer, $this->project, $this->entryStructure);
        $this->assertFalse($this->validator->hasErrors());
        $this->assertEquals('', $response);
        $this->validator->resetErrors();
    }
}