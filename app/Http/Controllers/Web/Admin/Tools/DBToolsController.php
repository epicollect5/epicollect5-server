<?php

/**
 * Created by PhpStorm.
 * User: mirko
 * Date: 06/08/2020
 * Time: 13:21
 */

namespace ec5\Http\Controllers\Web\Admin\Tools;

use Carbon\Carbon;
use DB;
use ec5\Models\Entries\Entry;
use ec5\Models\Project\Project;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;


class DBToolsController
{
    public function getEntries(Request $request)
    {
        $params = $request->all();

        if (isset($params['project_id'])) {
            $projectId = $params['project_id'];
        } else {
            return 'Missing project_id parameter';
        }

        $entryModel = new Entry();
        $entries = $entryModel::where('project_id', $projectId)->get();

        return $entries;
    }

    public function copyEntries(Request $request)
    {
        // bestpint project id: 7
        $params = $request->all();

        if (isset($params['source_project_id']) && isset($params['destination_project_id'])) {
            $sourceProjectId = $params['source_project_id'];
            $destinationProjectId = $params['destination_project_id'];
        } else {
            return 'Missing project_id(s) parameters';
        }


        $entryModel = new Entry();
        $sourceProjectModel = Project::find($sourceProjectId);
        $destinationProjectModel = Project::find($destinationProjectId);

        if ($sourceProjectModel === null || $destinationProjectModel === null) {
            return 'Error finding models, passing correct project id(s)?';
        }

        $sourceProjectRef = $sourceProjectModel->ref;
        $destinationProjectRef = $destinationProjectModel->ref;

        $sourceEntries = $entryModel::where('project_id', $sourceProjectId)->get();

        foreach ($sourceEntries as $sourceEntry) {

            $destinationEntryUuid = Uuid::uuid4()->toString();

            $destinationEntry = new Entry();
            $destinationEntry->project_id = $destinationProjectId;
            $destinationEntry->uuid = $destinationEntryUuid;
            $destinationEntry->title = $sourceEntry->title;
            $destinationEntry->parent_uuid = $sourceEntry->parent_uuid;
            //form ref with string replace
            $destinationEntry->form_ref = str_replace($sourceProjectRef, $destinationProjectRef, $sourceEntry->form_ref);

            $destinationEntry->parent_form_ref = $sourceEntry->parent_form_ref;
            $destinationEntry->user_id = $sourceEntry->user_id;
            $destinationEntry->platform = $sourceEntry->platform;
            $destinationEntry->device_id = $sourceEntry->device_id;
            $destinationEntry->created_at = $sourceEntry->created_at;
            $destinationEntry->uploaded_at = $sourceEntry->uploaded_at;

            $destinationEntry->geo_json_data = $sourceEntry->geo_json_data;
            $destinationEntry->child_counts = $sourceEntry->child_counts;
            $destinationEntry->branch_counts = $sourceEntry->branch_counts;

            //now the entry data....
            // JSON encode the data
            $jsonData = json_encode($sourceEntry->entry_data);
            // Replace the old ref with the new ref in the data
            $jsonData = str_replace($sourceProjectRef, $destinationProjectRef, $jsonData);
            //replace uuid with new one
            $jsonData = str_replace($sourceEntry->uuid, $destinationEntryUuid, $jsonData);
            // Replace 'this' data
            $destinationEntry->entry_data = json_decode($jsonData, true);

            try {
                $destinationEntry->save();
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }

        //        //count entries on destination project and update stats
        //        $destinationEntries = Entry::where('project_id', $destinationProjectId);
        //        $destinationProjectStats = ProjectStats::where('project_id', $destinationProjectId);
        //
        //        $destinationProjectStats->total_entries = $destinationEntries->count();
        //        $destinationProjectStats->save();

        return 'Copied ' . $sourceEntries->count() . ' entries :)';
    }

    public function getDBSize()
    {

        $total = 0;

        //        $sizeOfCurrentProjectDatabse = DB::table('information_schema.TABLES')
        //            ->select(['TABLE_NAME as TableName', 'table_rows as TableRows', 'data_length as DataLength', 'index_length as IndexLength'])
        //            ->where('information_schema.TABLES.table_schema', '=', config('database.connections.' . config('database.default') . '.database'))
        //            ->get()
        //            ->map(function ($eachDatabse) use (&$total) {
        //
        //                $dataIndex = $eachDatabse->DataLength + $eachDatabse->IndexLength;
        //
        //                $modifiedObject = new \StdClass;
        //                $kbSize = ($dataIndex / 1024);
        //                $mbSize = ($kbSize / 1024);
        //                $gbSize = ($mbSize / 1024);
        //                $modifiedObject->SizeInKb = $kbSize;
        //                $modifiedObject->SizeInMB = $mbSize;
        //
        //                $total += $gbSize;
        //
        //                return (object)array_merge((array)$eachDatabse, (array)$modifiedObject);
        //
        //            })
        //            ->keyBy('TableName');

        $size = DB::select('SELECT SUM(ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 / 1024), 2)) AS "gb_size" FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = "epicollect5";');

        return $size[0]->gb_size;
        //dd((float)number_format($size[0]->gb_size, 2));
    }

    public function getUsersToday()
    {

        dd(Carbon::yesterday());
        //count in sql is faster than eloquent, use raw!
        return DB::table('users')
            ->select([DB::raw('count(id) as users_total')])
            ->where('users' . '.created_at', '>=', Carbon::yesterday())
            ->get();
    }
}
