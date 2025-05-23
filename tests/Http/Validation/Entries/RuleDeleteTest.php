<?php

namespace Tests\Http\Validation\Entries;

use Tests\TestCase;
use ec5\Http\Validation\Entries\Delete\RuleDelete;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RuleDeleteTest extends TestCase
{
    use DatabaseTransactions;

    private RuleDelete $ruleDelete;
    private array $payload;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleDelete = new RuleDelete();
        $this->payload = [
            'type' => 'delete',
            'id' => '42fd84b0-99e7-11ee-92b0-758caf1d828e',
            'attributes' => [
                'form' => [
                    'ref' => '546c94324ee043b0a1d56c2fefd6c7cc_6569e390cd7ee',
                    'type' => 'hierarchy'
                ]
            ],
            'relationships' => [
                'branch' => [
                ],
                'parent' => [
                ]
            ],
            'delete' => [
                'entry_uuid' => '42fd84b0-99e7-11ee-92b0-758caf1d828e'
            ]
        ];
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleDelete->resetErrors();
    }

    public function test_valid_payload()
    {
        $this->ruleDelete->validate($this->payload);
        $this->assertFalse($this->ruleDelete->hasErrors());
    }

    public function test_should_fail_if_type_wrong()
    {
        $this->payload['type'] = 'wrong';

        $this->ruleDelete->validate($this->payload);

        $this->assertTrue($this->ruleDelete->hasErrors());
        $this->assertEquals(
            [
                'type' => ['ec5_29']
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_id_wrong()
    {
        $this->payload['id'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'id' => [
                    'ec5_21'
                ],
                'delete.entry_uuid' => [
                    'The delete.entry uuid and id must match.'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_attributes_wrong()
    {
        $this->payload['attributes'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'attributes' => [
                    'ec5_21'
                ],
                'attributes.form.ref' => [
                    'ec5_21'
                ],
                'attributes.form.type' => [
                    'ec5_21'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_attributes_form_wrong()
    {
        $this->payload['attributes']['form'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'attributes.form.ref' => [
                    'ec5_21'
                ],
                'attributes.form.type' => [
                    'ec5_21'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_attributes_form_ref_wrong()
    {
        $this->payload['attributes']['form']['ref'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'attributes.form.ref' => [
                    'ec5_21'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_attributes_form_type_wrong()
    {
        $this->payload['attributes']['form']['type'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'attributes.form.type' => [
                    'ec5_21'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_relationships_wrong()
    {
        $this->payload['relationships'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'relationships' => [
                    'ec5_21'
                ],
                'relationships.branch' => [
                    'ec5_21'
                ],
                'relationships.parent' => [
                    'ec5_21'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_archive_key_missing()
    {
        $this->payload['delete'] = null;
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());


        $this->assertEquals(
            [
                'delete' => [
                    'ec5_21'
                ],
                'delete.entry_uuid' => [
                    'ec5_21'
                ]
            ],
            $this->ruleDelete->errors
        );
    }

    public function test_should_fail_if_delete_entry_uuid_not_matching()
    {
        $this->payload['delete']['entry_uuid'] = 'mismatch';
        $this->ruleDelete->validate($this->payload);
        $this->assertTrue($this->ruleDelete->hasErrors());

        $this->assertEquals(
            [
                'delete.entry_uuid' => [
                    'ec5_43',
                    'The delete.entry uuid and id must match.'
                ]
            ],
            $this->ruleDelete->errors
        );
    }
}
