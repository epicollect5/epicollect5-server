@extends('app')
@section('title', trans('site.forgot_password'))
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')
    <div class="container page-forgot">
        <h2 class="page-title">{{trans('site.forgot_password')}}</h2>

        @include('toasts/success')
        @include('toasts/error')

        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <p class="well text-center">We will send you an email to reset your password</p>
                        <span class="hidden gcaptcha">{{Config::get('auth.google.recaptcha_site_key')}}</span>
                        <form id="page-forgot__form"
                              class="form-horizontal" role="form" method="POST" action="{{ route('forgot-post') }}">
                            {{ csrf_field() }}

                            <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                                <label for="email" class="col-md-4 control-label">Email</label>

                                <div class="col-md-6">
                                    <input id="email" type="email" class="form-control" name="email"
                                           value="{{ old('email') }}" required>

                                    @if (strpos($errors->first('email'), 'ec5_') === false)
                                        <small class="text-danger">{{ $errors->first('email') }}</small>
                                    @else
                                        <small class="text-danger">{{ trans('status_codes.' . $errors->first('email'))  }}</small>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <button id="send" type="submit" class="btn btn-action pull-right">
                                        Send
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://www.google.com/recaptcha/api.js?render={{Config::get('auth.google.recaptcha_site_key')}}">
    </script>
    <script type="text/javascript" src="{{ asset('js/users/users.js').'?'.Config::get('app.release') }}"></script>
@stop
