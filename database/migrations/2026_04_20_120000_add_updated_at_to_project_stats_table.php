<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ec5\Models\Project\ProjectStructure;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_stats', function (Blueprint $table) {
            $table->timestamp('updated_at')->useCurrent()->after('branch_counts');
        });

        DB::table('project_stats')
            ->select([
                'id',
                'project_id',
                'form_counts',
                'branch_counts',
                'total_bytes_updated_at',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($projectStatsRows) {
                foreach ($projectStatsRows as $projectStatsRow) {
                    $timestamps = [];

                    foreach ([$projectStatsRow->form_counts, $projectStatsRow->branch_counts] as $countsJson) {
                        $counts = json_decode($countsJson ?? '', true);

                        if (!is_array($counts)) {
                            continue;
                        }

                        foreach ($counts as $count) {
                            $lastEntryCreated = $count['last_entry_created'] ?? null;

                            if (!empty($lastEntryCreated)) {
                                $timestamps[] = Carbon::parse($lastEntryCreated);
                            }
                        }
                    }

                    if (!empty($projectStatsRow->total_bytes_updated_at)) {
                        $timestamps[] = Carbon::parse($projectStatsRow->total_bytes_updated_at);
                    }

                    $updatedAt = collect($timestamps)
                        ->sortByDesc(function (Carbon $timestamp) {
                            return $timestamp->getTimestamp();
                        })
                        ->first();

                    if (empty($updatedAt)) {
                        $updatedAt = ProjectStructure::where(
                            'project_id',
                            $projectStatsRow->project_id
                        )
                            ->value('updated_at');
                        DB::table('project_stats')
                            ->where('id', $projectStatsRow->id)
                            ->update([
                                'updated_at' => $updatedAt
                            ]);
                    } else {
                        DB::table('project_stats')
                            ->where('id', $projectStatsRow->id)
                            ->update([
                                'updated_at' => $updatedAt->format('Y-m-d H:i:s')
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('project_stats', 'updated_at')) {
            Schema::table('project_stats', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
