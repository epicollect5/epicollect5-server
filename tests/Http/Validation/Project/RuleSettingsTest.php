<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleSettings;
use ec5\Models\Eloquent\Project;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RuleSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private $ruleSettings;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleSettings = new ruleSettings();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->ruleSettings->resetErrors();
    }

    public function test_valid_payload_access()
    {
        $data = [
            'access' => array_rand(config('epicollect.strings.project_access'))
        ];

        $this->ruleSettings->validate($data);
        $this->assertFalse($this->ruleSettings->hasErrors());
    }

    public function test_valid_payload_status()
    {
        $data = [
            'status' => array_rand(config('epicollect.strings.projects_status_all'))
        ];
        $this->ruleSettings->validate($data);
        $this->assertFalse($this->ruleSettings->hasErrors());
    }

    public function test_valid_payload_visibility()
    {
        $data = [
            'visibility' => array_rand(config('epicollect.strings.project_visibility'))
        ];
        $this->ruleSettings->validate($data);
        $this->assertFalse($this->ruleSettings->hasErrors());
    }

    public function test_valid_payload_category()
    {
        $data = [
            'category' => array_rand(config('epicollect.strings.project_categories'))
        ];
        $this->ruleSettings->validate($data);
        $this->assertFalse($this->ruleSettings->hasErrors());
    }
}