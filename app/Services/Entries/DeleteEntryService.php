<?php

namespace ec5\Services\Entries;

use DB;
use DirectoryIterator;
use ec5\DTO\ProjectDTO;
use ec5\Models\Counters\BranchEntryCounter;
use ec5\Models\Counters\EntryCounter;
use ec5\Models\Entries\BranchEntry;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\ProjectStats;
use Exception;
use File;
use Log;
use Storage;
use Throwable;

class DeleteEntryService
{
    //imp: branch entries get deleted by FK constraint ON DELETE CASCADE
    /**
     * @throws Throwable
     */
    public function deleteHierarchyEntry(ProjectDTO $project, $entryStructure): bool
    {
        $entryUuid = $entryStructure->getEntryUuid();
        try {
            DB::beginTransaction();
            $entryModel = new Entry();
            $branchEntryModel = new BranchEntry();
            // 1. Gather all child entries uuids related to this entry (including the original entry_uuid)
            $entryUuids = $entryModel->getChildEntriesUuids($project->getId(), [$entryUuid], $entryUuid);

            //get all the branch entries uuids (to delete the branch media files)
            $branchEntryUuids = $branchEntryModel->getBranchEntriesUuids($project->getId(), $entryUuids);

            // 2. Delete all the hierarchy entries
            if (!Entry::where('project_id', $project->getId())
                ->whereIn('uuid', $entryUuids)
                ->delete()) {
                throw new Exception('Cannot delete hierarchy entries');
            }

            // 3. Update project stats
            $projectStats = new ProjectStats();
            if (!$projectStats->updateProjectStats($project->getId())) {
                throw new Exception('Cannot update project stats');
            }

            // 4. Update counters for hierarchy entries
            $entryCounter = new EntryCounter();
            if (!$entryCounter->updateCounters($project, $entryStructure)) {
                throw new Exception('Cannot update entries and branch entries counters');
            }

            //5. Delete media files
            //imp: merge hierarchy uuids with branch entries uuids
            //4. Delete media files
            if (config("filesystems.default") === 's3') {
                if (!$this->deleteMediaFilesS3($project, array_merge($entryUuids, $branchEntryUuids))) {
                    throw new Exception('Cannot delete media files S3');
                }
            }
            if (config("filesystems.default") === 'local') {
                if (!$this->deleteMediaFilesLocal($project, array_merge($entryUuids, $branchEntryUuids))) {
                    throw new Exception('Cannot delete media files local');
                }
            }

            // Now finally commit
            DB::commit();
            return true;
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
    }

    /**
     * @param ProjectDTO $project
     * @param $branchEntryUuid - branch entry uuid we need to archive
     * @param $entryStructure
     * @param bool $reuseExistingTransaction
     * @return bool
     * @throws Throwable
     */
    public function deleteBranchEntry(ProjectDTO $project, $branchEntryUuid, $entryStructure, bool $reuseExistingTransaction = false): bool
    {
        // If we don't want to keep open the transaction for further processing
        // when deleting hierarchy entry, we have a transaction
        // when deleting only a branch, we do not
        try {
            if (!$reuseExistingTransaction) {
                DB::beginTransaction();
            }
            // 1. Delete the branch entry
            BranchEntry::where('project_id', $project->getId())
                ->where('uuid', $branchEntryUuid)
                ->delete();
            // 2. Update project stats (only when deleting branch entry directly)
            if (!$reuseExistingTransaction) {
                $projectStats = new ProjectStats();
                if (!$projectStats->updateProjectStats($project->getId())) {
                    throw new Exception('Cannot update project stats (branch)');
                }
            }
            // 3. Update counters for branch entries
            $entryCounter = new BranchEntryCounter();
            if (!$entryCounter->updateCounters($project, $entryStructure)) {
                throw new Exception('Cannot update branch entries counters');
            }

            //4. Delete media files
            if (config("filesystems.default") === 's3') {
                if (!$this->deleteMediaFilesS3($project, [$branchEntryUuid])) {
                    throw new Exception('Cannot delete media files S3');
                }
            }
            if (config("filesystems.default") === 'local') {
                if (!$this->deleteMediaFilesLocal($project, [$branchEntryUuid])) {
                    throw new Exception('Cannot delete media files local');
                }
            }

            // If we don't want to keep open the transaction for further processing
            // Commit
            if (!$reuseExistingTransaction) {
                DB::commit();
            }
        } catch (Throwable $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
        return true;
    }

    private function deleteMediaFilesLocal(ProjectDTO $project, array $uuids): bool
    {
        //delete all files for this entry (matching the starting uuid)
        // Use DirectoryIterator to iterate through files one by one
        $drivers = config('epicollect.media.entries_deletable');
        $photoDeletedBytes = 0;
        $audioDeletedBytes = 0;
        $videoDeletedBytes = 0;
        $photoDeletedFiles = 0;
        $audioDeletedFiles = 0;
        $videoDeletedFiles = 0;
        foreach ($drivers as $driver) {
            // Get disk, path prefix and all directories for this driver
            $diskRoot = config('filesystems.disks.' . $driver . '.root').'/';
            try {
                $directory = new DirectoryIterator($diskRoot . $project->ref);
            } catch (Throwable) {
                //directory not found, so no media files, can skip safely
                continue;
            }
            foreach ($directory as $file) {
                // Skip directories and "." and ".." entries
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                // Check if the file starts with the specified prefix
                foreach ($uuids as $uuid) {
                    if (str_starts_with($file->getFilename(), $uuid)) {
                        // Get the full path of the file
                        $filePath = $file->getPathname();
                        // Delete the file
                        $fileSize = $file->getSize();
                        if (File::exists($filePath)) {
                            if (!File::delete($filePath)) {
                                // Log an error if file deletion fails
                                Log::error("Failed to delete file: " . $filePath);
                                return false;
                            }
                            //track bytes deleted
                            match ($driver) {
                                'photo' => $photoDeletedBytes += $fileSize,
                                'audio' => $audioDeletedBytes += $fileSize,
                                'video' => $videoDeletedBytes += $fileSize
                            };
                            //track files deleted
                            match ($driver) {
                                'photo' => $photoDeletedFiles++,
                                'audio' => $audioDeletedFiles++,
                                'video' => $videoDeletedFiles++
                            };
                        }
                    }
                }
            }
        }

        $totalDeletedBytes = $photoDeletedBytes + $audioDeletedBytes + $videoDeletedBytes;
        if ($totalDeletedBytes > 0) {
            //adjust total bytes (negative delta)
            ProjectStats::where('project_id', $project->getId())
                ->first()
                ->decrementMediaStorageUsage(
                    $photoDeletedBytes,
                    $photoDeletedFiles,
                    $audioDeletedBytes,
                    $audioDeletedFiles,
                    $videoDeletedBytes,
                    $videoDeletedFiles,
                );
        }

        return true;
    }

    private function deleteMediaFilesS3(ProjectDTO $project, array $uuids): bool
    {
        $disks = config('epicollect.media.entries_deletable');
        $photoDeletedBytes = 0;
        $audioDeletedBytes = 0;
        $videoDeletedBytes = 0;
        $photoDeletedFiles = 0;
        $audioDeletedFiles = 0;
        $videoDeletedFiles = 0;
        foreach ($disks as $disk) {

            $diskPath = Storage::disk($disk);
            $prefix = rtrim($project->ref, '/') . '/';

            try {
                // List all files under the S3 "directory"
                $allFiles = $diskPath->files($prefix);

                // Filter only files that start with one of the UUIDs
                $filesToDelete = array_filter($allFiles, function ($path) use ($uuids) {
                    $filename = basename($path);

                    foreach ($uuids as $uuid) {
                        if (str_starts_with($filename, $uuid)) {
                            return true;
                        }
                    }

                    return false;
                });

                if (!empty($filesToDelete)) {

                    // Get total bytes before deletion
                    foreach ($filesToDelete as $file) {
                        $size = $diskPath->size($file);
                        match ($disk) {
                            'photo' => $photoDeletedBytes += $size,
                            'audio' => $audioDeletedBytes += $size,
                            'video' => $videoDeletedBytes += $size
                        };
                        match ($disk) {
                            'photo' => $photoDeletedFiles++,
                            'audio' => $audioDeletedFiles++,
                            'video' => $videoDeletedFiles++
                        };
                    }
                    $diskPath->delete($filesToDelete);
                }
            } catch (Throwable $e) {
                Log::error("deleteMediaFilesS3 failed for driver [$disk], project [$project->ref]: " . $e->getMessage());
                return false;
            }
        }

        $totalDeletedBytes = $photoDeletedBytes + $audioDeletedBytes + $videoDeletedBytes;
        if ($totalDeletedBytes > 0) {
            //adjust total bytes (negative delta)
            ProjectStats::where('project_id', $project->getId())
                ->first()
                ->decrementMediaStorageUsage(
                    $photoDeletedBytes,
                    $photoDeletedFiles,
                    $audioDeletedBytes,
                    $audioDeletedFiles,
                    $videoDeletedBytes,
                    $videoDeletedFiles,
                );
        }

        return true;
    }
}
