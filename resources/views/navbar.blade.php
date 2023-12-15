<nav class="navbar navbar-default navbar-fixed-top site-navbar">
    <noscript>
        <div class="warning-well warning-no-js">
            <strong>Epicollect5 requires Javascript enabled to work properly</strong>
        </div>
    </noscript>

    <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar"
                aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="{{ url('/') }}">
            <img src="{{ asset('/images/brand.png') }}" width="180" height="40"
                 alt="Epicollect5: Mobile & Web Application for free and easy data collection.">
        </a>
        <!--googleoff: index-->
        {{--<span class="beta-warning">BETA</span>--}}
        <!--googleon: index-->
    </div>
    <h1 class="home-seo">Epicollect5</h1>

    <div id="navbar" class="navbar-collapse collapse">
        @if(!$hideMenu)
            <ul class="nav navbar-nav navbar-right">
                {{--Logged in options--}}
                @if (Auth::guard()->check())
                    <li class="no-hover">
                        <img class="navbar-brand img-circle" alt="Your avatar" src="
                    @if (Auth::user()->avatar)
                        {{ Auth::user()->avatar }}
                        @else
                        {{ asset('images/avatar-placeholder.png') }}
                        @endif
                                ">
                    </li>
                    <li class="no-hover">
                        <p class="navbar-text">{{ trans('site.hi') }},
                            <strong>
                                <a href="{{ url('/profile')}}">
                                    {{ str_limit(Auth::user()->name, 15, '...')  }}
                                </a>
                            </strong>
                        </p>
                    </li>

                    @if(Auth::user()->isLocalAndUnverified())
                        <li @if (Request::is('signup/verification')) class="active" @endif>
                            <a
                                    href="{{ url('/signup/verification') }}">
                                <i class="material-icons">
                                    verified_user
                                </i>&nbsp;{{ trans('site.verification') }}
                            </a>
                        </li>
                    @else
                        <li @if (Request::is('myprojects')) class="active" @endif><a href="{{ url('/myprojects') }}"><i
                                        class="material-icons">
                                    &#xE2C8;</i>&nbsp;{{ trans('site.my_projects') }}</a></li>
                        <li @if (Request::is('myprojects/create')) class="active" @endif><a
                                    href="{{ url('/myprojects/create') }}"><i class="material-icons">
                                    &#xE148;</i>&nbsp;{{ trans('site.create_project') }}</a></li>
                    @endif
                @endif

                {{--Find project--}}
                {{--Show only on production server--}}
                @if(config('app.env') == 'production')
                    <li @if (Request::is('projects/*') || Request::is('projects')) class="active" @endif><a
                                href="{{ url('/projects/') }}">
                            <i class="material-icons">&#xE880;</i>&nbsp;{{ trans('site.find_project') }}</a>
                    </li>
                @endif

                {{--User guide link--}}
                <li>
                    <a href="https://docs.epicollect.net" target="_blank">
                        <i class="material-icons">
                            {{--chrome_reader_mode--}}
                            launch
                        </i>
                        User Guide
                    </a>
                </li>

                {{--Logged in options--}}
                @if(Auth::guard()->check())
                    @if (Auth::user()->isAdmin() || Auth::user()->isSuperAdmin())
                        <li @if (Request::is('admin')) class="active" @endif><a href="{{ url('/admin/stats') }}"><i
                                        class="material-icons">
                                    &#xE31E;</i>&nbsp;{{ trans('site.admin') }}</a></li>
                    @endif
                    <li><a href="{{ url('logout') }}"><i
                                    class="material-icons">&#xE879;</i>&nbsp;{{ trans('site.logout') }}
                        </a></li>

                @else
                    {{--Logged out options--}}
                    <li @if (Request::is('login')) class="active" @endif><a href="{{ url('login') }}"><i
                                    class="material-icons">&#xE7FF;</i>&nbsp;{{ trans('site.login') }}
                        </a></li>
                @endif
            </ul>
        @endif
    </div>
</nav>

