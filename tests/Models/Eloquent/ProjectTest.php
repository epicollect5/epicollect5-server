<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Models\Eloquent;

use Carbon\Carbon;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\Project\ProjectStats;
use ec5\Models\Project\ProjectStructure;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Throwable;

class ProjectTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        $this->clearDatabase([]);
    }

    /**
     * @throws Throwable
     */
    public function test_transfer_ownership()
    {
        $creatorRole = config('epicollect.permissions.projects.creator_role');
        $managerRole = config('epicollect.permissions.projects.manager_role');

        //create a fake user with creator email
        $creator = factory(User::class)->create([
            'email' => config('testing.CREATOR_EMAIL')
        ]);

        //create a fake user with manager email
        $manager = factory(User::class)->create([
            'email' => config('testing.MANAGER_EMAIL')
        ]);

        //create fake project with creator user
        $project = factory(Project::class)->create(
            ['created_by' => $creator->id]
        );

        //add creator role to that project
        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);

        //add manager role to that project
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);

        $this->assertDatabaseHas('users', ['email' => config('testing.CREATOR_EMAIL')]);
        $this->assertDatabaseHas('users', ['email' => config('testing.MANAGER_EMAIL')]);

        $this->assertDatabaseHas('project_roles', [
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);

        $this->assertDatabaseHas('project_roles', [
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);


        if ($project->transferOwnership($project->id, $creator->id, $manager->id)) {
            //assert creator is now a manager
            $this->assertDatabaseHas('project_roles', [
                'project_id' => $project->id,
                'user_id' => $creator->id,
                'role' => $managerRole
            ]);
            //assert manager is now a creator
            $this->assertDatabaseHas('project_roles', [
                'project_id' => $project->id,
                'user_id' => $manager->id,
                'role' => $creatorRole
            ]);
        }
    }

    public function test_creator_email()
    {
        $creatorRole = config('epicollect.permissions.projects.creator_role');
        //create a fake user with creator email
        $creator = factory(User::class)->create([
            'email' => config('testing.CREATOR_EMAIL')
        ]);
        //create the fake project with creator user
        $project = factory(Project::class)->create(
            ['created_by' => $creator->id]
        );
        //add the creator role to that project
        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);
        $this->assertDatabaseHas('users', ['email' => config('testing.CREATOR_EMAIL')]);
        $this->assertDatabaseHas('project_roles', [
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);

        $email = Project::creatorEmail($project->id);
        $this->assertEquals(config('testing.CREATOR_EMAIL'), $email);

        //remove creator and retest (safety net)
        User::where('email', $email)->delete();
        $email = Project::creatorEmail($project->id);
        $this->assertEquals('n/a', $email);
    }

    public function test_admin_projects_include_structure_last_updated()
    {
        $updatedAt = '2026-05-08 10:11:12';
        $creator = User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first();
        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'name' => 'Admin Structure Version Test'
        ]);

        factory(ProjectStats::class)->create([
            'project_id' => $project->id,
            'total_entries' => 1
        ]);

        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'updated_at' => $updatedAt
        ]);

        $projects = (new Project())->admin(['name' => $project->name]);
        $adminProject = $projects->items()[0];

        $this->assertSame($updatedAt, $adminProject->structure_last_updated);
    }

    public function test_starts_with_caps_results_at_20_and_exposes_structure_last_updated()
    {
        $creator = User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first();
        $updatedAt = '2026-05-08 10:11:12';
        $baseName = 'Search Cap Test';

        // Create 25 projects whose name starts with the search term
        for ($i = 0; $i < 25; $i++) {
            $project = factory(Project::class)->create([
                'created_by' => $creator->id,
                'name' => $baseName . ' ' . $i,
            ]);
            factory(ProjectStructure::class)->create([
                'project_id' => $project->id,
                'updated_at' => $updatedAt,
            ]);
        }

        $hits = Project::startsWith($baseName, ['name', 'slug', 'access', 'ref', 'has_logo']);

        $this->assertCount(20, $hits);
        $this->assertIsString($hits->first()->structure_last_updated);
        $this->assertSame(
            $updatedAt,
            Carbon::parse($hits->first()->structure_last_updated)->format('Y-m-d H:i:s')
        );
    }

    public function test_matches_returns_one_row_with_structure_last_updated()
    {
        $creator = User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first();
        $updatedAt = '2026-05-08 13:14:15';
        $name = 'Match Scope Test';

        $project = factory(Project::class)->create([
            'created_by' => $creator->id,
            'name' => $name,
        ]);
        factory(ProjectStructure::class)->create([
            'project_id' => $project->id,
            'updated_at' => $updatedAt,
        ]);

        $hits = Project::matches($name, ['name', 'slug', 'access', 'ref', 'has_logo']);

        $this->assertCount(1, $hits);
        $this->assertSame($project->ref, $hits->first()->ref);
        $this->assertSame(
            $updatedAt,
            Carbon::parse($hits->first()->structure_last_updated)->format('Y-m-d H:i:s')
        );
    }

    public function test_starts_with_returns_null_structure_last_updated_when_no_structure_row_exists()
    {
        $creator = User::where('email', config('testing.SUPER_ADMIN_EMAIL'))->first();
        $name = 'Orphan Search Test';

        factory(Project::class)->create([
            'created_by' => $creator->id,
            'name' => $name,
        ]);

        $hits = Project::startsWith($name, ['name', 'slug', 'access', 'ref', 'has_logo']);

        $this->assertCount(1, $hits);
        $this->assertNull($hits->first()->structure_last_updated);
    }
}
