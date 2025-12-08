<?php

namespace Tests\Models\Eloquent;

use Carbon\Carbon;
use ec5\Models\Project\Project;
use ec5\Models\Project\ProjectStats;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectStatsTest extends TestCase
{
    use DatabaseTransactions;

    protected Project $project;
    protected ProjectStats $projectStats;
    protected Carbon $now;

    public function setUp(): void
    {
        parent::setUp();
        $this->project = factory(Project::class)->create();
        $this->now = now();
        $this->projectStats = factory(ProjectStats::class)->create([
            'project_id' => $this->project->id,
            'photo_bytes' => 100,
            'photo_files' => 10,
            'audio_bytes' => 200,
            'audio_files' => 20,
            'video_bytes' => 300,
            'video_files' => 30,
            'total_bytes' => 600,
            'total_files' => 60,
            'total_bytes_updated_at' => $this->now
        ]);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 100,
            'photo_files' => 10,
            'audio_bytes' => 200,
            'audio_files' => 20,
            'video_bytes' => 300,
            'video_files' => 30,
            'total_bytes' => 600,
        ]);
    }

    public function test_reset_storage_usage()
    {
        $this->projectStats->setMediaStorageUsage(
            0,
            0,
            0,
            0,
            0,
            0
        );
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 0,
            'photo_files' => 0,
            'audio_bytes' => 0,
            'audio_files' => 0,
            'video_bytes' => 0,
            'video_files' => 0,
            'total_bytes' => 0,
            'total_files' => 0
        ]);

        ///assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }


    public function test_photo_deleted()
    {
        $this->projectStats->decrementMediaStorageUsage(10, 1, 0, 0, 0, 0);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 90,
            'photo_files' => 9,
            'audio_bytes' => 200,
            'audio_files' => 20,
            'video_bytes' => 300,
            'video_files' => 30,
            'total_bytes' => 590,
            'total_files' => 59
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_audio_deleted()
    {
        $this->projectStats->decrementMediaStorageUsage(0, 0, 20, 2, 0, 0);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 100,
            'photo_files' => 10,
            'audio_bytes' => 180,
            'audio_files' => 18,
            'video_bytes' => 300,
            'video_files' => 30,
            'total_bytes' => 580,
            'total_files' => 58
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_video_deleted()
    {
        $this->projectStats->decrementMediaStorageUsage(0, 0, 0, 0, 30, 3);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 100,
            'photo_files' => 10,
            'audio_bytes' => 200,
            'audio_files' => 20,
            'video_bytes' => 270,
            'video_files' => 27,
            'total_bytes' => 570,
            'total_files' => 57
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_one_file_per_media_type_deleted()
    {
        $this->projectStats->decrementMediaStorageUsage(10, 1, 20, 2, 30, 3);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 90,
            'photo_files' => 9,
            'audio_bytes' => 180,
            'audio_files' => 18,
            'video_bytes' => 270,
            'video_files' => 27,
            'total_bytes' => 540,
            'total_files' => 54
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_photo_added()
    {
        $this->projectStats->incrementMediaStorageUsage(10, 1, 0, 0, 0, 0);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 110,
            'photo_files' => 11,
            'audio_bytes' => 200,
            'audio_files' => 20,
            'video_bytes' => 300,
            'video_files' => 30,
            'total_bytes' => 610,
            'total_files' => 61
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_audio_added()
    {
        $this->projectStats->incrementMediaStorageUsage(0, 0, 20, 2, 0, 0);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 100,
            'photo_files' => 10,
            'audio_bytes' => 220,
            'audio_files' => 22,
            'video_bytes' => 300,
            'video_files' => 30,
            'total_bytes' => 620,
            'total_files' => 62
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_video_added()
    {
        $this->projectStats->incrementMediaStorageUsage(0, 0, 0, 0, 30, 3);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 100,
            'photo_files' => 10,
            'audio_bytes' => 200,
            'audio_files' => 20,
            'video_bytes' => 330,
            'video_files' => 33,
            'total_bytes' => 630,
            'total_files' => 63
        ]);
        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }

    public function test_one_file_per_media_type_added()
    {
        $this->projectStats->incrementMediaStorageUsage(10, 1, 20, 2, 30, 3);
        $this->assertDatabaseHas('project_stats', [
            'id' => $this->projectStats->id,
            'photo_bytes' => 110,
            'photo_files' => 11,
            'audio_bytes' => 220,
            'audio_files' => 22,
            'video_bytes' => 330,
            'video_files' => 33,
            'total_bytes' => 660,
            'total_files' => 66
        ]);

        //assert that total_bytes_updated_at is updated to a recent time (within the last minute)
        $updatedAt = Carbon::parse($this->projectStats->fresh()->total_bytes_updated_at);
        $this->assertTrue($updatedAt->greaterThan(now()->subMinute()));
    }
}
