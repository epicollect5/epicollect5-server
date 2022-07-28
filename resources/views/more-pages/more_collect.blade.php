@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.more_collect'))
@section('description', trans('site.more_collect_description'))
@section('content')

    <div class='container page-more-create'>
        <h1 class="page-title">{{ trans('site.more_collect') }}</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-body text-left">
                        <h2 class="">1. Download the app</h2>
                        <p>Epicollect5 is available for Android phones and tablets, iPhones and iPads.</p>

                        <p>
                            <a href="
                            https://play.google.com/store/apps/details?id=uk.ac.imperial.epicollect.five">
                            <img src="{{ asset('/images/more-pages/play-store-badge.jpg') }}" width="140"
                                alt="Epicollect5 Play Store badge" />
                            </a>

                            <a href="https://itunes.apple.com/us/app/epicollect5/id1183858199?mt=8">
                                <img src="{{ asset('/images/more-pages/app-store-badge.jpg') }}" width="140"
                                    alt="Epicollect5 App Store badge" />
                            </a>

                            <p>The app is completely <strong>free.</strong></p>

                            <p>Use it on as many devices as you like, <strong>no limits.</strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <img src="{{ asset('/images/more-pages/more-collect-1.jpg') }}" class="img-responsive"
                            alt="Download the app">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 col-md-push-6">
                <div class="panel panel-default">
                    <div class="panel-body text-left">
                        <h2 class="">2. Add your project</h2>
                        <p>Add your project on the device</p>

                        <p>Just click "
                            Add project" on the app home page</p>

                            <p>Search for your project</p>

                            <p>Tap on it to download it.</p>

                            <p>Yor are good to go.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-md-pull-6">
                <div class="panel panel-default">
                    <div class="panel-body text-left">
                        <img src="{{ asset('/images/more-pages/more-collect-2.jpg') }}"
                            class="img-responsive img-with-border" alt="Add your project">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">

            <div class="col-md-6 ">
                <div class="panel panel-default">
                    <div class="panel-body text-left">
                        <h2 class="">3. Collect data</h2>

                        <p>Select your project and add entries to it.</p>

                        <p>Collect data online or <strong>offline</strong>.</p>

                        <p>Upload your data to the server the next time you are online.</p>

                        <p>View and download your data from the server.</p>

                        <p> <a class="
                            underline" href="{{ url('/more-view') }}">More info on viewing and downloading data.</a></p>

                            <p>
                                <a class="underline" href="https://docs.epicollect.net/">
                                    Read full Epicollect5 User Guide.
                                </a>
                            </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <img src="{{ asset('/images/more-pages/more-collect-3.jpg') }}"
                            class="img-responsive img-with-border" alt="Collect data">
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
