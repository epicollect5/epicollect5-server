<div class="page-home__server-stats row">
    <h3 class="text-center server-stats__stats-teaser">Thousands of people use Epicollect5 to collect data for thousands
        of projects every day.</h3>
    <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 text-center server-stats__stats-wrapper">
        <div class="circle stats-users">
            <div class="circle-content">
                <p><i class="material-icons">people</i></p>
                <h4>Users</h4>
                <p><strong>{{ $users }}</strong></p>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 text-center server-stats__stats-wrapper">
        <div class="circle stats-projects">
            <div class="circle-content">
                <p><i class="material-icons">list_alt</i></p>
                <h4>Projects</h4>
                <p><strong>{{ $projects }}</strong></p>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 text-center server-stats__stats-wrapper">
        <div class="circle stats-entries">
            <div class="circle-content">
                <p><i class="material-icons">cloud_upload</i></p>
                <h4>Entries</h4>
                <p><strong>{{ $entries }}</strong></p>
            </div>
        </div>
    </div>
</div>

<hr>

<div class="page-home__find-project">
    <h2 class="text-center">Have a look at our featured projects below or
        <a href="{{ url('/projects/') }}" class="btn btn-default btn-action-inverse btn-lg">find a project</a>
    </h2>
</div>
<hr>

<div class="row page-home-featured-projects-small">

    @foreach ($projectsFirstRow as $project)
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <a href="{{ url('project/' . $project->slug) }}" class="thumbnail">
                        <img class="img-responsive img-circle" width="128" height="128"
                             src="{{ $project->logo_base64 }}"
                             alt="{{ $project->name }}">
                        <div class="loader"></div>
                    </a>

                    <span class="project-name">{{ $project->name }}</span>

                    <div class="project-small-description text-center">
                        {{ $project->small_description }}
                    </div>
                    <div class="clearfix"></div>
                    <div class="page-home-view-btn">
                        <a class="btn btn-action pull-right"
                           href="{{ url('project/' . $project->slug) }}">{{ trans('site.view') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Only show Community column if featured project total is 7 (first row is 3) --}}
    @if($projectsFirstRow->count() === 3)
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
            <div class="panel panel-default">
                <div class="panel-body">
                    <a href="https://community.epicollect.net" class="thumbnail" target="_blank"
                       rel="noopener noreferrer">
                        <img class="img-responsive img-circle" width="128" height="128"
                             src="{{ static_asset('/images/epicollect5-rounded-no-borders.jpg') }}"
                             alt="Community Logo">
                        <div class="loader"></div>
                    </a>

                    <span class="project-name">Do You Have Any Questions?</span>

                    <div class="project-small-description text-center">
                        Ask Our Community
                    </div>
                    <div class="clearfix"></div>
                    <div class="page-home-view-btn">
                        <a class="btn btn-action"
                           href="https://community.epicollect.net"
                           rel="noopener noreferrer"
                           target="_blank"
                        >
                            {{ trans('site.join_community') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Display second project row only if we have 4 projects featured --}}
@if (count($projectsSecondRow) === 4)
    <div class="row page-home-featured-projects-small">
        @foreach ($projectsSecondRow as $project)
            <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <a href="{{ url('project/' . $project->slug) }}" class="thumbnail">
                            <img class="img-responsive img-circle" width="128" height="128"
                                 src="{{ $project->logo_base64 }}"
                                 alt="{{ $project->name }}">

                            <div class="loader"></div>
                        </a>

                        <span class="project-name">{{ $project->name }}</span>

                        <div class="project-small-description text-center">
                            {{ $project->small_description }}
                        </div>
                        <div class="clearfix"></div>
                        <div class="page-home-view-btn">
                            <a class="btn btn-action pull-right"
                               href="{{ url('project/' . $project->slug) }}">{{ trans('site.view') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<hr>
