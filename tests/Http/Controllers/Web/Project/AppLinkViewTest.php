<?php

namespace Tests\Http\Controllers\Web\Project;

use stdClass;
use Tests\TestCase;
use Throwable;

class AppLinkViewTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_app_link_view_generates_correct_deeplink_url()
    {
        $slug = 'test-project-slug';

        $project = new stdClass();
        $project->slug = $slug;

        $requestAttributes = new stdClass();
        $requestAttributes->requestedProject = $project;

        $output = view('project.deeplinks.app_link', [
            'requestAttributes' => $requestAttributes,
        ])->render();

        $expectedUrl = url('/open/project/' . $slug);

        $this->assertStringContainsString($expectedUrl, $output);
        $this->assertStringNotContainsString('app_link.blade.php', $output);
    }

    /**
     * @throws Throwable
     */
    public function test_app_link_view_renders_all_url_instances_consistently()
    {
        $slug = 'my-project';

        $project = new stdClass();
        $project->slug = $slug;

        $requestAttributes = new stdClass();
        $requestAttributes->requestedProject = $project;

        $output = view('project.deeplinks.app_link', [
            'requestAttributes' => $requestAttributes,
        ])->render();

        $expectedUrl = url('/open/project/' . $slug);

        // The deeplink URL should appear 3 times: copy button, pre tag, and QR code
        $this->assertSame(3, substr_count($output, $expectedUrl));
    }
}
