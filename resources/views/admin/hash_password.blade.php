@extends('app')
@section('title', trans('site.server_admin_login'))
@section('content')

    @include('toasts/success')
    @include('toasts/error')

    <div class="row">

        <div class="col-lg-6 col-md-6 col-md-offset-3">
            <div class="panel panel-default">
                <div class="panel-body">

                    <form method="POST" action="{{ url('admin/tools/hash-password') }}" accept-charset="UTF-8">

                        {{ csrf_field() }}

                        <div class="form-group">
                            <label for="password">{{trans('site.password')}}</label>
                            <input class="form-control" required="required" placeholder="{{trans('site.password')}}"
                                   name="password"
                                   type="text" value="" id="password">
                        </div>
                        <div class="form-group">
                            <input class="btn btn-default btn-action" type="submit" value="Hash">
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@stop
