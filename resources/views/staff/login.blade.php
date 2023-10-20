@extends('app')
@section('title', trans('site.server_staff_login'))
@section('content')

    @include('toasts/success')
    @include('toasts/error')

    <div class="container page-staff-login">
        <h2 class="page-title">{{trans('site.staff_login')}}</h2>
        <div class="row">
            <div class="col-lg-6 col-md-6 col-md-offset-3">
                <div class="panel panel-default">
                    <div class="panel-body">

                        <form method="POST" action="{{ url('login/staff') }}" accept-charset="UTF-8">
                            {{ csrf_field() }}
                            <div class="form-group">
                                <label for="email">{{trans('site.email')}}</label>
                                <input class="form-control" required="required"
                                       placeholder="{{trans('site.email_address')}}" name="email"
                                       type="email" id="email">
                            </div>
                            <div class="form-group">
                                <label for="password">{{trans('site.password')}}</label>
                                <input class="form-control" required="required" placeholder="{{trans('site.password')}}"
                                       name="password"
                                       type="password" value="" id="password">
                            </div>
                            <div class="form-group">
                                <input class="btn btn-default btn-action" type="submit" value="{{trans('site.login')}}">
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
