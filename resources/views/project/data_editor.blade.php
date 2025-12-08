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

    <title>Epicollect5 - Data Editor</title>

    @include('favicon')

    <link rel="stylesheet" type="text/css" href="{{ asset('css/vendor-site.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/site.css').'?'.config('app.release') }}">

    <link rel="stylesheet" type="text/css"
          href="{{ asset('data-editor/vendor/css/leaflet.css').'?'.config('app.release') }}">


    <script src="https://unpkg.com/leaflet@1.0.3/dist/leaflet.js"></script>

</head>

<body>

<div id="root"></div>

<script src="{{ asset('data-editor/data-editor.js').'?'.config('app.release') }}"></script>
</body>

</html>
