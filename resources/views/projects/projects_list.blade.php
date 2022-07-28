@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.project_categories.' . $selectedCategory) . ' ' . trans('site.projects'))
@section('content')

    <div class='container-fluid page-projects-list'>

        @include('projects.project_list_navbar')

        <div class="row project_list_cards">
            @if (count($projects) == 0)
                <p class="well text-center">No {{ trans('site.project_categories.' . $selectedCategory) }} Projects
                    found.</p>
            @endif
            @foreach ($projects as $project)
                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3 item">
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <div class="flexbox col-direction project-summary">
                                <div class="thumbnail">
                                    <img class="projects-list__project-logo img-responsive img-circle" width="128"
                                        height="128" alt="{{ $project->name }}" src="@if (!empty($project->logo_url)) {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                @else
                                    {{ url('/images/ec5-placeholder-256x256.jpg') }} @endif">
                                </div>
                                <span class="project-name"> {{ $project->name }}</span>
                                {{-- truncate small desc for layout, see if it break and lower from 100 to until it is fixed --}}
                                <div class="project-small-description">{{ $project->small_description }}</div>

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

        </div>
        <div class="text-right">
            {{ $projects->render() }}
        </div>
    </div>
@stop
