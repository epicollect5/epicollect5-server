<?php

namespace Tests\Http\Validation\Project\RuleInput;

class NumericConstraintsNullTest extends GeneralTest
{
    public function test_integer_null_min_max_are_treated_as_unset()
    {
        $this->type = 'integer';
        $this->reset();

        $this->inputDetails['min'] = null;
        $this->inputDetails['max'] = null;

        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);

        $this->assertFalse($this->validator->hasErrors());
    }

    public function test_decimal_null_min_max_are_treated_as_unset()
    {
        $this->type = 'decimal';
        $this->reset();

        $this->inputDetails['min'] = null;
        $this->inputDetails['max'] = null;

        $this->validator->validate($this->inputDetails);
        $this->validator->additionalChecks($this->parentRef);

        $this->assertFalse($this->validator->hasErrors());
    }
}
