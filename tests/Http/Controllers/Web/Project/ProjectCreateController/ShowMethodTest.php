<?php

namespace Tests\Http\Controllers\Web\Project\ProjectCreateController;

use ec5\Http\Validation\Project\RuleCreateRequest;
use ec5\Models\Eloquent\Project;
use ec5\Models\Eloquent\ProjectStructure;
use ec5\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class ShowMethodTest extends TestCase
{
    use DatabaseTransactions;

    const DRIVER = 'web';

    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function test_create_page_renders_correctly()
    {
        //create a fake user and save it to DB
        $user = factory(User::class)->create();
        $response = $this->actingAs($user, self::DRIVER)->get(route('my-projects-create')); // Replace with the actual route or URL to your view
        $response->assertStatus(200); // Ensure the response is successful
    }
}
