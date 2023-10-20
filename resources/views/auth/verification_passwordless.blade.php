@extends('app')
@section('title', trans('site.verification'))

@section('content')
    @include('toasts/success')
    @include('toasts/error')

    <div class="container page-verification">
        <div class="row">
            <h2 class="page-title">{{ trans('site.login') }}</h2>
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-body">

                        <p class="well text-center">
                            We sent a code to
                            <strong>{{ $email }}</strong><br/>
                            Enter it below to login
                        </p>

                        <form class="form-horizontal" method="POST" action="{{ route('passwordless-auth-web') }}"
                              autocomplete="off">
                            {{ csrf_field() }}
                            <div class="form-group">
                                <label for="code" class="col-sm-4 control-label">Code</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control code-input" id="code" name="code"
                                           maxlength="6" minlength="6" required pattern="[0-9]+">
                                    <input type="hidden" class="form-control email-input" id="email" name="email"
                                           value="{{ $email }}">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-sm-offset-4 col-sm-8">
                                    <button type="submit" class="btn btn-action code-btn">
                                        Login
                                    </button>
                                </div>
                            </div>
                        </form>

                        {{-- <hr> --}}

                        {{-- <p class="warning-well">Check also your spam folder! <br /> --}}
                        {{-- If you did not receive it, send it again</p> --}}
                        {{-- <form
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
                        </form> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
