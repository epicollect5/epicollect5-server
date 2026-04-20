<?php

namespace Tests\Http\Middleware;

use ec5\DTO\ProjectDTO;
use ec5\Http\Middleware\ProjectPermissionsApi;
use Illuminate\Http\Request;
use League\OAuth2\Server\ResourceServer;
use stdClass;
use Tests\TestCase;

class ProjectPermissionsApiTest extends TestCase
{
    public function test_it_should_bail_if_requested_project_is_trashed()
    {
        $middleware = $this->makeMiddlewareWithProjectStatus(config('epicollect.strings.project_status.trashed'));

        $this->assertFalse($middleware->hasPermission());
        $this->assertSame('ec5_11', $middleware->getErrorCode());
    }

    public function test_it_should_bail_if_requested_project_is_archived()
    {
        $middleware = $this->makeMiddlewareWithProjectStatus(config('epicollect.strings.project_status.archived'));

        $this->assertFalse($middleware->hasPermission());
        $this->assertSame('ec5_11', $middleware->getErrorCode());
    }

    private function makeMiddlewareWithProjectStatus(string $status): ProjectPermissionsApi
    {
        $requestedProject = app(ProjectDTO::class);
        $requestedProject->initAllDTOs($this->makeProjectData($status));

        return new class ($this->createMock(ResourceServer::class), Request::create('/api/export/project/test-project', 'GET'), $requestedProject) extends ProjectPermissionsApi {
            public function getErrorCode(): string
            {
                return $this->error;
            }
        };
    }

    private function makeProjectData(string $status): stdClass
    {
        return (object) [
            'id' => 1,
            'project_id' => 1,
            'structure_id' => 1,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'ref' => 'test-project-ref',
            'description' => '',
            'small_description' => '',
            'logo_url' => '',
            'access' => config('epicollect.strings.project_access.private'),
            'visibility' => config('epicollect.strings.project_visibility.listed'),
            'category' => 'general',
            'created_by' => 1,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'status' => $status,
            'can_bulk_upload' => 'nobody',
            'app_link_visibility' => 'hidden',
            'project_definition' => json_encode(['project' => [], 'forms' => []]),
            'project_extra' => json_encode([]),
            'project_mapping' => json_encode([]),
            'total_entries' => 0,
            'total_bytes' => 0,
            'form_counts' => json_encode([]),
            'branch_counts' => json_encode([]),
            'structure_last_updated' => now()->toDateTimeString(),
        ];
    }
}
