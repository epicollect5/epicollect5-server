@extends('app')
@section('title', $requestAttributes->requestedProject->name)
@section('description', $requestAttributes->requestedProject->description)
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-project-open'>

        <div class="row">
            <div class="project-open-wrapper col-sm-12 col-md-8 col-lg-8 col-md-offset-2 col-lg-offset-2">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="col-xs-12 col-sm-offset-3 col-sm-6 col-md-offset-3 col-md-6 project-open__action-btns text-center">
                            <a class="btn btn-action"
                               href="{{ url('project/' . $requestAttributes->requestedProject->slug . '/data') }}">
                                Open in Browser
                            </a>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="col-xs-12 col-sm-12 col-md-offset-2 col-md-8 project-open__action-btns text-center">
                            <div>Get the Epicollect5 mobile app to join</div>

                            <div class="panel-body project-open__project-name">
                                <h1 class="text-center">{{$requestAttributes->requestedProject->name}}</h1>
                            </div>
                            <div>
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
                    </div>

                </div>
            </div>
        </div><!-- end col -->
    </div><!-- end row -->

@stop

@section('scripts')

@stop
