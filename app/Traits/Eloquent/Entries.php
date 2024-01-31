<?php

namespace ec5\Traits\Eloquent;

use Carbon\Carbon;
use DB;
use ec5\DTO\EntryStructureDTO;
use ec5\Http\Validation\Entries\Upload\RuleAnswers;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Log;

trait Entries
{

    /**
     * Search for entries based on answers
     *
     * @param Builder $q
     * @param $options
     * @return Builder
     */
    protected function createFilterOptions(Builder $q, $options): Builder
    {
        $inputRef = (empty($options['input_ref'])) ? null : $options['input_ref'];
        $search = (empty($options['search'])) ? null : $options['search'];

        if ($inputRef == null || $search == null) {
            return $q;
        }

        $sql = ' json_unquote(JSON_EXTRACT(entry_data, \'$.entry."';
        $q->whereRaw($sql . $inputRef . '"."answer"\')) = ?', [$search]);

        return $q;
    }

    public static function sortAndFilterEntries(Builder $q, $filters): Builder
    {
        // Filtering
        if (!empty($filters['filter_by'])) {
            // Filter between
            if (!empty($filters['filter_from']) && !empty($filters['filter_to'])) {
                //create artificial dates using Carbon modifiers
                //to include the full range of entries
                $from = Carbon::parse($filters['filter_from'])->startOfDay();
                $to = Carbon::parse($filters['filter_to'])->endOfDay();
                $q->whereBetween($filters['filter_by'], [$from, $to]);
            } // Filter from
            else {
                if (!empty($filters['filter_from'])) {
                    $q->where($filters['filter_by'], '>=', $filters['filter_from']);
                } // Filter to
                else {
                    if (!empty($filters['filter_to'])) {
                        $q->where($filters['filter_by'], '<=', $filters['filter_to']);
                    }
                }
            }
        }
        //filter by title
        if (!empty($filters['title'])) {
            $q->where('title', 'LIKE', '%' . $filters['title'] . '%');
        }
        // Sorting
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            if ($filters['sort_by'] === 'title') {
                //handle the natural sort on alphanumeric titles -> t.ly/tl5X
                $q->orderByRaw('LENGTH(' . $filters['sort_by'] . ') ' . $filters['sort_order'] . ' , ' . $filters['sort_by'] . ' ' . $filters['sort_order']);
            } else {
                $q->orderBy($filters['sort_by'], $filters['sort_order']);
            }
        } else {
            //default sorting, most recent first
            $q->orderBy('created_at', 'DESC');
        }
        return $q;
    }

    /**
     * @param Request $request
     * @param $perPage
     * @return array
     */
    public function getRequestParams(Request $request, $perPage): array
    {
        $params = [];
        foreach ($this->allowedSearchKeys as $k) {
            $params[$k] = $request->get($k) ?? '';
        }

        // Defaults for sort by and sort order
        $params['sort_by'] = !empty($params['sort_by']) ? $params['sort_by'] : config('epicollect.mappings.search_data_entries_defaults.sort_by');
        $params['sort_order'] = !empty($params['sort_order']) ? $params['sort_order'] : config('epicollect.mappings.search_data_entries_defaults.sort_order');

        // Set defaults
        if (empty($params['per_page'])) {
            $params['per_page'] = $perPage;
        }
        if (empty($params['page'])) {
            $params['page'] = 1;
        }

        // Check user project role
        // Collectors can only view their own data in private projects
        if (
            $this->requestedProject()->isPrivate()
            && $this->requestedProjectRole()->isCollector()
        ) {
            $params['user_id'] = $this->requestedProjectRole()->getUser()->id;
        }

        // Set default form_ref (first form), if not supplied
        if (empty($params['form_ref'])) {
            $params['form_ref'] = $this->requestedProject()->getProjectDefinition()->getFirstFormRef();
        }

        //if no map_index provide, return default map (check of empty string, as 0 is a valid map index)
        if ($params['map_index'] === '') {
            $params['map_index'] = $this->requestedProject()->getProjectMapping()->getDefaultMapIndex();
        }

        // Format of the data i.e., json, csv
        $params['format'] = !empty($params['format']) ? $params['format'] : config('epicollect.mappings.search_data_entries_defaults.format');
        // Whether to include headers for csv
        $params['headers'] = !empty($params['headers']) ? $params['headers'] : config('epicollect.mappings.search_data_entries_defaults.headers');

        return $params;
    }

    /**
     * @param array $params - Request options
     * @return bool
     */
    protected function validateParams(array $params): bool
    {
        $this->ruleQueryString->validate($params);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }
        // Do additional checks
        $this->ruleQueryString->additionalChecks($this->requestedProject(), $params);
        if ($this->ruleQueryString->hasErrors()) {
            $this->validateErrors = $this->ruleQueryString->errors();
            return false;
        }

        $inputRef = (empty($params['input_ref'])) ? '' : $params['input_ref'];

        if (empty($inputRef)) {
            return true;
        }

        // Otherwise, check if valid value i.e., date is date min max etc.
        //$inputType =$this->requestedProject()->getProjectExtra()->getInputDetail($inputRef, 'type');
        $value = $params['search'];
        //$value2 = $params['search_two'];

        return $this->validateValue($inputRef, $value);
    }

    /**
     * Validate the value of an input
     * Using the RuleAnswers validation class
     *
     * @param string $inputRef
     * @param string $value
     * @return bool
     */
    public function validateValue(string $inputRef, string $value, RuleAnswers $ruleAnswers): bool
    {
        $input = $this->requestedProject()->getProjectExtra()->getInputData($inputRef);
        $this->ruleAnswers->validateAnswer($input, $value, $this->requestedProject());
        if ($this->ruleAnswers->hasErrors()) {
            $this->validateErrors = $this->ruleAnswers->errors();
            return false;
        }
        return true;
    }

    public function storeEntry(EntryStructureDTO $entryStructure, $entry): int
    {
        // Set the entry params to be added
        $entry['uuid'] = $entryStructure->getEntryUuid();
        $entry['form_ref'] = $entryStructure->getFormRef();
        $entry['created_at'] = $entryStructure->getDateCreated();
        $entry['project_id'] = $entryStructure->getProjectId();
        $entry['device_id'] = $entryStructure->getHashedDeviceId();
        $entry['platform'] = $entryStructure->getPlatform();
        $entry['user_id'] = $entryStructure->getUserId();

        //Save entry to database
        try {
            $table = config('epicollect.tables.entries');
            if ($entryStructure->isBranch()) {
                $table = config('epicollect.tables.branch_entries');
            }
            return DB::table($table)->insertGetId($entry);
        } catch (Exception $e) {
            Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
            return 0;
        }
    }
}
