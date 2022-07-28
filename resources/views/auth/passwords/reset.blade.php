@extends('app')
@section('title', trans('site.reset_password'))
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')
    <div class="container page-reset">
        <h2 class="page-title">{{trans('site.reset_password')}}</h2>
        @include('toast-success')
        @include('toast-error')

        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">

                    <div class="panel-body">
                        <form class="form-horizontal" role="form" method="POST" action="{{ route('login-reset-post') }}">
                            {{ csrf_field() }}

                            <input type="hidden" name="jwt-forgot" value="{{$token}}">

                            <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                                <label for="email" class="col-md-4 control-label">Email</label>

                                <div class="col-md-6">
                                    <input id="email" type="email" class="form-control" name="email" value="{{ $email or old('email') }}" required
                                           >

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
                                    <input id="password" type="password" class="form-control password-input" name="password" required minlength="10">

                                    @if (strpos($errors->first('password'), 'ec5_') === false)
                                        <small class="text-danger">{{ $errors->first('password') }}</small>
                                    @else
                                        <small class="text-danger">{{ trans('status_codes.' . $errors->first('password'))  }}</small>
                                    @endif
                                    <div>
                                        <small>
                                            Use 10 or more characters with a mix of letters, numbers & symbols.
                                        </small>
                                    </div>
                                </div>

                            </div>

                            <div class="form-group">
                                <label for="password-confirm" class="col-md-4 control-label">Confirm Password</label>
                                <div class="col-md-6">
                                    <input id="password-confirm" type="password" class="form-control password-input" name="password_confirmation"
                                           required minlength="10">

                                    @if (strpos($errors->first('password-confirm'), 'ec5_') === false)
                                        <small class="text-danger">{{ $errors->first('password-confirm') }}</small>
                                    @else
                                        <small class="text-danger">{{ trans('status_codes.' . $errors->first('password-confirm'))  }}</small>
                                    @endif
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input show-password-control" id="show-password">
                                        <label class="form-check-label" for="show-password">
                                            <small>Show passwords</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <button type="submit" class="btn btn-action pull-right">
                                        Reset Password
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
    <script type="text/javascript" src="{{ asset('js/users/users.js').'?'.ENV('RELEASE') }}"></script>
@stop
