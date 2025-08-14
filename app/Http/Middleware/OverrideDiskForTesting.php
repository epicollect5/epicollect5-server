<?php

namespace ec5\Http\Middleware;

use App;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OverrideDiskForTesting
{
    /**
     * Handle an incoming testing request (Guzzle)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always skip in production
        if (!App::environment('production') && config('app.debug')) {
            $ip = $request->ip();
            // Allow only localhost or similar
            if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($request->getHost(), 'localhost')) {
                // Optional: read override from header or hardcode here
                $diskOverride = $request->header('X-Disk-Override', 'local');

                // Apply to specific disks you want to override during tests
                if ($diskOverride === 'local') {
                    config([
                        'filesystems.default' => $diskOverride,
                        'filesystems.disks.temp.driver' => $diskOverride,
                        'filesystems.disks.temp.root' => storage_path('app/temp'),
                        'filesystems.disks.entry_original.driver' => $diskOverride,
                        'filesystems.disks.entry_original.root' => storage_path('app/entries/photo/entry_original'),
                        'filesystems.disks.entry_thumb.driver' => $diskOverride,
                        'filesystems.disks.entry_thumb.root' => storage_path('app/entries/photo/entry_thumb'),
                        'filesystems.disks.project_thumb.driver' => $diskOverride,
                        'filesystems.disks.project_thumb.root' => storage_path('app/projects/project_thumb'),
                        'filesystems.disks.project_mobile_logo.driver' => $diskOverride,
                        'filesystems.disks.project_mobile_logo.root' => storage_path('app/projects/project_mobile_logo'),
                        'filesystems.disks.audio.driver' => $diskOverride,
                        'filesystems.disks.audio.root' => storage_path('app/entries/audio'),
                        'filesystems.disks.video.driver' => $diskOverride,
                        'filesystems.disks.video.root' => storage_path('app/entries/video'),
                    ]);
                } elseif ($diskOverride === 's3') {
                    config([
                        'filesystems.default' => $diskOverride,
                        'filesystems.disks.temp.driver' => $diskOverride,
                        'filesystems.disks.temp.root' => 'app/temp',
                        'filesystems.disks.entry_original.driver' => $diskOverride,
                        'filesystems.disks.entry_original.root' => 'app/entries/photo/entry_original',
                        'filesystems.disks.entry_thumb.driver' => $diskOverride,
                        'filesystems.disks.entry_thumb.root' => 'app/entries/photo/entry_thumb',
                        'filesystems.disks.project_thumb.driver' => $diskOverride,
                        'filesystems.disks.project_thumb.root' => 'app/projects/project_thumb',
                        'filesystems.disks.project_mobile_logo.driver' => $diskOverride,
                        'filesystems.disks.project_mobile_logo.root' => 'app/projects/project_mobile_logo',
                        'filesystems.disks.audio.driver' => $diskOverride,
                        'filesystems.disks.audio.root' => 'app/entries/audio',
                        'filesystems.disks.video.driver' => $diskOverride,
                        'filesystems.disks.video.root' => 'app/entries/video',
                    ]);
                }
            }
        }


        return $next($request);
    }
}
