<?php

namespace ec5\Libraries\EC5Logger;

use ec5\Models\Projects\Project;
use Log;

class EC5Logger
{

    /**
     * @param $title
     * @param Project|null $project
     * @return string
     */
    public static function makeTitle($title, Project $project = null)
    {
        if ($project) {
            return '*** ' . $title . ': "' . $project->name. '"';
        }
        return '*** ' . $title;
    }

    /**
     * @param $title
     * @param Project|null $project
     * @param array $data
     */
    public static function error($title, Project $project =  null, $data = [])
    {
        //send only critical errors to Slack
        Log::critical(self::makeTitle($title, $project), $data);
    }

    /**
     * @param $title
     * @param Project|null $project
     * @param array $data
     */
    public static function info($title, Project $project =  null, $data = [])
    {
        Log::info(self::makeTitle($title, $project), $data);
    }
}
