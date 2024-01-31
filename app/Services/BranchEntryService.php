<?php

namespace ec5\Services;

use ec5\DTO\EntryStructureDTO;
use ec5\Traits\Eloquent\Entries;

class BranchEntryService
{
    use Entries;

    public function storeBranchEntry(EntryStructureDTO $entryStructure, $entry): int
    {
        // Add additional keys/values of branch entries
        $entry['owner_entry_id'] = $entryStructure->getBranchOwnerEntryDbId();
        $entry['owner_uuid'] = $entryStructure->getOwnerUuid();
        $entry['owner_input_ref'] = $entryStructure->getOwnerInputRef();

        return $this->storeEntry($entryStructure, $entry);
    }
}