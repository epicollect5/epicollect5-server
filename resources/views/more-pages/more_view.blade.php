@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.more_view'))
@section('description', trans('site.more_view_description'))

@section('content')

    <div class='container page-more-create'>
        <h1 class="page-title">{{ trans('site.more_view') }}</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-body text-left">
                        <h2 class="">1. View collected data on a table</h2>
                        <p>Data collected from multiple devices is aggregated on the server.</p>

                        <p>View your data collection as a table.</p>

                        <p>Edit each entry or just delete the ones you do not want.</p>

                        <p>Add new entry directly via the web interface.</p>
                    </div>
                </div>
            </div>
            <div class="
                            col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-body text-center">
                                    <img src="{{ asset('/images/more-pages/more-view-1.jpg') }}"
                                        class="img-responsive img-with-border" alt="View collected data on a table">
                                </div>
                            </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-md-push-6">
                        <div class="panel panel-default">
                            <div class="panel-body text-left">
                                <h2 class="">2. View data collected on a map</h2>
                        <p>If your project contains locations, data can be viewed on a map.</p>

                        <p>Click on a single marker to view details about a single entry.</p>

                        <p>Select a multiple choice question to view distribution as pie charts.</p>

                    </div>
                </div>
            </div>
            <div class="
                                    col-md-6 col-md-pull-6">
                                    <div class="panel panel-default">
                                        <div class="panel-body text-left">
                                            <img src="{{ asset('/images/more-pages/more-view-2.jpg') }}"
                                                class="img-responsive img-with-border" alt="View data collected on a map">
                                        </div>
                                    </div>
                            </div>
                        </div>

                        <div class="row">

                            <div class="col-md-6 ">
                                <div class="panel panel-default">
                                    <div class="panel-body text-left">
                                        <h2 class="">3. Download your data</h2>

                        <p>Data can be easily downloaded in both JSON and CSV formats.</p>

                        <p>For advanced users, select a data mapping to use for the download.</p>

                        <p><a class="
                                            underline" href="https://docs.epicollect.net/">Read full Epicollect5 User
                                            Guide</a></p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="panel panel-default">
                                    <div class="panel-body text-center">
                                        <img src="{{ asset('/images/more-pages/more-view-3.jpg') }}"
                                            class="img-responsive img-with-border" alt="Download your data">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @stop
