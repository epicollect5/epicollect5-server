<?php

namespace Tests\Project\Inputs;

use ec5\Http\Validation\Project\RuleInput;

class DecimalInputTest extends GeneralInputTest
{

    /**
     * @var \ec5\Models\Projects\Project
     */
    protected $project;
    /**
     * @var RuleInput
     */
    protected $validator;

    /**
     * @var array
     */
    protected $inputDetails;

    protected $parentRef;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->type = 'decimal';
        $this->reset();
    }

    /**
     *
     */
    public function testMinMax()
    {

        // Integers allowed
        $this->inputDetails['min'] = 1;
        $this->inputDetails['max'] = 2;
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Decimals allowed (should this only be for type decimal?)
        $this->inputDetails['min'] = 1.1;
        $this->inputDetails['max'] = 2.2;
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Max should be greater than min
        $this->inputDetails['min'] = 3;
        $this->inputDetails['max'] = 2;
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Letters not allowed
        $this->inputDetails['min'] = 'xx';
        $this->inputDetails['max'] = 'xx';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid chars not allowed
        $this->inputDetails['min'] = '&%';
        $this->inputDetails['max'] = '£@';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->reset();
    }

    public function testDefault()
    {
        // Valid default
        $this->inputDetails['default'] = '300';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Invalid Default
        $this->inputDetails['default'] = 'xxx';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Default with min/max pass
        $this->inputDetails['min'] = '1';
        $this->inputDetails['max'] = '10';
        $this->inputDetails['default'] = '5';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertFalse($this->validator->hasErrors());
        $this->validator->resetErrors();

        // Default with min/max fail
        $this->inputDetails['min'] = '1';
        $this->inputDetails['max'] = '10';
        $this->inputDetails['default'] = '20';
        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);
        $this->assertTrue($this->validator->hasErrors());
        $this->validator->resetErrors();

        $this->reset();
    }

}
