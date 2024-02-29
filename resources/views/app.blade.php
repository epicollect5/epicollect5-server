<!DOCTYPE html>
<html lang="en">
<head>
    @include('header')
</head>
<body id="@yield('page-name')">
<!--[if lt IE 9]>
<p closeass="browsehappy">You are using an <strong>outdated</strong> browser.
    Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.
</p>
<![endif]-->
<div class="wait-overlay"></div>
<div class="container-fluid">
    @if(Route::currentRouteName() === 'project-open')
        @include('navbar-open')
    @else
        @include('navbar', ['hideMenu' => $hideMenu ?? false])
    @endif

    @yield('content')

</div>

@include('footer')

@yield ('scripts')

</body>
</html>
