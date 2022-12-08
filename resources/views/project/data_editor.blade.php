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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.2/dist/leaflet.css"
        integrity="sha256-sA+zWATbFveLLNqWO2gtiw3HL/lh1giY/Inf1BJ0z14=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.2/dist/leaflet.js"
        integrity="sha256-o9N1jGDZrf5tS+Ft4gbIK7mYMipq9lqpVJ91xHSyKhg=" crossorigin=""></script>
    <script defer data-domain="five.epicollect.net" src="https://analytics.cgps.dev/js/plausible.js"></script>

    <title>Epicollect5 - Data Editor</title>

    @include('favicon')

    <link href="{{ asset('data-editor/app/assets/css/laravel-server-loader.css') . '?' . ENV('RELEASE') }}"
        rel="stylesheet">
    <link href="{{ asset('data-editor/app/assets/css/Control.FullScreen.css') . '?' . ENV('RELEASE') }}"
        rel="stylesheet">
    <link href="{{ asset('data-editor/app/css/vendor-ionic.css') . '?' . ENV('RELEASE') }}" rel="stylesheet">
    <link href="{{ asset('data-editor/app/css/app.css') . '?' . ENV('RELEASE') }}" rel="stylesheet">

</head>




<body>
    <script defer="defer" src="{{ asset('data-editor/app/assets/js/Control.Fullscreen.js') . '?' . ENV('RELEASE') }}">
    </script>
    <script defer="defer" src="{{ asset('data-editor/app/js/vendor-capacitor.js') . '?' . ENV('RELEASE') }}"></script>
    <script defer="defer" src="{{ asset('data-editor/app/js/vendor-common.js') . '?' . ENV('RELEASE') }}"></script>
    <script defer="defer" src="{{ asset('data-editor/app/js/vendor-ionic.js') . '?' . ENV('RELEASE') }}"></script>
    <script defer="defer" src="{{ asset('data-editor/app/js/vendor-vue.js') . '?' . ENV('RELEASE') }}"></script>
    <script defer="defer" src="{{ asset('data-editor/app/js/app.js') . '?' . ENV('RELEASE') }}"></script>
    <div id="loader" class="loader-placeholder"></div>
    <div id="app"></div>
</body>

</html>
