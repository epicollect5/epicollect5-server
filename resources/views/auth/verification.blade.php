@extends('app')
@section('title', trans('site.verification'))

@section('content')

    @include('toast-success')
    @include('toast-error')

    <div class="container page-verification">
        <div class="row">
            <h2 class="page-title">{{trans('site.verification')}}</h2>
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-body">

                        <p class="well text-center">Hi <strong>{{$name}}</strong>, we sent the activation code at <strong>{{$email}}.</strong><br />
                            Enter it below to activate your account
                        </p>

                        <form class="form-horizontal"
                              method="POST"
                              action="{{ route('verify-post') }}"
                              autocomplete="off"
                        >
                            {{ csrf_field() }}
                            <div class="form-group">
                                <label for="code" class="col-sm-4 control-label">Code</label>
                                <div class="col-sm-4">
                                    <input type="text"
                                           class="form-control code-input"
                                           id="code"
                                           name="code"
                                           maxlength="6"
                                           minlength="6"
                                           required
                                           pattern="[0-9]+"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-sm-offset-4 col-sm-8">
                                    <button type="submit" class="btn btn-action code-btn">
                                        Activate Account
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr>

                        {{--<p class="warning-well">Check also your spam folder! <br />--}}
                            {{--If you did not receive it, send it again</p>--}}
                        <form
                                method="POST"
                                action="{{ route('resend') }}"
                                class="text-center"
                        >
                            {{ csrf_field() }}
                            <input type="submit"
                                   name="resend"
                                   class="btn btn-action-inverse code-btn"
                                   value="{{trans('site.resend_code')}}"
                            >
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
