<?php

namespace Tests\Http\Validation\Entries;

use Tests\TestCase;
use ec5\Http\Validation\Project\RuleEntryLimits;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RuleEntryLimitsTest extends TestCase
{
    use DatabaseTransactions;

    private $ruleEntryLimits;
    private $payload;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleEntryLimits = new ruleEntryLimits();
        $this->payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '1',
                'limitTo' => '20',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                'branchRef' => ''
            ],
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee_656f585d4b01c' => [
                'limit' => '1',
                'limitTo' => '20',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                'branchRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee_656f585d4b01c'
            ],
            '546c94324ee043b0a1d56c2fefd6c7cc_656f48ab6c924' => [
                'limit' => '1',
                'limitTo' => '20',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_656f48ab6c924',
                'branchRef' => ''
            ]
        ];
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->ruleEntryLimits->resetErrors();
    }

    public function test_valid_payload()
    {
        foreach ($this->payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }
        $this->assertFalse($this->ruleEntryLimits->hasErrors());
    }

    public function test_should_fail_limit_not_integer()
    {
        $payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '?',
                'limitTo' => '20',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                'branchRef' => ''
            ]
        ];
        foreach ($payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }

        $this->assertTrue($this->ruleEntryLimits->hasErrors());
        $this->assertEquals(
            [
                'limit' => ['ec5_27']
            ],
            $this->ruleEntryLimits->errors
        );
    }

    public function test_should_fail_limitTo_not_integer()
    {
        $payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '5',
                'limitTo' => 'P',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                'branchRef' => ''
            ]
        ];
        foreach ($payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }

        $this->assertTrue($this->ruleEntryLimits->hasErrors());
        $this->assertEquals(
            [
                'limitTo' => ['ec5_27']
            ],
            $this->ruleEntryLimits->errors
        );
    }

    public function test_should_fail_limitTo_over_range()
    {
        $payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '5',
                'limitTo' => '100000',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                'branchRef' => ''
            ]
        ];
        foreach ($payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }

        $this->assertTrue($this->ruleEntryLimits->hasErrors());
        $this->assertEquals(
            [
                'limitTo' => ['ec5_335']
            ],
            $this->ruleEntryLimits->errors
        );
    }

    public function test_should_fail_limitTo_negative()
    {
        $payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '5',
                'limitTo' => '-9',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                'branchRef' => ''
            ]
        ];
        foreach ($payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }

        $this->assertTrue($this->ruleEntryLimits->hasErrors());
        $this->assertEquals(
            [
                'limitTo' => ['The limit to must be at least 0.']
            ],
            $this->ruleEntryLimits->errors
        );
    }

    public function test_should_fail_missing_formRef()
    {
        $payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '5',
                'limitTo' => '7',
                'formRef' => null,
                'branchRef' => ''
            ]
        ];
        foreach ($payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }

        $this->assertTrue($this->ruleEntryLimits->hasErrors());
        $this->assertEquals(
            [
                'formRef' => ['ec5_21']
            ],
            $this->ruleEntryLimits->errors
        );
    }

    public function test_should_fail_missing_branchRef_key()
    {
        $payload = [
            '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee' => [
                'limit' => '5',
                'limitTo' => '7',
                'formRef' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
            ]
        ];
        foreach ($payload as $data) {
            $this->ruleEntryLimits->validate($data);
        }

        $this->assertTrue($this->ruleEntryLimits->hasErrors());
        $this->assertEquals(
            [
                'branchRef' => ['The branch ref field must be present.']
            ],
            $this->ruleEntryLimits->errors
        );
    }
}