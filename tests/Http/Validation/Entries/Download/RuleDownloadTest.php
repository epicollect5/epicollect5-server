<?php

namespace Tests\Http\Validation\Entries\Download;

use ec5\Models\Eloquent\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ec5\Http\Validation\Entries\Download\RuleDownload;

class RuleDownloadTest extends TestCase
{
    use DatabaseTransactions;

    private $ruleDownload;

    public function setUp()
    {
        // This method will automatically be called prior to any of your test cases
        parent::setUp();
        $this->ruleDownload = new RuleDownload();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->ruleDownload->resetErrors();
    }

    public function test_missing_required_format()
    {
        $data = [
            'map_index' => 0,
            'format' => null,
        ];

        $this->ruleDownload->validate($data);

        $this->assertTrue($this->ruleDownload->hasErrors());
        $this->assertEquals(
            [
                'format' => ['ec5_21']
            ],
            $this->ruleDownload->errors);
    }

    public function test_map_index_not_numeric()
    {
        $data = [
            'map_index' => null,
            'format' => 'csv',
        ];

        $this->ruleDownload->validate($data);

        $this->assertTrue($this->ruleDownload->hasErrors());
        $this->assertEquals(
            [
                'map_index' => ['ec5_27']
            ],
            $this->ruleDownload->errors);
    }

    public function test_invalid_format()
    {
        $data = [
            'map_index' => 0,
            'format' => 'ciao',
        ];

        $this->ruleDownload->validate($data);

        $this->assertTrue($this->ruleDownload->hasErrors());
        $this->assertEquals(
            [
                'format' => ['ec5_29']
            ],
            $this->ruleDownload->errors);
    }

    public function test_valid_parameters()
    {
        foreach (['csv', 'json'] as $format) {
            foreach ([0, 1, 2] as $mapIndex) {
                $data = [
                    'map_index' => $mapIndex,
                    'format' => $format,
                ];
                $this->ruleDownload->validate($data);
                $this->assertFalse($this->ruleDownload->hasErrors());
            }
        }
    }
}