<div class="panel-body project-home__project-view-btn">
    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-6 project-home__stats">
            <i class="fa fa-database fa-2x fa-fw" aria-hidden="true"></i>
            <br />
            {{ Common::roundNumber($projectStats->getTotalEntries(), 1) }} Entries
        </div>
        <div class="col-xs-12 col-sm-12 col-md-6 project-home__stats">
            <i class="fa fa-calendar fa-2x fa-fw"></i>
            <br />
            Last on:

            @if (!$lastEntryDate)
                <span> - </span>
            @else
                {{ date('j M y', intval($lastEntryDate)) }}
            @endif

        </div>
    </div>

    <hr>

    <div class="row">

        <div class="col-xs-12 col-sm-12 col-md-6 project-home__action-btns text-center">
            <a class="btn btn-action" href="{{ url('/myprojects/' . $project->slug) }}">
                {{ trans('site.details') }}
            </a>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-6 project-home__action-btns text-center">
            <a class="btn btn-action" href="{{ url('project/' . $project->slug . '/data') }}">
                {{ trans('site.view_data') }}
            </a>
        </div>

    </div>
</div>
