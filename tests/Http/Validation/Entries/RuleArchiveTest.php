<?php

namespace Tests\Http\Validation\Entries;

use Tests\TestCase;
use ec5\Http\Validation\Entries\Archive\RuleArchive;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RuleArchiveTest extends TestCase
{
    use DatabaseTransactions;

    private $ruleArchive;
    private $payload;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleArchive = new RuleArchive();
        $this->payload = [
            'type' => 'archive',
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
            'archive' => [
                'entry_uuid' => '42fd84b0-99e7-11ee-92b0-758caf1d828e'
            ]
        ];
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleArchive->resetErrors();
    }

    public function test_valid_payload()
    {
        $this->ruleArchive->validate($this->payload);
        $this->assertFalse($this->ruleArchive->hasErrors());
    }

    public function test_should_fail_if_type_wrong()
    {
        $this->payload['type'] = 'wrong';

        $this->ruleArchive->validate($this->payload);

        $this->assertTrue($this->ruleArchive->hasErrors());
        $this->assertEquals(
            [
                'type' => ['ec5_29']
            ],
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_id_wrong()
    {
        $this->payload['id'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

        $this->assertEquals(
            [
                'id' => [
                    'ec5_21'
                ],
                'archive.entry_uuid' => [
                    'The archive.entry uuid and id must match.'
                ]
            ],
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_attributes_wrong()
    {
        $this->payload['attributes'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

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
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_attributes_form_wrong()
    {
        $this->payload['attributes']['form'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

        $this->assertEquals(
            [
                'attributes.form.ref' => [
                    'ec5_21'
                ],
                'attributes.form.type' => [
                    'ec5_21'
                ]
            ],
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_attributes_form_ref_wrong()
    {
        $this->payload['attributes']['form']['ref'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

        $this->assertEquals(
            [
                'attributes.form.ref' => [
                    'ec5_21'
                ]
            ],
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_attributes_form_type_wrong()
    {
        $this->payload['attributes']['form']['type'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

        $this->assertEquals(
            [
                'attributes.form.type' => [
                    'ec5_21'
                ]
            ],
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_relationships_wrong()
    {
        $this->payload['relationships'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

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
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_archive_key_missing()
    {
        $this->payload['archive'] = null;
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());


        $this->assertEquals(
            [
                'archive' => [
                    'ec5_21'
                ],
                'archive.entry_uuid' => [
                    'ec5_21'
                ]
            ],
            $this->ruleArchive->errors
        );
    }

    public function test_should_fail_if_archive_entry_uuid_not_matching()
    {
        $this->payload['archive']['entry_uuid'] = 'mismatch';
        $this->ruleArchive->validate($this->payload);
        $this->assertTrue($this->ruleArchive->hasErrors());

        $this->assertEquals(
            [
                'archive.entry_uuid' => [
                    'ec5_43',
                    'The archive.entry uuid and id must match.'
                ]
            ],
            $this->ruleArchive->errors
        );
    }
}