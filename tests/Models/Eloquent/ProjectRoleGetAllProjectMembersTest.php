<?php

namespace Tests\Models\Eloquent;

use DB;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectRole;
use ec5\Models\User\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Throwable;

class ProjectRoleGetAllProjectMembersTest extends TestCase
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
    public function test_returns_one_row_per_project_member_with_expected_shape(): void
    {
        $creatorRole = config('epicollect.permissions.projects.creator_role');
        $managerRole = config('epicollect.permissions.projects.manager_role');
        $curatorRole = config('epicollect.strings.project_roles.curator');

        $creator = factory(User::class)->create();
        $manager = factory(User::class)->create();
        $curator = factory(User::class)->create();

        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $project->id,
            'role' => $curatorRole
        ]);

        $members = ProjectRole::getAllProjectMembers($project->id);

        $this->assertCount(3, $members);

        $emailsToRoles = [];
        foreach ($members as $member) {
            $this->assertIsObject($member);
            $this->assertTrue(property_exists($member, 'name'));
            $this->assertTrue(property_exists($member, 'last_name'));
            $this->assertTrue(property_exists($member, 'email'));
            $this->assertTrue(property_exists($member, 'role'));
            $emailsToRoles[$member->email] = $member->role;
        }

        $this->assertSame($creatorRole, $emailsToRoles[$creator->email]);
        $this->assertSame($managerRole, $emailsToRoles[$manager->email]);
        $this->assertSame($curatorRole, $emailsToRoles[$curator->email]);
    }

    /**
     * @throws Throwable
     */
    public function test_returns_empty_array_for_project_with_no_members(): void
    {
        $creator = factory(User::class)->create();
        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        $members = ProjectRole::getAllProjectMembers($project->id);

        $this->assertSame([], $members);
    }

    /**
     * Regression guard: the method must issue exactly one query, regardless
     * of the number of project members. Locks in the N+1 fix.
     *
     * @throws Throwable
     */
    public function test_issues_exactly_one_query(): void
    {
        $creatorRole = config('epicollect.permissions.projects.creator_role');
        $managerRole = config('epicollect.permissions.projects.manager_role');

        $creator = factory(User::class)->create();
        $manager = factory(User::class)->create();
        $curator = factory(User::class)->create();
        $collector = factory(User::class)->create();

        $project = factory(Project::class)->create(['created_by' => $creator->id]);

        factory(ProjectRole::class)->create([
            'user_id' => $creator->id,
            'project_id' => $project->id,
            'role' => $creatorRole
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $manager->id,
            'project_id' => $project->id,
            'role' => $managerRole
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $curator->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.curator')
        ]);
        factory(ProjectRole::class)->create([
            'user_id' => $collector->id,
            'project_id' => $project->id,
            'role' => config('epicollect.strings.project_roles.collector')
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        ProjectRole::getAllProjectMembers($project->id);

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(
            1,
            $queries,
            'getAllProjectMembers() must issue exactly one query, got: ' . count($queries)
        );
    }
}
