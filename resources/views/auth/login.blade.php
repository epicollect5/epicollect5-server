@extends('app')
@section('title', trans('site.login'))
@section('content')

    @include('toasts/error')
    @include('toasts/success')

    <div class="container page-login">
        <div class="row">
            <div class="col-lg-6 col-lg-offset-3 col-md-6 col-md-offset-3 col-sm-12 col-xs-12">
                <div class="panel panel-default text-center">
                    <div class="panel-body">
                        <div class="btn-login-wrapper">
                            @if (in_array('google', $authMethods))
                                <a href="{{ url('redirect/google') }}" class="btn-login-google">
                                    <img class="img-responsive" src="{{ asset('/images/login-google@2x.png') }}"
                                         alt="Sign in with Google">
                                </a>
                            @endif
                            @if (in_array('apple', $authMethods))
                                <a href="#" class="btn-login-apple">
                                    <img class="img-responsive" src="{{ asset('/images/login-apple@2x.png') }}"
                                         alt="Sign in with Apple">
                                </a>
                            @endif
                        </div>

                        @if (in_array('passwordless', $authMethods))
                            <div class="row">
                                {{-- Show OR divider only if a social login is enabled --}}
                                @if (in_array('apple', $authMethods) || in_array('google', $authMethods))
                                    <div class='hr-or'></div>
                                @endif

                                <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2">
                                    <span class="hidden gcaptcha">
                                        {{ $gcaptcha }}
                                    </span>

                                    <form id="page-login__passwordless" method="POST"
                                          action="{{ route('passwordless-token-web') }}" accept-charset="UTF-8">

                                        {{ csrf_field() }}

                                        <div class="form-group">
                                            <label for="email">{{ trans('site.sign_in_with_email') }}</label>
                                            <input class="form-control" required="required"
                                                   placeholder="{{ trans('site.email_address') }}" name="email"
                                                   type="email" id="email">
                                            <div>
                                                <small>
                                                    We will send a code to your inbox
                                                </small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <input id="passwordless" class="btn btn-default btn-action pull-right"
                                                   type="submit" value="{{ trans('site.send') }}">
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                        @if (in_array('ldap', $authMethods))
                            <div class="row">
                                <div class="panel panel-default">
                                    <div class="panel-body">

                                        <form method="POST" action="{{ url('login/ldap') }}" accept-charset="UTF-8">
                                            {{ csrf_field() }}
                                            <div class="form-group">
                                                <label for="email">{{ trans('site.ldap_username') }}</label>
                                                <input class="form-control" required="required"
                                                       placeholder="{{ trans('site.ldap_username') }}" name="username"
                                                       type="text">
                                            </div>
                                            <div class="form-group">
                                                <label for="password">{{ trans('site.ldap_password') }}</label>
                                                <input class="form-control" required="required"
                                                       placeholder="{{ trans('site.ldap_password') }}" name="password"
                                                       type="password" value="">
                                            </div>
                                            <div class="form-group">
                                                <input class="btn btn-default btn-action pull-right" type="submit"
                                                       value="{{ trans('site.ldap_login') }}">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('scripts')
    <script src="https://www.google.com/recaptcha/api.js?render={{ env('GOOGLE_RECAPTCHA_SITE_KEY') }}"></script>
    <script type="text/javascript" src="{{ asset('js/users/users.js') . '?' . ENV('RELEASE') }}"></script>
@stop
