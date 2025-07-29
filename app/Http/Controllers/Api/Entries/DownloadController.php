<?php

namespace ec5\Http\Controllers\Api\Entries;

use Auth;
use Cache;
use Cookie;
use ec5\Http\Validation\Entries\Download\RuleDownload;
use ec5\Libraries\Utilities\Common;
use ec5\Models\User\User;
use ec5\Services\Entries\EntriesDownloadService;
use ec5\Services\Entries\EntriesViewService;
use ec5\Services\Mapping\DataMappingService;
use ec5\Traits\Requests\RequestAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Storage;
use Throwable;

class DownloadController
{
    use RequestAttributes;

    /*
    |--------------------------------------------------------------------------
    | Download Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the downloading of entry data (compressed using zip)
    |
    */

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(Request $request, RuleDownload $ruleDownload, EntriesViewService $viewEntriesService)
    {
        $user = Auth::user();
        $allowedKeys = array_keys(config('epicollect.strings.download_data_entries'));
        $perPage = config('epicollect.limits.entries_table.per_page');
        $params = $viewEntriesService->getSanitizedQueryParams($allowedKeys, $perPage);
        $cookieName = config('epicollect.setup.cookies.download_entries');

        if ($user === null) {
            return Response::apiErrorCode(400, ['download-entries' => ['ec5_86']]);
        }
        // Validate the request params
        $ruleDownload->validate($params);
        if ($ruleDownload->hasErrors()) {
            return Response::apiErrorCode(400, $ruleDownload->errors());
        }
        //we send a "media-request" parameter in the query string with a timestamp. to generate a cookie with the same timestamp
        $timestamp = $request->query($cookieName);
        if ($timestamp) {
            //check if the timestamp is valid
            if (!Common::isValidTimestamp($timestamp) && strlen($timestamp) === 13) {
                return Response::apiErrorCode(400, ['download-entries' => ['ec5_29']]);
            }
        } else {
            //error no timestamp was passed
            return Response::apiErrorCode(400, ['download-entries' => ['ec5_29']]);
        }
        $projectDir = $this->getArchivePath($user);

        // Fix permissions if path does not exist
        // Ensure directory exists with correct permissions ()
        $storage =  Storage::disk('entries_zip');
        if (!$storage->exists($this->requestedProject()->ref.'/' . $user->id)) {
            $storage->makeDirectory($this->requestedProject()->ref.'/' . $user->id);

            // For local driver, fix permissions for entire chain
            $diskRoot = $storage->path('');

            // Build full folder path to newly created directory
            $newDirFullPath = $diskRoot . $this->requestedProject()->ref.'/' . $user->id;

            // Fix folder chain permissions up to app/ to fix laravel 700 issue since 9+
            try {
                Common::setPermissionsRecursiveUp($newDirFullPath);
            } catch (Throwable $e) {
                Log::error('Failed to set permissions on: ' . $newDirFullPath, ['exception' => $e->getMessage()]);
                return Response::apiErrorCode(400, ['download-entries' => ['ec5_83']]);
            }
        }

        // Try and create the files
        return $this->createArchive($projectDir, $params, $timestamp);
    }

    /**
     * Sends an archive file as a download and deletes it after sending, or returns an error if the file is missing.
     *
     * This method checks if the file exists at the specified path. If it does, it queues a download entries cookie based on
     * the provided timestamp and returns a download response that deletes the file after sending. If the file cannot be found,
     * it returns an error response with a designated error code.
     *
     * @param string $filepath The path to the archive file.
     * @param string $filename The name to be used for the downloaded file.
     * @param ?string $timestamp Optional timestamp used for generating the download entries cookie and error response.
     */
    private function sendArchive(string $filepath, string $filename, ?string $timestamp = null)
    {
        if (file_exists($filepath)) {
            $downloadEntriesCookie = Common::getDownloadEntriesCookie($timestamp);
            Cookie::queue($downloadEntriesCookie);
            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } else {
            return Common::errorResponseAsFile($timestamp, 'ec5_364');
        }
    }

    /**
     * Creates a downloadable ZIP archive of project entries.
     *
     * Acquires a user-specific cache lock to prevent concurrent archive generation. If the lock
     * is obtained, the method attempts to create the archive using the EntriesDownloadService with
     * the designated project directory and parameters. On successful archive creation, it builds the
     * ZIP filename and sends the file as a download. If archive creation fails, it returns an error
     * response with code "ec5_83", and if the lock cannot be acquired, it returns an error response with
     * code "ec5_406".
     *
     * @param string $projectDir Directory where the archive is to be stored.
     * @param array $params Parameters for archive creation (e.g., output format).
     * @param ?string $timestamp A timestamp used for file naming and error response consistency.
     * @return mixed The response from sending the archive file or an error response.
     */
    private function createArchive(string $projectDir, array $params, ?string $timestamp)
    {
        $lockKey = 'download-entries-archive-' . $this->requestedUser()->id;

        // Attempt to acquire the lock
        $lock = Cache::lock(
            $lockKey,
            config('epicollect.setup.locks.duration_archive_download_lock')
        );

        if ($lock->get()) {
            try {
                $entriesDownloadService = new EntriesDownloadService(new DataMappingService());
                if (!$entriesDownloadService->createArchive($this->requestedProject(), $projectDir, $params)) {
                    return Common::errorResponseAsFile($timestamp, 'ec5_83');
                }
                $zipName = $this->requestedProject()->slug . '-' . $params['format'] . '.zip';
                return $this->sendArchive($projectDir . '/' . $zipName, $zipName, $timestamp);
            } catch (Throwable $e) {
                // Log the actual error for debugging
                Log::error('Archive creation failed: ' . $e->getMessage());
                // Return a generic error code to the user
                return Common::errorResponseAsFile($timestamp, 'ec5_83');
            } finally {
                // Release the lock
                $lock->release();
            }
        } else {
            return Common::errorResponseAsFile($timestamp, 'ec5_406');
        }
    }

    /**
     * Constructs the archive directory path for a given user.
     *
     * This method obtains the base storage path for ZIP entries, appends the reference of the
     * currently requested project, and then adds the user's ID to ensure that archive files are stored
     * in a unique directory per user. This helps prevent conflicts during concurrent downloads.
     *
     * @param User $user The authenticated user object with an accessible 'id' property.
     * @return string The complete file system path for the user's archive directory.
     */
    private function getArchivePath(User $user)
    {
        // Setup storage
        $storage = Storage::disk('entries_zip');
        $storagePrefix = $storage->path('');
        $projectDir = $storagePrefix . $this->requestedProject()->ref;

        //append user ID to handle concurrency -> MUST be logged in to download!
        return $projectDir . '/' . $user->id;
    }
}
