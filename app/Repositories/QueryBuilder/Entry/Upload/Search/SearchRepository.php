<?php

namespace ec5\Repositories\QueryBuilder\Entry\Upload\Search;

use ec5\Models\Entries\EntryStructure;

use DB;
use PDO;
use Config;

abstract class SearchRepository
{

    /**
     * @var
     */
    protected $table;

    /**
     * @var bool
     */
    protected $isBranchEntry;

    /**
     * SearchRepository constructor.
     * @param $table
     * @param $isBranchEntry
     */
    public function __construct($table, $isBranchEntry)
    {
        $this->table = $table;
        $this->isBranchEntry = $isBranchEntry;
    }

    /**
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($columns = array('*'))
    {
        //
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return mixed
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return DB::table($this->table)->where($column, $operator, $value, $boolean)->first();
    }

    /**
     * @param $field
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($field, $value, $columns = array('*'))
    {
        //
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return mixed
     */
    public function findAllBy($column, $operator = null, $value = null, $boolean = 'and')
    {
        return DB::table($this->table)->where($column, $operator, $value, $boolean)->orderBy('id', 'desc')->get();
    }

    /**
     * @param $id
     * @param $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        //
    }

    /**
     * Function for retrieving paginated entries
     *
     * @param int $perPage
     * @param int $currentPage
     * @param string $search
     * @param array $options
     * @param array $columns
     * @return array
     */
    public function paginate($perPage = 1, $currentPage = 1, $search = '', $options = array(), $columns = array('*'))
    {
        //
    }

    /**
     * Function for checking whether an answer for an input is unique
     * We will check this against any parent form entries, if required
     *
     * @param EntryStructure $entryStructure
     * @param string $uniquenessType
     * @param string|int $inputRef
     * @param string|int $answer
     * @return bool
     */
    public function isUnique(EntryStructure $entryStructure, string $uniquenessType, $inputRef, $answer, $inputType = null, $datetimeFormat = null): bool
    {

        $pdo = DB::connection()->getPdo();

        $query = 'SELECT uuid, user_id, device_id FROM ' . $this->table . ' WHERE project_id = ? AND form_ref = ? ';

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
            case  Config::get('ec5Strings.inputs_type.time'):

                //for time inputs we compare only the time based on the format
                //time answers are like "2011-10-05T14:48:00.000"
                $timePart = substr($answer, 10, 10);
                //time part is like "T14:48:00."
                $formats = Config::get('ec5Enums.datetime_format');

                // if format is 'HH:mm',
                if ($datetimeFormat ===  $formats[7]) {
                    $timePart = substr($timePart, 0, 7);
                    //time part is now like "T14:48:"
                }
                // if format is 'hh:mm',
                if ($datetimeFormat === $formats[8]) {
                    $timePart = substr($timePart, 0, 7);
                    //time part is now like "T14:48:"
                }
                // if format is 'mm:ss',
                if ($datetimeFormat === $formats[9]) {
                    $timePart = substr($timePart, 3, 7);
                    //time part is like ":48:00."
                }

                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query .= ' AND lcase(json_unquote(JSON_EXTRACT(entry_data, \'$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"\'))) LIKE ? LIMIT 1;';
                $queryParams[] = strtolower('%' . $timePart . '%');
                break;

            case  Config::get('ec5Strings.inputs_type.date'):

                $datePart = substr($answer, 0, 11);
                //datePart is now 2011-10-05T
                $formats = Config::get('ec5Enums.datetime_format');

                // if format is 'MM/YYYY',
                if ($datetimeFormat === $formats[3]) {
                    $datePart = substr($datePart, 0, 8);
                    //datePart is now 2011-10-
                }

                //if format is 'dd/MM',
                if ($datetimeFormat === $formats[4]) {
                    $datePart = substr($datePart, 4, 10);
                    //datePart is now -10-05T
                }

                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query .= ' AND lcase(json_unquote(JSON_EXTRACT(entry_data, \'$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"\'))) LIKE ? LIMIT 1;';
                $queryParams[] = strtolower('%' . $datePart . '%');
                break;

            default:
                // Use entry type when json extracting, all lowercase (both needle and haystack)
                $query .= ' AND lcase(json_unquote(JSON_EXTRACT(entry_data, \'$."' . $entryStructure->getEntryType() . '"."' . 'answers' . '"."' . $inputRef . '"."answer"\'))) = ? LIMIT 1;';
                $queryParams[] = strtolower($answer);
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

    /**
     * Get the parent given a parent entry uuid and form ref
     *
     * @param $parentEntryUuid
     * @param $parentFormRef
     * @return mixed
     */
    public function getParentEntry($parentEntryUuid, $parentFormRef)
    {
        //
    }
}
