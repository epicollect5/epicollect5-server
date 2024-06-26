<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#673C90">
    <meta name="description" content="{{$requestAttributes->requestedProject->small_description}}">

    {{--og protocol--}}
    <meta property="og:title" content="{{$requestAttributes->requestedProject->name}}"/>
    <meta property="og:description" content="{{$requestAttributes->requestedProject->small_description}}"/>
    <meta property="og:type" content="article"/>
    <meta property="og:image"
          content="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
          @else
          {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
          @endif"
    />
    <meta property="og:image:width" content="200"/>
    <meta property="og:image:height" content="200"/>


    <link href='https://fonts.googleapis.com/css?family=Arimo' rel='stylesheet' type='text/css'>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    @if(config('app.env') == 'production')
        <script defer data-domain="five.epicollect.net" src="https://analytics.cgps.dev/js/plausible.js"></script>
    @endif


    <title>Epicollect5 - {{$project->name}}</title>

    @include('favicon')

    {{-- Bootstrap theme for Epicollect5 --}}
    <link rel="stylesheet" type="text/css" href="{{ asset('css/vendor-site.css').'?'.config('app.release') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/site.css').'?'.config('app.release') }}">

    {{-- dataviewer css --}}
    <link rel="stylesheet"
          type="text/css"
          href="{{ asset('dataviewer/css/vendor-dataviewer.css').'?'.config('app.release') }}">
    <link rel="stylesheet"
          type="text/css"
          href="{{ asset('dataviewer/css/dataviewer.css').'?'.config('app.release') }}">

    {{-- Leaflet--}}
    <script src="https://unpkg.com/leaflet@1.6.0/dist/leaflet.js"></script>
    <!--DEFER is important, otherwise markerClusterGroup will be undefined -->
    <!--Loading library locally as it is not maintained anymore, I made some changes to zoomToLayer() see t.ly/PPHk -->
    <script src="{{asset('dataviewer/js/leaflet.markercluster.min.js').'?'.config('app.release')}}" defer></script>


    <script>
        window.EC5 = window.EC5 || {};
        window.EC5.SITE_URL = '{{url('')}}';
    </script>

</head>

<!--[if lt IE 9]>
<p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade
    your browser</a> to use this app.</p>
<![endif]-->

<body>
<div id="app"></div>
<script src="{{asset('dataviewer/js/dataviewer.js').'?'.config('app.release')}}"></script>
</body>
</html>
