@extends('app')
@section('title', trans('site.profile'))
@section('content')

    @include('toasts/error')
    @include('toasts/success')

    <div class="container page-profile">
        <h2 class="page-title">{{ trans('site.profile') }}</h2>
        <div class="row">
            <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8 col-lg-offset-2 col-md-offset-2 col-sm-offset-2  text-center">
                <div class="panel panel-default ">
                    <div class="panel-heading">
                        Account Details
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <p>
                                <span>You are logged in with the email <strong>{{ $email }}</strong>
                                </span>
                            </p>
                        </div>

                        @if (in_array('local', $providers))
                            <a href="{{ route('password-reset') }}">
                                <strong>Reset Password</strong>
                            </a>
                        @endif

                        {{-- Show account connection when email login is enabled --}}
                        {{-- IMP: do not show it to Local users --}}
                        @if (!in_array('local', $providers))
                            @if (in_array('passwordless', $auth_methods))

                                @if (in_array('google', $auth_methods))
                                    @if (in_array('google', $providers))
                                        <hr/>
                                        {{-- Google Account already connected --}}
                                        <div class="row">
                                            <form method="POST" action="{{ route('profile-disconnect-google') }}"
                                                  class="">
                                                {{ csrf_field() }}
                                                <button type="submit" class="btn btn-default">
                                                    <span class="material-icons">link_off</span>
                                                    <span class="hidden-xs"> Disconnect </span>
                                                    Google
                                                </button>
                                                <div class="hidden-xs">
                                                    <small>Connected account is <strong>{{ $email }}</strong></small>
                                                </div>
                                            </form>
                                        </div>
                                    @else
                                        <div class="row">
                                            <div class="">
                                                <a href="{{ route('profile-connect-google') }}" class="btn btn-action ">
                                                    <span class="material-icons">
                                                        add_link
                                                    </span>
                                                    <span class="hidden-xs"> Connect </span>
                                                    Google
                                                </a>
                                                <div class="hidden-xs">
                                                    <small>You can connect your Google Account only when its email is
                                                        <br/>
                                                        <strong>{{ $email }}</strong>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif



                                @if (in_array('apple', $auth_methods))
                                    <hr/>
                                    @if (in_array('apple', $providers))
                                        {{-- Apple Account already connected --}}
                                        <form method="POST" action="{{ route('profile-disconnect-apple') }}"
                                              class="">
                                            {{ csrf_field() }}
                                            <button type="submit" class="btn btn-default">
                                                <span class="material-icons">link_off</span>
                                                <span class="hidden-xs">Disconnect</span> Apple
                                            </button>
                                            <div class="hidden-xs">
                                                <small>Connected account is <strong>{{ $email }}</strong></small>
                                            </div>
                                        </form>
                                    @else
                                        <div class="row">
                                            <div class="">
                                                <button class="btn btn-action btn-connect-apple">
                                                    <span class="material-icons">
                                                        add_link
                                                    </span>
                                                    <span class="hidden-xs">Connect</span> Apple
                                                </button>
                                                <div class="hidden-xs">
                                                    <small>You can connect your Apple Account only when its email is
                                                        <br/>
                                                        <strong>{{ $email }}</strong>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8 col-lg-offset-2 col-md-offset-2 col-sm-offset-2 text-center">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        Danger Zone
                    </div>
                    <div class="panel-body">
                        <button class="btn btn-danger btn-account-delete" data-toggle="modal"
                                data-target="#modal__account-deletion">
                            <span class="material-icons">account_circle</span>
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('modals/modal_account_deletion')
    </div>
@stop

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/users/users.js') . '?' . config('app.release') }}"></script>
@stop
