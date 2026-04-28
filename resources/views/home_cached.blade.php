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

        <div class="row page-home-intro">
            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-sm-12 text-center intro-thumbnail">
                <img src="{{ static_asset('/images/ec5-intro-create-project.jpg') }}" class="img-responsive"
                     alt="Create your project and forms on the website">

                <div class="loader"></div>
                <h4>Create your project and forms</h4>
                <a href="{{ url('/more-create') }}" class="btn btn-default btn-action">Tell me more</a>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-sm-12 text-center intro-thumbnail">
                <img src="{{ static_asset('/images/ec5-intro-collect-data.jpg') }}" class="img-responsive"
                     alt="Download project on device and collect data online or offline">

                <div class="loader"></div>
                <h4>Collect data online or offline</h4>
                <a href="{{ url('/more-collect') }}" class="btn btn-default btn-action">Tell me more</a>
            </div>
            <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-sm-12 text-center intro-thumbnail">
                <img src="{{ static_asset('/images/ec5-intro-view-data.jpg') }}" class="img-responsive"
                     alt="View, analyse and export your data (json, csv)">

                <div class="loader"></div>
                <h4>View, analyse and export your data</h4>
                <a href="{{ url('/more-view') }}" class="btn btn-default btn-action">Tell me more</a>
            </div>
        </div>

        <hr>

        {{-- Render cached featured projects and stats content --}}
        {!! $homepageCachedContent !!}

    </div>

@stop
