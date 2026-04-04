<!DOCTYPE html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#673C90">
    <link href='https://fonts.googleapis.com/css?family=Arimo' rel='stylesheet' type='text/css'>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    @if(config('app.env') == 'production')
        <script defer data-domain="five.epicollect.net" src="https://analytics.cgps.dev/js/plausible.js"></script>
    @endif

    <title>Epicollect5 - PWA</title>

    @include('favicon')


    <link rel="stylesheet" type="text/css"
          href="{{ asset('pwa/css/vendor-ionic.css').'?'.config('app.release') }}">
    <link rel="stylesheet" type="text/css"
          href="{{ asset('pwa/css/leaflet.css').'?'.config('app.release') }}">
    <link rel="stylesheet" type="text/css"
          href="{{ asset('pwa/css/leaflet-fullscreen.css').'?'.config('app.release') }}">
    <link rel="stylesheet" type="text/css"
          href="{{ asset('pwa/css/app.css').'?'.config('app.release') }}">


</head>

<body>

<div id="app"></div>

<script defer src="{{ asset('pwa/js/vendor-capacitor.js').'?'.config('app.release') }}"></script>
<script defer src="{{ asset('pwa/js/vendor-common.js').'?'.config('app.release') }}"></script>
<script defer src="{{ asset('pwa/js/vendor-ionic.js').'?'.config('app.release') }}"></script>
<script defer src="{{ asset('pwa/js/vendor-vue.js').'?'.config('app.release') }}"></script>
<script defer src="{{ asset('pwa/js/app.js').'?'.config('app.release') }}"></script>

</body>

</html>
