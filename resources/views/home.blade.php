@extends('app')
@section('title', trans('site.home_title'))
@section('page-name', 'home')
@section('description', trans('site.home_description'))
@section('content')

    <div class='container-fluid page-home'>

        @include('toasts/success')
        @include('toasts/error')

        <div class="row">
            <h1 class="page-title">{{ trans('site.home_title') }}
                <div class="page-home-app-links pull-right hidden-sm hidden-xs">
                    <span>Available on </span>
                    <a href="https://play.google.com/store/apps/details?id=uk.ac.imperial.epicollect.five&hl=en_GB">
                        <i class="fa fa-android"></i>
                    </a>
                    <a href="https://itunes.apple.com/us/app/epicollect5/id1183858199?mt=8"><i class="fa fa-apple"></i></a>
                </div>
            </h1>
        </div>

        <div class="row" style="text-align: center;">
            <p style="background-color:#ffe0b2;padding:5px 0;border-radius:6px">Try the new beta for
                <strong><a href="https://testflight.apple.com/join/6gW71eIy" target="_blank">iOS</a></strong>
            </p>
        </div>

        <div class="row page-home-intro">
            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-sm-12 text-center intro-thumbnail">
                <img src="{{ asset('/images/ec5-intro-create-project.jpg') }}" class="img-responsive"
                     alt="Create your project and forms on the website">

                <div class="loader"></div>
                <h4>Create your project and forms</h4>
                <a href="{{ url('/more-create') }}" class="btn btn-default btn-action">Tell me more</a>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-sm-12 text-center intro-thumbnail">
                <img src="{{ asset('/images/ec5-intro-collect-data.jpg') }}" class="img-responsive"
                     alt="Download project on device and collect data online or offline">

                <div class="loader"></div>
                <h4>Collect data online or offline</h4>
                <a href="{{ url('/more-collect') }}" class="btn btn-default btn-action">Tell me more</a>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-sm-12 text-center intro-thumbnail">
                <img src="{{ asset('/images/ec5-intro-view-data.jpg') }}" class="img-responsive"
                     alt="View, analyse and export your data (json, csv)">

                <div class="loader"></div>
                <h4>View, analyse and export your data</h4>
                <a href="{{ url('/more-view') }}" class="btn btn-default btn-action">Tell me more</a>
            </div>
        </div>

        <hr>
        <div class="page-home__find-project">
            <h2 class="text-center">Have a look at our featured projects below or
                <a href="{{ url('/projects/') }}" class="btn btn-default btn-action-inverse btn-lg">find a project</a>
            </h2>
        </div>
        <hr>

        <div class="row page-home-featured-projects">

            @foreach ($projectsFirstRow as $project)
                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
                    <div class="panel panel-default">
                        {{-- <div class="panel-heading"> --}}
                        {{-- </div> --}}
                        <div class="panel-body">
                            <a href="{{ url('project/' . $project->slug) }}" class="thumbnail">
                                <img class="img-responsive img-circle" width="256" height="256"
                                     src="
                                                {{-- If a private project, show lock --}}
                                                     @if ($project->access == Config::get('ec5Strings.project_access.private')) {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                            @elseif($project->logo_url == '')
                                {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                            @else
                                {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }} @endif"
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

            <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
                <div class="panel panel-default panel-community">
                    <div class="panel-body">
                        <a href="https://community.epicollect.net" class="thumbnail">
                            <img class="" width=" 256" src="{{ asset('/images/ec5-community.jpg') }}">
                            <div class="loader"></div>
                        </a>
                        <div class="project-small-description text-center">
                            Do you have any questions?
                        </div>
                        <div class="clearfix"></div>
                        <div class="page-home-join-btn">
                            <a class="btn btn-action" href="https://community.epicollect.net">
                                {{ trans('site.join_community') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Display second project row only if we have 4 projects featured --}}
        @if (count($projectsSecondRow) === 4)
            <div class="row page-home-featured-projects-small">
                @foreach ($projectsSecondRow as $project)
                    <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
                        <div class="panel panel-default">
                            {{-- <div class="panel-heading"> --}}
                            {{-- </div> --}}
                            <div class="panel-body">
                                <a href="{{ url('project/' . $project->slug) }}" class="thumbnail">
                                    <img class="img-responsive img-circle" width="128" height="128"
                                         src="
                                                {{-- If a private project, show lock --}}
                                                       @if ($project->access == Config::get('ec5Strings.project_access.private')) {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                @elseif($project->logo_url == '')
                                    {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                @else
                                    {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }} @endif"
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

        <div class="page-home__server-stats row">
            <h3 class="text-center server-stats__stats-teaser">Thousand of people use <strong
                        style="color:#673c90">Epicollect5</strong> every day
                to collect data
                all over the
                world.</h3>
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

    </div>

@stop
