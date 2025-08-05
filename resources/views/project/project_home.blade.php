@extends('app')
@section('title', $requestAttributes->requestedProject->name)
@section('description', $requestAttributes->requestedProject->description)
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-project-home'>

        <div class="row">

            <div class="project-home-wrapper col-sm-12 col-md-8 col-lg-8 col-md-offset-2 col-lg-offset-2">

                <div class="panel panel-default ">
                    <div class="panel-body">
                        <a href="{{url('project/' . $requestAttributes->requestedProject->slug . '/data')}}"
                           class="project-logo-wrapper">
                            <img class="project-logo img-responsive img-circle" width="256" height="256"
                                 alt="Project logo"
                                 src="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                 @else
                                 {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                 @endif">

                            <div class="loader"></div>
                        </a>
                    </div>

                    <div class="panel-body project-home__project-name">
                        <h1 class="text-center">{{$requestAttributes->requestedProject->name}}</h1>
                    </div>

                    <div class="panel-body project-home__small-description">
                        <h2 class="well">{{$requestAttributes->requestedProject->small_description}}</h2>
                    </div>

                    @if($requestAttributes->requestedProjectRole->canEditProject())
                        @if($requestAttributes->requestedProject->isAppLinkShown())
                            @include('project.project_home.action_row_edit_add')
                        @else
                            @include('project.project_home.action_row_edit')
                        @endif
                    @else
                        @if($requestAttributes->requestedProject->isAppLinkShown())
                            @include('project.project_home.action_row_view_add')
                        @else
                            @include('project.project_home.action_row_view')
                        @endif
                    @endif

                    <div class="panel-body project-home__project-description">
                        @if(trim($requestAttributes->requestedProject->description) === '')
                            <p class="well text-center">{{trans('site.no_desc_yet')}}</p>
                        @else
                            <p class="well">{!! nl2br(e($requestAttributes->requestedProject->description)) !!}</p>
                        @endif
                    </div>

                    {{--Show social media share buttons if project is public and listed--}}
                    <div class="panel-body project-home__share-btns">
                        @include('project.share.share_links')
                    </div>

                </div>
            </div>
        </div><!-- end col -->
    </div><!-- end row -->

@stop

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.config('app.release') }}"></script>
@stop
