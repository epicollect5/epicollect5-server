@extends('app')
@section('title', $project->name)
@section('description', $project->description)
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-project-home'>

        <div class="row">

            <div href="#" class="project-home-wrapper col-sm-12 col-md-8 col-lg-8 col-md-offset-2 col-lg-offset-2">

                <div id="" class="panel panel-default ">
                    <div class="panel-body">
                        <a href="{{url('project/' . $project->slug . '/data')}}" class="project-home__logo-wrapper">
                            <img class="project-home__logo img-responsive img-circle" width="256" height="256"
                                 alt="Project logo"
                                 src="@if($project->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                 @else
                                 {{ url('/api/internal/media/'.$project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                 @endif">

                            <div class="loader"></div>
                        </a>
                    </div>

                    <div class="panel-body project-home__project-name">
                        <h1 class="text-center">{{$project->name}}</h1>
                    </div>

                    <div class="panel-body project-home__small-description">
                        <h2 class="well">{{$project->small_description}}</h2>
                    </div>

                    @if($requestedProjectRole->canEditProject())
                        @include('project.project_home.action_row_edit')
                    @else
                        @include('project.project_home.action_row_view')
                    @endif

                    <div class="panel-body project-home__project-description">

                        @if(!$project->description)
                            <p class="well text-center">{{trans('site.no_desc_yet')}}</p>
                        @else
                            <p class="well">{!! nl2br(e($project->description)) !!}</p>
                        @endif
                    </div>

                    {{--Show social media share buttons if project is public and listed--}}
                    @if($canShowSocialMediaShareBtns)
                        <div class="panel-body project-home__share-btns">
                            <div class="sharethis-inline-share-buttons"></div>
                        </div>
                    @endif

                </div>
            </div>
        </div><!-- end col -->
    </div><!-- end row -->

@stop

@section('scripts')

@stop
