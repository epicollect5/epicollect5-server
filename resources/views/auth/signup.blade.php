@extends('app')
@section('title', trans('site.sign_up'))

@section('content')

    @include('toasts/error')

    <div class="container page-signup">
        <h2 class="page-title">{{trans('site.sign_up')}}</h2>
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <span class="hidden gcaptcha">{{config('auth.google.recaptcha_site_key')}}</span>
                        <form id="page-signup__form" class="form-horizontal" role="form" method="POST"
                              action="{{ route('signup-post') }}"
                              autocomplete="off"
                        >
                            {{ csrf_field() }}

                            <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                                <label for="name" class="col-md-4 control-label">Name</label>

                                <div class="col-md-6">
                                    <input id="name" type="text" class="form-control" name="name"
                                           value="{{ old('name') }}" minlength="3" maxlength="25" required
                                           autofocus>

                                    @if (strpos($errors->first('name'), 'ec5_') === false)
                                        <small class="text-danger">{{ $errors->first('name') }}</small>
                                    @else
                                        <small class="text-danger">{{ trans('status_codes.' . $errors->first('name'))  }}</small>
                                    @endif
                                    <div>
                                        <small>Use 3 to 25 characters
                                        </small>
                                    </div>
                                </div>

                            </div>

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

                            <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                                <label for="password" class="col-md-4 control-label">Password</label>

                                <div class="col-md-6">
                                    <input id="password" type="password" class="form-control password-input"
                                           name="password" required minlength="10"
                                           autocomplete="off">

                                    @if (strpos($errors->first('password'), 'ec5_') === false)
                                        <small class="text-danger">{{ $errors->first('password') }}</small>
                                    @else
                                        <small class="text-danger">{{ trans('status_codes.' . $errors->first('password'))  }}</small>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="password-confirm" class="col-md-4 control-label">
                                    Confirm Password
                                </label>
                                <div class="col-md-6">
                                    <input id="password-confirm" type="password" class="form-control password-input"
                                           name="password_confirmation"
                                           required
                                           minlength="10" autocomplete="off">
                                </div>
                            </div>


                            <div class="col-md-6 col-md-offset-4">

                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input show-password-control"
                                           id="show-password">
                                    <label class="form-check-label" for="show-password">
                                        <small>Show password</small>
                                    </label>
                                </div>

                                <div>
                                    <small>Use 10 or more characters with a mix of letters, numbers & symbols
                                    </small>
                                    <br/><br/>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">

                                    {{--<strong><a href="#" class="pull-left">Login instead</a></strong>--}}
                                    <input id="signup" type="submit" class="btn btn-action pull-right"
                                           value="{{trans('site.sign_up')}}">
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
    <script src="https://www.google.com/recaptcha/api.js?render={{config('auth.google.recaptcha_site_key')}}">
    </script>
    <script type="text/javascript" src="{{ asset('js/users/users.js').'?'.config('app.release') }}"></script>
@stop
