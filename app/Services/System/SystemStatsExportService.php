<?php

namespace ec5\Services\System;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SystemStatsExportService
{
    /**
     * @throws Exception
     */
    public function generatePayload(): array
    {
        $today = Carbon::now();

        $history = DB::table('system_stats')
            ->where('created_at', '>=', $today->copy()->subMonths(3)->startOfDay())
            ->orderBy('created_at', 'asc')
            ->get();

        if ($history->isEmpty()) {
            throw new Exception('No stats data available.');
        }

        $current = $history->last();

        // Final Totals for the "Stats" section
        $uStats = json_decode($current->user_stats, true);
        $pStats = json_decode($current->project_stats, true);
        $eStats = json_decode($current->entries_stats, true);
        $bStats = json_decode($current->branch_entries_stats, true);

        $uTotal = $uStats['total'] ?? 0;
        $pTotal = ($pStats['total']['public']['hidden'] ?? 0) + ($pStats['total']['public']['listed'] ?? 0) +
            ($pStats['total']['private']['hidden'] ?? 0) + ($pStats['total']['private']['listed'] ?? 0);
        $eTotal = ($eStats['total']['public'] ?? 0) + ($eStats['total']['private'] ?? 0) +
            ($bStats['total']['public'] ?? 0) + ($bStats['total']['private'] ?? 0);

        $diffs = $this->calculateWindowsDiff($history);

        // Build Daily Charts Data
        $chartData = $history->map(function (stdClass $row) {
            $u = json_decode($row->user_stats, true);
            $p = json_decode($row->project_stats, true);
            $e = json_decode($row->entries_stats, true);
            $b = json_decode($row->branch_entries_stats, true);

            return [
                'date' => Carbon::parse($row->created_at)->format('Y-m-d'),
                'users' => $u['today'] ?? 0,
                'projects' => ($p['today']['public']['hidden'] ?? 0) + ($p['today']['public']['listed'] ?? 0) +
                    ($p['today']['private']['hidden'] ?? 0) + ($p['today']['private']['listed'] ?? 0),
                'entries' => ($e['today']['public'] ?? 0) + ($e['today']['private'] ?? 0) +
                    ($b['today']['public'] ?? 0) + ($b['today']['private'] ?? 0)
            ];
        })->values()->toArray();

        return [
            "name" => "Epicollect5",
            "date" => $today->format('Y-m-d'),
            "stats" => [
                ["title" => "Projects", "value" => $pTotal, "label" => "Total uploaded projects", "diff" => $diffs['projects']],
                ["title" => "Users", "value" => $uTotal, "label" => "Total registered accounts", "diff" => $diffs['users']],
                ["title" => "Entries", "value" => $eTotal, "label" => "Total entries in all projects", "diff" => $diffs['entries']],
            ],
            "charts" => [
                [
                    "title" => "Total projects",
                    "series" => ["projects"],
                    "description" => "Daily project creations (3 months)",
                    "data" => $chartData
                ],
                [
                    "title" => "Total user accounts",
                    "series" => ["users"],
                    "description" => "Daily user registrations (3 months)",
                    "data" => $chartData
                ],
                [
                    "title" => "Total entries",
                    "series" => ["entries"],
                    "description" => "Daily entries uploaded (3 months)",
                    "data" => $chartData
                ]
            ]
        ];
    }

    /**
     * @throws Exception
     */
    public function uploadToS3(): bool
    {
        try {
            $payload = $this->generatePayload();

            // Upload to s3-cgps-dashboard bucket
            $uploaded = Storage::disk('s3-cgps-dashboard')->put(
                'epicollect.json',
                json_encode($payload, JSON_PRETTY_PRINT),
                'private'
            );

            if (!$uploaded) {
                // Some drivers might still return false without throwing
                throw new Exception('Failed to upload to S3 CGPS Dashboard bucket (Unknown Error).');
            }

            return true;

        } catch (Throwable $e) {
            // Log the actual AWS error message for easier debugging
            Log::error(__METHOD__ . ' failed.', [
                'message' => $e->getMessage()
            ]);

            // Re-throw or throw a custom exception so the caller (Scheduled Task) knows it failed
            throw new Exception('S3 Upload Error: ' . $e->getMessage());
        }
    }

    public function downloadFromS3(): StreamedResponse
    {
        $filename = 'epicollect.json';
        $disk = 's3-cgps-dashboard';

        if (!Storage::disk($disk)->exists($filename)) {
            abort(404, "File not found on S3.");
        }

        // This streams the file directly from S3 to your browser
        return Storage::disk($disk)->download($filename);
    }

    private function calculateWindowsDiff(Collection $history): array
    {
        $now = Carbon::now()->startOfDay();
        $dailyStats = $history->keyBy(fn (stdClass $item) => Carbon::parse($item->created_at)->format('Y-m-d'));

        $last30 = ['u' => 0, 'p' => 0, 'e' => 0];
        $prev30 = ['u' => 0, 'p' => 0, 'e' => 0];

        for ($i = 0; $i < 60; $i++) {
            $cursor = $now->copy()->subDays($i);
            $dateKey = $cursor->format('Y-m-d');

            /** @var stdClass|null $dayData */
            $dayData = $dailyStats->get($dateKey);
            if (!$dayData) {
                continue;
            }

            $u = json_decode($dayData->user_stats, true)['today'] ?? 0;

            $pj = json_decode($dayData->project_stats, true)['today'] ?? [];
            $p = ($pj['public']['hidden'] ?? 0) + ($pj['public']['listed'] ?? 0) + ($pj['private']['hidden'] ?? 0) + ($pj['private']['listed'] ?? 0);

            $ej = json_decode($dayData->entries_stats, true)['today'] ?? [];
            $bj = json_decode($dayData->branch_entries_stats, true)['today'] ?? [];
            $e = ($ej['public'] ?? 0) + ($ej['private'] ?? 0) + ($bj['public'] ?? 0) + ($bj['private'] ?? 0);

            if ($i < 30) {
                $last30['u'] += $u;
                $last30['p'] += $p;
                $last30['e'] += $e;
            } else {
                $prev30['u'] += $u;
                $prev30['p'] += $p;
                $prev30['e'] += $e;
            }
        }

        return [
            'users'    => $this->formatDiff($last30['u'], $prev30['u']),
            'projects' => $this->formatDiff($last30['p'], $prev30['p']),
            'entries'  => $this->formatDiff($last30['e'], $prev30['e']),
        ];
    }

    private function formatDiff(int $current, int $previous): string
    {
        if ($previous <= 0) {
            return "+0%";
        }
        $percent = (int) round((($current - $previous) / $previous) * 100);
        return ($percent >= 0 ? "+" : "") . $percent . "%";
    }
}
