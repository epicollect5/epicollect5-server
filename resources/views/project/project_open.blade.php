@extends('app')
@section('title', $requestAttributes->requestedProject->name)
@section('description', $requestAttributes->requestedProject->description)
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-project-open'>

        <div class="row">

            <div class="project-open-wrapper col-sm-12 col-md-8 col-lg-8 col-md-offset-2 col-lg-offset-2">

                <div class="panel panel-default ">
                    <div class="panel-body">
                        {{-- Logo lazy loading done in site/config.js       --}}
                        <a href="{{url('project/' . $requestAttributes->requestedProject->slug . '/data')}}"
                           class="project-open__logo-wrapper">
                            <img class="project-open__logo img-responsive img-circle" width="256" height="256"
                                 alt="Project logo"
                                 src="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                 @else
                                 {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                 @endif">

                            <div class="loader"></div>
                        </a>
                    </div>

                    <div class="panel-body project-open__project-name">
                        <h1 class="text-center">{{$requestAttributes->requestedProject->name}}</h1>
                    </div>

                    <div class="panel-body project-open__small-description">
                        <h2 class="well">{{$requestAttributes->requestedProject->small_description}}</h2>
                    </div>
                    <div class="panel-body">
                        <div class="col-xs-12 col-sm-offset-3 col-sm-6 col-md-offset-3 col-md-6 project-open__action-btns text-center">
                            <a class="btn btn-action"
                               href="{{ url('project/' . $requestAttributes->requestedProject->slug . '/data') }}">
                                Open in Browser
                            </a>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="col-xs-12 col-sm-offset-3 col-sm-6 col-md-offset-3 col-md-6 project-open__action-btns text-center">

                            <a href="
                            https://play.google.com/store/apps/details?id=uk.ac.imperial.epicollect.five">
                                <img class="margin-bottom-md"
                                     src="{{ asset('/images/more-pages/play-store-badge.jpg') }}" width="140"
                                     alt="Epicollect5 Play Store badge"/>
                            </a>


                            <a
                                    href="https://itunes.apple.com/us/app/epicollect5/id1183858199?mt=8">
                                <img
                                        class="margin-bottom-md"
                                        src="{{ asset('/images/more-pages/app-store-badge.jpg') }}" width="140"
                                        alt="Epicollect5 App Store badge"/>
                            </a>
                        </div>
                    </div>

                    <div class="panel-body project-open__project-description">

                        @if(!$requestAttributes->requestedProject->description)
                            <p class="well text-center">{{trans('site.no_desc_yet')}}</p>
                        @else
                            <p class="well">{!! nl2br(e($requestAttributes->requestedProject->description)) !!}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div><!-- end col -->
    </div><!-- end row -->

@stop

@section('scripts')

@stop
