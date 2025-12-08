<?php

namespace Tests\Http\Validation\Project;

use ec5\Http\Validation\Project\RuleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RuleSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private RuleSettings $ruleSettings;

    public function setUp(): void
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleSettings = new ruleSettings();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->ruleSettings->resetErrors();
    }

    public function test_valid_payload_access()
    {
        for ($i = 0; $i < 10 ; $i++) {
            $data = [
                'access' => array_rand(config('epicollect.strings.project_access'))
            ];

            $this->ruleSettings->validate($data);
            $this->assertFalse($this->ruleSettings->hasErrors());
        }
    }

    public function test_valid_payload_status()
    {
        for ($i = 0; $i < 10 ; $i++) {
            $data = [
                'status' => array_rand(config('epicollect.strings.projects_status_all'))
            ];
            $this->ruleSettings->validate($data);
            $this->assertFalse($this->ruleSettings->hasErrors());
        }
    }

    public function test_valid_payload_visibility()
    {
        for ($i = 0; $i < 10 ; $i++) {
            $data = [
                'visibility' => array_rand(config('epicollect.strings.project_visibility'))
            ];
            $this->ruleSettings->validate($data);
            $this->assertFalse($this->ruleSettings->hasErrors());
        }
    }

    public function test_valid_payload_category()
    {
        for ($i = 0; $i < 10 ; $i++) {
            $data = [
                'category' => array_rand(config('epicollect.strings.project_categories'))
            ];
            $this->ruleSettings->validate($data);
            $this->assertFalse($this->ruleSettings->hasErrors());
        }
    }

    public function test_valid_payload_app_link_visibility()
    {
        for ($i = 0; $i < 10 ; $i++) {
            $data = [
                'app_link_visibility' => array_rand(config('epicollect.strings.app_link_visibility'))
            ];
            $this->ruleSettings->validate($data);
            $this->assertFalse($this->ruleSettings->hasErrors());
        }

    }
}
