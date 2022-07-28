{{-- We render this template server side if there is an Ajax request --}}
{{-- We do this because we are lazy ;) --}}
{{-- When there is time we will do it properly (send json and render client side) --}}

<div class="row">
    @if (count($projects) == 0)
         <p class="well text-center"> {{ trans('site.no_projects_found') }}
            <br />
            <small>Projects set as <strong>private</strong> or <strong>hidden</strong> do not get listed here.

                <strong>
                    <a href="https://docs.epicollect.net/web-application/set-project-details#project-visibility"
                        target="_blank">
                        More info.
                    </a>
                </strong>

            </small>
        </p>
    @else
        {{-- Show projects if there are any --}}
        @foreach ($projects as $project)
            <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3 item">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="flexbox col-direction project-summary">
                            <div class="thumbnail">
                                <img class="projects-list__project-logo img-responsive img-circle" width="128"
                                    height="128" alt="Project logo" src="@if (!empty($project->logo_url)) {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                            @else
                                {{ url('/images/ec5-placeholder-256x256.jpg') }} @endif">
                            </div>
                            <span class="project-name"> {{ $project->name }}</span>
                            {{-- truncate small desc for layout, see if it break and lower from 100 to until it is fixed --}}
                            <div class="project-small-description">{{ $project->small_description }}</div>
                            <div class="text-center">
                                <small>{{ trans('site.project_category') }}:</small>
                                <span class="label label-primary">{{ mb_strtoupper($project->category) }}</span>
                            </div>
                            <div class="text-center">
                                <small>{{ trans('site.created') }}:
                                    <strong>{{ date('d M Y', strtotime($project->created_at)) }}</strong>
                                </small>
                            </div>
                            <div class="text-center">
                                <small>{{ trans('site.entries') }}:
                                    <strong>{{ Common::roundNumber($project->total_entries, 1) }}</strong>
                                </small>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <div class="text-right">
                            <a class="btn btn-action btn-sm" href="{{ url('project') . '/' . $project->slug }}">
                                {{ trans('site.view') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

    @endif
</div>

@if (count($projects) > 0)
    <div class="row">
        <div class="col-md-12">
            <div class="text-right">
                {{-- render the pagination links --}}
                {{ $projects->render() }}
            </div>
        </div>
    </div>
@endif
