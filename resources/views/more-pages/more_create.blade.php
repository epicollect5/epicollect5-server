@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.more_create'))
@section('description', trans('site.more_create_description'))
@section('content')

    <div class='container page-more-create'>
        <h1 class="page-title">{{ trans('site.more_create') }}</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-body text-left">
                        <h2 class="">1. Create a project</h2>
                        <p>Create a project in just a few easy steps.</p>

                        <p>Add your logo and description to personalise it even more.</p>

                        <p>Your project will be hosted for <strong>FREE</strong> on Epicollect5.</p>
                    </div>
                </div>
            </div>
            <div class="
                            col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-body text-center">
                                    <img src="{{ asset('/images/more-pages/more-create-1.jpg') }}"
                                        class="img-responsive img-with-border" alt="Create a project">
                                </div>
                            </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-md-push-6">
                        <div class="panel panel-default">
                            <div class="panel-body text-left">
                                <h2 class="">2. Add questions</h2>
                        <p>Add questions and forms using an intuitive drag & drop formbuilder.</p>

                        <p>Add simple or multiple choice questions.</p>

                        <p>Geotag your entries adding locations.</p>

                        <p>Add photos, videos, audios and barcodes.</p>
                    </div>
                </div>
            </div>
            <div class="
                                    col-md-6 col-md-pull-6">
                                    <div class="panel panel-default">
                                        <div class="panel-body text-left">
                                            <img src="{{ asset('/images/more-pages/more-create-2.jpg') }}"
                                                class="img-responsive img-with-border" alt="Add questions">
                                        </div>
                                    </div>
                            </div>
                        </div>

                        <div class="row">

                            <div class="col-md-6 ">
                                <div class="panel panel-default">
                                    <div class="panel-body text-left">
                                        <h2 class="">3. Publish and share</h2>
                        <p>Set project details according to your needs.</p>

                        <p>For advanced users, it is very simple to set access control, manage users and create custom data mappings.</p>

                        <p>Share project home page.</p>

                        <p>Download project on device to collect data online or <strong>offline</strong></p>

                        <p>
                            <a class="
                                            underline" href="{{ url('/more-collect') }}">
                                            More info on collecting data.
                                            </a>
                                            </p>

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
                                        <img src="{{ asset('/images/more-pages/more-create-3.jpg') }}"
                                            class="img-responsive" alt="Publish and share">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @stop
