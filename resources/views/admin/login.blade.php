@extends('app')
@section('title', trans('site.server_admin_login'))
@section('content')

    @if(!$errors->isEmpty())
        @foreach($errors->all() as $error)
            <div class="var-holder-error" data-message="{{trans('status_codes.'.$error)}}"></div>
        @endforeach
        <script>
            //get all errors
            var errors = '';
            $('.var-holder-error').each(function () {
                errors += $(this).attr('data-message') + '</br>';
            });
            EC5.toast.showError(errors);
        </script>
    @endif

    {{-- Success Message --}}
    @if (session('message'))
        <div class="var-holder-success" data-message="{{trans('status_codes.'.session('message'))}}"></div>
        <script>
            EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
        </script>
    @endif

    <div class="row">

        <div class="col-lg-6 col-md-6 col-md-offset-3">
            <div class="panel panel-default">
                <div class="panel-body">

                    <form method="POST" action="{{ url('login/admin') }}" accept-charset="UTF-8">

                        {{ csrf_field() }}

                        <div class="form-group">
                            <label for="email">{{trans('site.email')}}</label>
                            <input class="form-control" required="required" placeholder="{{trans('site.email_address')}}" name="email"
                                   type="email" id="email">
                        </div>
                        <div class="form-group">
                            <label for="password">{{trans('site.password')}}</label>
                            <input class="form-control" required="required" placeholder="{{trans('site.password')}}" name="password"
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
@stop