@php
    $projectSlug = $requestAttributes->requestedProject->slug;
    $projectExtra = $requestAttributes->requestedProject->getProjectExtra();
    /**
     * @var $projectStats \ec5\DTO\ProjectStatsDTO
     */
    $projectStats = $requestAttributes->requestedProject->getProjectStats();
@endphp
<div class="panel-body project-home__project-view-btn">
    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-4 project-home__stats">
            <i class="fa fa-database fa-2x fa-fw" aria-hidden="true"></i>
            <br/>
            {{  Common::roundNumber($projectStats->total_entries, 1)  }} Entries
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4 project-home__stats">
            <i class="fa fa-calendar fa-2x fa-fw"></i>
            <br/>
            Last on:
            <br/>
            {{ $mostRecentEntryTimestamp ? date('j M Y', intval($mostRecentEntryTimestamp)) : '-' }}
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4 project-home__action-btns">
            <a class="btn btn-action"
               href="{{ url('project/' . $projectSlug . '/data') }}">
                <span class="material-icons">table_view</span>
                {{ trans('site.view_data') }}
            </a>
        </div>
    </div>
</div>
