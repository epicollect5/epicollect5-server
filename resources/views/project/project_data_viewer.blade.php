<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#673C90">

    <title>Epicollect5 - Dataviewer</title>

    @include('favicon')
    <script src="{{asset('/js/vendor-site.js')}}"></script>
    <script src="{{asset('/js/site.js') }}"></script>


    <link href='https://fonts.googleapis.com/css?family=Roboto:400,700' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">

    <link rel="stylesheet" type="text/css" href="{{asset('css/vendor-dataviewer.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('dataviewer/dataviewer.css') }}">
    <script>
        window.EC5 =  window.EC5 || {};
        window.EC5.SITE_URL = '{{url('')}}';
    </script>
</head>
<!--[if IE 9]>
<body class="loader-background ie9"> <![endif]-->
<!--[if gt IE 9]>
<body class="loader-background"><![endif]-->

<!--[if lt IE 9]>
<p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade
    your browser</a> to use this app.</p>
<![endif]-->

<body>

<div id="ec5-data-viewer"></div>
<footer>

</footer>
<script src="https://code.getmdl.io/1.1.3/material.min.js"></script>
<script src="{{ asset('js/vendor-dataviewer.js') }}"></script>
<script src="{{ asset('dataviewer/dataviewer.js') }}"></script>
</body>
</html>
