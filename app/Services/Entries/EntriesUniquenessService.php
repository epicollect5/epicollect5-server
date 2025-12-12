<?php

namespace ec5\Services\Entries;

use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\Traits\Eloquent\Entries;

class EntriesUniquenessService
{
    use Entries;

    /**
     * Function for checking whether an answer for a question is unique
     * We will check this against any parent form entries, if required
     *
     */
    public function isUnique(
        EntryStructureDTO $entryStructure,
        string $uniquenessType,
        string $inputRef,
        string $answer,
        $inputType = null,
        $datetimeFormat = null
    ): bool {
        // set table based on whether is an entry or branch_entry
        $table = config('epicollect.tables.entries');
        $tableJson = config('epicollect.tables.entries_json');
        if ($entryStructure->isBranch()) {
            $table = config('epicollect.tables.branch_entries');
            $tableJson = config('epicollect.tables.branch_entries_json');
        }

        // derive COALESCE expression for unified lookup
        $coalesced = "COALESCE(e.entry_data, ej.entry_data)";

        $query = DB::query()
            ->from("$table as e")
            ->leftJoin("$tableJson as ej", 'e.id', '=', 'ej.entry_id')
            ->where('e.project_id', $entryStructure->getProjectId())
            ->where('e.form_ref', $entryStructure->getFormRef());

        // What type of uniqueness check needs to be made
        switch ($uniquenessType) {

            case 'hierarchy':
                $query->where('e.parent_uuid', $entryStructure->getParentUuid());
                break;

            case 'form':
                // If we have a branch, check against the owner entry uuid
                if ($entryStructure->isBranch()) {
                    $query->where('e.owner_uuid', $entryStructure->getOwnerUuid());
                }
                break;
        }

        // JSON path for the answer field
        $jsonPath = '$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"';

        // What input type needs to be checked
        switch ($inputType) {

            case config('epicollect.strings.inputs_type.time'):

                // for time inputs we compare only the time based on the format
                // time answers are like "2011-10-05T14:48:00.000"
                $timePart = substr($answer, 10, 10);
                // time part is like "T14:48:00."
                $formats = config('epicollect.strings.datetime_format');

                // if format is 'HH:mm',
                if ($datetimeFormat === $formats['HH:mm']) {
                    $timePart = substr($timePart, 0, 7);
                    // time part is now like "T14:48:"
                }

                // if format is 'hh:mm',
                if ($datetimeFormat === $formats['hh:mm']) {
                    $timePart = substr($timePart, 0, 7);
                    // time part is now like "T14:48:"
                }

                // if format is 'mm:ss',
                if ($datetimeFormat === $formats['mm:ss']) {
                    $timePart = substr($timePart, 3, 7);
                    // time part is like ":48:00."
                }

                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT($coalesced, ?))) LIKE LOWER(?)",
                    [$jsonPath, '%' . strtolower($timePart) . '%']
                )->limit(1);

                break;

            case config('epicollect.strings.inputs_type.date'):

                $datePart = substr($answer, 0, 11);
                // datePart is now 2011-10-05T
                $formats = config('epicollect.strings.datetime_format');

                // if the format is 'MM/YYYY',
                if ($datetimeFormat === $formats['MM/YYYY']) {
                    $datePart = substr($datePart, 0, 8);
                    // datePart is now 2011-10-
                }

                // if format is 'dd/MM',
                if ($datetimeFormat === $formats['dd/MM']) {
                    $datePart = substr($datePart, 4, 10);
                    // datePart is now -10-05T
                }

                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT($coalesced, ?))) LIKE LOWER(?)",
                    [$jsonPath, '%' . strtolower($datePart) . '%']
                )->limit(1);

                break;

            default:
                // Use the entry type when json extracting, all lowercase (both needle and haystack)

                /**
                 * The LOWER function in SQL generally handles non-ASCII characters like accents correctly.
                 * When you use LOWER on a string in SQL, it typically converts all characters to lowercase
                 * according to the rules of the collation being used.
                 *
                 * For example, in MySQL, using a collation like utf8mb4_unicode_ci will correctly handle
                 * lowercase conversions for characters with accents and other diacritics.
                 * This means that characters like "é", "à", "ü", etc.,
                 * will be converted to their lowercase equivalents as expected.
                 *
                 * So, in this case, using LOWER in your SQL query should handle non-ASCII characters
                 * and accents properly when converting the string to lowercase.
                 *
                 * (Or use mb_strtolower instead of strtolower to handle accents)
                 */
                $query->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT($coalesced, ?))) = LOWER(?)",
                    [$jsonPath, $answer]
                )->limit(1);
        }

        // Retrieve all the entries that match
        $dbEntry = $query->select('e.uuid', 'e.user_id', 'e.device_id')->first();

        // If an entry is found
        if ($dbEntry) {
            // If the uuid is the same, check if it can be edited
            if ($dbEntry->uuid == $entryStructure->getEntryUuid()) {
                return $entryStructure->canEdit($dbEntry);
            }
            // Otherwise can't edit
            return false;
        }

        // If no entry found, answer is unique
        return true;
    }
}
