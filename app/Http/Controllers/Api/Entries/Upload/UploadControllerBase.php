<?php

declare(strict_types=1);

namespace ec5\Http\Controllers\Api\Entries\Upload;

use ec5\DTO\EntryStructureDTO;
use ec5\Http\Validation\Entries\Upload\RuleFileEntry;
use ec5\Http\Validation\Entries\Upload\RuleUpload;
use ec5\Services\Entries\EntriesUploadService;
use ec5\Traits\Requests\RequestAttributes;

abstract class UploadControllerBase
{
    use RequestAttributes;

    protected RuleFileEntry $ruleFileEntry;
    protected EntriesUploadService $entriesUploadService;
    protected EntryStructureDTO $entryStructure;
    protected array $errors = [];
    protected RuleUpload $ruleUpload;
    protected string $storageDriver;

    /**
     * Initializes the base upload controller with entry structure, file entry rules, and upload rules.
     *
     * Sets up the entries upload service and determines the default storage driver from configuration.
     *
     * @param EntryStructureDTO $entryStructure Data transfer object representing the entry structure.
     * @param RuleFileEntry $ruleFileEntry Validation rules for file entries.
     * @param RuleUpload $ruleUpload Validation rules for the upload process.
     */
    public function __construct(
        EntryStructureDTO $entryStructure,
        RuleFileEntry     $ruleFileEntry,
        RuleUpload        $ruleUpload
    ) {
        $this->ruleFileEntry = $ruleFileEntry;
        $this->entryStructure = $entryStructure;
        $this->ruleUpload = $ruleUpload;
        $this->entriesUploadService = new EntriesUploadService(
            $this->entryStructure,
            $this->ruleUpload
        );
        $this->storageDriver = config('filesystems.default');
    }
}
