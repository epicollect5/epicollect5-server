<?php

namespace Tests\Http\Controllers\Web;

use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectRole;
use ec5\Models\Eloquent\ProjectStat;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Users\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function test_home_page_renders_correctly()
    {
        $response = $this
            ->get(route('home'))
            ->assertStatus(200);
    }
}