@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.contact'))
@section('content')

    <div class='container-fluid page-contact'>
        <div class="row">

            <div class="col-md-6 col-md-offset-3">
                <h1 class="page-title">{{trans('site.contact_us')}}</h1>
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                       <h2 class="modal-title"> Epicollect5 is currently available as <strong>Beta</strong></h2>
                        <p>If you would like to give it a try go to the login page using the button below</p>
                        <p class="bg-warning">
                            <strong>Please be aware this is a Beta release. We are still testing the application and ironing out bugs. </strong>
                        </p>
                        <p>Your feedback will be very helpful! </p>
                        <a href="{{ url('login') }}" target="_blank" class="btn btn-default btn-action-inverse pull-right">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
