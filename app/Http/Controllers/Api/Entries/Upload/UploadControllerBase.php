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
    protected bool $isBulkUpload;
    protected EntriesUploadService $entriesUploadService;
    protected EntryStructureDTO $entryStructure;
    protected array $errors = [];
    protected RuleUpload $ruleUpload;
    protected string $storageDriver;

    /**
     * @param EntryStructureDTO $entryStructure
     * @param RuleFileEntry $ruleFileEntry
     * @param RuleUpload $ruleUpload
     */
    public function __construct(
        EntryStructureDTO $entryStructure,
        RuleFileEntry     $ruleFileEntry,
        RuleUpload        $ruleUpload
    ) {
        $this->ruleFileEntry = $ruleFileEntry;
        $this->entryStructure = $entryStructure;
        $this->ruleUpload = $ruleUpload;
        $this->isBulkUpload = false;
        $this->entriesUploadService = new EntriesUploadService(
            $this->entryStructure,
            $this->ruleUpload,
            $this->isBulkUpload
        );
        $this->storageDriver = config('filesystems.default');
    }
}
