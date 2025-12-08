@extends('app')
@section('title', trans('site.verification'))

@section('content')

    @include('toasts/success')
    @include('toasts/error')

    <div class="container page-verification">
        <div class="row">
            <h2 class="page-title">{{ trans('site.verification') }}</h2>
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <p class="well text-center">
                            Account with the provided email already exists <br/>
                            We sent a verification code to <strong
                                    class="email-for-verification">{{ $email }}.</strong><br/>
                            Enter it below to verify your account
                        </p>

                        <div class="hidden-xs">
                            <form class="form-horizontal" method="POST"
                                  action="{{ $provider === 'google' ? route('verification-google') : route('verification-apple') }}"
                                  autocomplete="off">
                                {{ csrf_field() }}
                                <div class="form-group">
                                    <label for="code" class="col-sm-4 control-label">Code</label>
                                    <div class="col-sm-4">
                                        <input type="text" class="form-control code-input" id="code" name="code"
                                               maxlength="6" minlength="6" required pattern="[0-9]+">
                                    </div>
                                    <input type="hidden" name="email" value="{{ $email }}"/>

                                    @if ($provider === 'google')
                                        <input type="hidden" name="user[given_name]" value="{{ $name }}"/>
                                        <input type="hidden" name="user[family_name]" value="{{ $last_name }}"/>
                                    @endif
                                    @if ($provider === 'apple')
                                        <input type="hidden" name="user[givenName]" value="{{ $name }}"/>
                                        <input type="hidden" name="user[familyName]" value="{{ $last_name }}"/>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <div class="col-sm-offset-4 col-sm-8">
                                        <button type="submit" class="btn btn-action code-btn">
                                            Verify Account
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="visible-xs text-center">
                            <form method="POST"
                                  action="{{ $provider === 'google' ? route('verification-google') : route('verification-apple') }}"
                                  autocomplete="off">
                                {{ csrf_field() }}
                                <div class="form-group">
                                    <label for="code" class="col-sm-4 control-label">Code</label>
                                    <div class="col-sm-4">
                                        <input type="text" class="form-control code-input" id="code" name="code"
                                               maxlength="6" minlength="6" required pattern="[0-9]+">
                                    </div>
                                    <input type="hidden" name="email" value="{{ $email }}"/>
                                </div>

                                <div class="form-group">
                                    <div class="col-sm-offset-4 col-sm-8">
                                        <button type="submit" class="btn btn-action code-btn">
                                            Verify <span class="hidden-xs">Account</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
