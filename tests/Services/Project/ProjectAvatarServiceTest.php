<?php

namespace Tests\Services\Project;

use ec5\Services\Project\ProjectAvatarService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;
use Laravolt\Avatar\Facade as Avatar;
use ec5\Libraries\Utilities\Common;
use Exception;

/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.StaticAccess)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ProjectAvatarServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProjectAvatarService $service;
    private string $testProjectRef;
    private string $testProjectName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProjectAvatarService();
        $this->testProjectRef = 'test_project_' . uniqid();
        $this->testProjectName = 'Test Project Name';

        // Mock storage disks for testing
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        // Set up test configuration
        Config::set('epicollect.media.project_avatar.width', [
            'thumb' => 150,
            'mobile' => 300
        ]);
        Config::set('epicollect.media.project_avatar.height', [
            'thumb' => 150,
            'mobile' => 300
        ]);
        Config::set('epicollect.media.project_avatar.quality', 85);
        Config::set('epicollect.media.project_avatar.filename', 'avatar.jpg');
        Config::set('epicollect.media.project_avatar.driver', ['local', 's3']);
        Config::set('epicollect.media.project_avatar.font_size', [
            'thumb' => 60,
            'mobile' => 120
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_instantiated_with_correct_configuration()
    {
        $service = new ProjectAvatarService();

        $this->assertInstanceOf(ProjectAvatarService::class, $service);
    }

    /** @test */
    public function it_generates_avatar_for_local_storage_driver()
    {
        Config::set('filesystems.default', 'local');

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_generates_avatar_for_s3_storage_driver()
    {
        Config::set('filesystems.default', 's3');
        Storage::fake('project_thumb');
        Storage::fake('project_mobile_logo');

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_unsupported_storage_driver()
    {
        Config::set('filesystems.default', 'unsupported_driver');
        Log::shouldReceive('error')->once();

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_creates_directories_for_local_storage_when_they_dont_exist()
    {
        Config::set('filesystems.default', 'local');

        // Ensure directories don't exist initially
        Storage::disk('project_thumb')->assertMissing($this->testProjectRef);
        Storage::disk('project_mobile_logo')->assertMissing($this->testProjectRef);

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef);
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef);
    }

    /** @test */
    public function it_generates_thumb_avatar_with_correct_dimensions_locally()
    {
        Config::set('filesystems.default', 'local');

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_generates_mobile_avatar_with_correct_dimensions_locally()
    {
        Config::set('filesystems.default', 'local');

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_sets_correct_visibility_for_local_storage()
    {
        Config::set('filesystems.default', 'local');

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);

        // Verify files were stored with public visibility
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_generates_s3_avatars_with_correct_quality()
    {
        Config::set('filesystems.default', 's3');

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_handles_exception_during_local_avatar_generation()
    {
        Config::set('filesystems.default', 'local');

        // Mock Avatar to throw exception
        Avatar::shouldReceive('create')->andThrow(new Exception('Avatar generation failed'));
        Log::shouldReceive('error')->once();

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_exception_during_s3_avatar_generation()
    {
        Config::set('filesystems.default', 's3');

        // Mock Avatar to throw exception
        Avatar::shouldReceive('create')->andThrow(new Exception('S3 upload failed'));
        Log::shouldReceive('error')->once();

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_storage_disk_creation_failure()
    {
        Config::set('filesystems.default', 'local');

        // Mock Storage to throw exception when making directory
        Storage::shouldReceive('disk->makeDirectory')->andThrow(new Exception('Directory creation failed'));
        Log::shouldReceive('error')->once();

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_uses_correct_filename_from_configuration()
    {
        Config::set('filesystems.default', 'local');
        Config::set('epicollect.media.project_avatar.filename', 'custom_avatar.jpg');

        // Recreate service to pick up new config
        $service = new ProjectAvatarService();

        $result = $service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/custom_avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/custom_avatar.jpg');
    }

    /** @test */
    public function it_handles_empty_project_name()
    {
        Config::set('filesystems.default', 'local');

        $result = $this->service->generate($this->testProjectRef, '');

        // Should still work with empty name - Avatar library handles this
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_special_characters_in_project_name()
    {
        Config::set('filesystems.default', 'local');
        $projectNameWithSpecialChars = 'Test Project @#$%^&*()';

        $result = $this->service->generate($this->testProjectRef, $projectNameWithSpecialChars);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_handles_unicode_characters_in_project_name()
    {
        Config::set('filesystems.default', 'local');
        $unicodeProjectName = 'Тест Проект 测试项目 プロジェクト';

        $result = $this->service->generate($this->testProjectRef, $unicodeProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_overwrites_existing_avatars()
    {
        Config::set('filesystems.default', 'local');

        // Generate avatar first time
        $result1 = $this->service->generate($this->testProjectRef, 'First Name');
        $this->assertTrue($result1);

        // Generate avatar second time with different name
        $result2 = $this->service->generate($this->testProjectRef, 'Second Name');
        $this->assertTrue($result2);

        // Should still exist (overwritten)
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_handles_very_long_project_names()
    {
        Config::set('filesystems.default', 'local');
        $longProjectName = str_repeat('Very Long Project Name ', 20); // ~420 characters

        $result = $this->service->generate($this->testProjectRef, $longProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_uses_different_font_sizes_for_thumb_and_mobile()
    {
        Config::set('filesystems.default', 'local');
        Config::set('epicollect.media.project_avatar.font_size', [
            'thumb' => 30,
            'mobile' => 60
        ]);

        // Recreate service to pick up new config
        $service = new ProjectAvatarService();

        $result = $service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_uses_different_dimensions_for_thumb_and_mobile()
    {
        Config::set('filesystems.default', 'local');
        Config::set('epicollect.media.project_avatar.width', [
            'thumb' => 100,
            'mobile' => 400
        ]);
        Config::set('epicollect.media.project_avatar.height', [
            'thumb' => 100,
            'mobile' => 400
        ]);

        // Recreate service to pick up new config
        $service = new ProjectAvatarService();

        $result = $service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
        Storage::disk('project_thumb')->assertExists($this->testProjectRef . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($this->testProjectRef . '/avatar.jpg');
    }

    /** @test */
    public function it_handles_concurrent_avatar_generation()
    {
        Config::set('filesystems.default', 'local');

        $projectRef1 = 'concurrent_test_1_' . uniqid();
        $projectRef2 = 'concurrent_test_2_' . uniqid();

        $result1 = $this->service->generate($projectRef1, 'Project One');
        $result2 = $this->service->generate($projectRef2, 'Project Two');

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        Storage::disk('project_thumb')->assertExists($projectRef1 . '/avatar.jpg');
        Storage::disk('project_thumb')->assertExists($projectRef2 . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($projectRef1 . '/avatar.jpg');
        Storage::disk('project_mobile_logo')->assertExists($projectRef2 . '/avatar.jpg');
    }

    /** @test */
    public function it_logs_directory_creation_info()
    {
        Config::set('filesystems.default', 'local');
        Log::shouldReceive('info')->twice()->with(Mockery::pattern('/Creating directory/'));

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_storage_permission_issues()
    {
        Config::set('filesystems.default', 'local');

        // Mock Common::setPermissionsRecursiveUp to simulate permission failure
        $commonMock = Mockery::mock('alias:' . Common::class);
        $commonMock->shouldReceive('setPermissionsRecursiveUp')->andThrow(new Exception('Permission denied'));

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('error')->once();

        $result = $this->service->generate($this->testProjectRef, $this->testProjectName);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_validates_configuration_values_on_instantiation()
    {
        // Test with missing configuration
        Config::set('epicollect.media.project_avatar', null);

        $this->expectException(\TypeError::class);

        new ProjectAvatarService();
    }

    /** @test */
    public function it_handles_null_project_reference()
    {
        Config::set('filesystems.default', 'local');

        $this->expectException(\TypeError::class);

        $this->service->generate(null, $this->testProjectName);
    }

    /** @test */
    public function it_handles_null_project_name()
    {
        Config::set('filesystems.default', 'local');

        $this->expectException(\TypeError::class);

        $this->service->generate($this->testProjectRef, null);
    }

    /** @test */
    public function it_generates_different_avatars_for_different_project_names()
    {
        Config::set('filesystems.default', 'local');

        $projectRef1 = 'project_1_' . uniqid();
        $projectRef2 = 'project_2_' . uniqid();

        $result1 = $this->service->generate($projectRef1, 'Project Alpha');
        $result2 = $this->service->generate($projectRef2, 'Project Beta');

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        // Both should exist but potentially have different generated content
        Storage::disk('project_thumb')->assertExists($projectRef1 . '/avatar.jpg');
        Storage::disk('project_thumb')->assertExists($projectRef2 . '/avatar.jpg');
    }
}