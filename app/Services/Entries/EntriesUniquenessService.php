<?php

namespace ec5\Services\Entries;

use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\Traits\Eloquent\Entries;
use PDO;

class EntriesUniquenessService
{
    use Entries;

    /**
     * Function for checking whether an answer for an input is unique
     * We will check this against any parent form entries, if required
     *
     * @param EntryStructureDTO $entryStructure
     * @param string $uniquenessType
     * @param string|int $inputRef
     * @param string|int $answer
     * @param null $inputType
     * @param null $datetimeFormat
     * @return bool
     */
    public function isUnique(EntryStructureDTO $entryStructure, string $uniquenessType, $inputRef, $answer, $inputType = null, $datetimeFormat = null): bool
    {
        //set table based on whether is an entry or branch_entry
        $table = config('epicollect.tables.entries');
        if ($entryStructure->isBranch()) {
            $table = config('epicollect.tables.branch_entries');
        }

        $pdo = DB::connection()->getPdo();

        $query = 'SELECT uuid, user_id, device_id FROM ' . $table . ' WHERE project_id = ? AND form_ref = ? ';

        $queryParams = [$entryStructure->getProjectId(), $entryStructure->getFormRef()];

        // What type of uniqueness check needs to be made
        switch ($uniquenessType) {

            case 'hierarchy':
                $query .= ' AND parent_uuid = ? ';
                $queryParams[] = $entryStructure->getParentUuid();
                break;
            case 'form':
                // If we have a branch, check against the owner entry uuid
                if ($entryStructure->isBranch()) {
                    $query .= ' AND owner_uuid = ? ';
                    $queryParams[] = $entryStructure->getOwnerUuid();
                }
                break;
        }

        switch ($inputType) {
            case  config('epicollect.strings.inputs_type.time'):

                //for time inputs we compare only the time based on the format
                //time answers are like "2011-10-05T14:48:00.000"
                $timePart = substr($answer, 10, 10);
                //time part is like "T14:48:00."
                $formats = config('epicollect.strings.datetime_format');

                // if format is 'HH:mm',
                if ($datetimeFormat === $formats[$datetimeFormat]) {
                    $timePart = substr($timePart, 0, 7);
                    //time part is now like "T14:48:"
                }
                // if format is 'hh:mm',
                if ($datetimeFormat === $formats[$datetimeFormat]) {
                    $timePart = substr($timePart, 0, 7);
                    //time part is now like "T14:48:"
                }
                // if format is 'mm:ss',
                if ($datetimeFormat === $formats[$datetimeFormat]) {
                    $timePart = substr($timePart, 3, 7);
                    //time part is like ":48:00."
                }

                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query .= ' AND lcase(json_unquote(JSON_EXTRACT(entry_data, \'$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"\'))) LIKE ? LIMIT 1;';
                $queryParams[] = strtolower('%' . $timePart . '%');
                break;

            case  config('epicollect.strings.inputs_type.date'):

                $datePart = substr($answer, 0, 11);
                //datePart is now 2011-10-05T
                $formats = config('epicollect.strings.datetime_format');

                // if the format is 'MM/YYYY',
                if ($datetimeFormat === $formats[$datetimeFormat]) {
                    $datePart = substr($datePart, 0, 8);
                    //datePart is now 2011-10-
                }

                //if format is 'dd/MM',
                if ($datetimeFormat === $formats[$datetimeFormat]) {
                    $datePart = substr($datePart, 4, 10);
                    //datePart is now -10-05T
                }

                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query .= ' AND lcase(json_unquote(JSON_EXTRACT(entry_data, \'$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"\'))) LIKE ? LIMIT 1;';
                $queryParams[] = strtolower('%' . $datePart . '%');
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
                 *
                 */
                $query .= ' AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(entry_data, \'$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"\'))) = LOWER(?) LIMIT 1;';
                $queryParams[] = $answer;
        }
        // Retrieve all the entries that match
        $stmt = $pdo->prepare($query);
        $stmt->execute($queryParams);
        // We want an stdClass object here
        $dbEntry = $stmt->fetch(PDO::FETCH_OBJ);

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