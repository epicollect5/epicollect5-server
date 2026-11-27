<!DOCTYPE html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    {{-- matches the built shell's viewport: PWA needs viewport-fit + the no-zoom set --}}
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#673C90">
    <meta name="color-scheme" media="(prefers-color-scheme: light)" content="light">
    <meta name="format-detection" content="telephone=no">
    <meta name="msapplication-tap-highlight" content="no">

    {{-- add to homescreen for ios --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Epicollect5">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">

    @if(config('app.env') == 'production')
        <script defer data-domain="five.epicollect.net" src="https://analytics.cgps.dev/js/plausible.js"></script>
    @endif

    <title>{{ $projectName }}</title>

    @include('favicon')

    @php
        //hot-reload dev loop: while the vite dev server runs, the mobile repo
        //(npm run pwa:browser) drops public/pwa/hot containing its URL, and
        //removes it when the server stops. While the file exists, the page
        //loads the dev bundle from the dev server instead of the built assets:
        //the page origin stays laravel (same-origin), so session, CSRF and the
        ///api/internal endpoints behave exactly like production. A stale hot
        //file pointing at a stopped server shows the loader forever: restart
        //the dev server or remove public/pwa/hot.
        $pwaHotFile = public_path('pwa/hot');
        $pwaHotUrl = null;
        if (is_file($pwaHotFile)) {
            $pwaHotUrl = rtrim(trim((string) file_get_contents($pwaHotFile)), '/');
        }
    @endphp

    @if ($pwaHotUrl !== null)
        {{-- the vite client injects the HMR runtime and the dev styles --}}
        <script type="module" crossorigin src="{{ $pwaHotUrl }}/@@vite/client"></script>
    @else
        @php
            //hashed PWA build: no ?v= anywhere - the content hashes are the cache
            //busting, and relative chunk imports carry them automatically. The
            //manifest maps the html entry to the current build's files.
            //A missing manifest means a broken/half-deployed build: fail loudly.
            $pwaManifestPath = public_path('pwa/.vite/manifest.json');
            if (!is_file($pwaManifestPath)) {
                abort(500, 'PWA manifest missing: run npm run pwa:deploy and push the assets');
            }
            $pwaManifest = json_decode(file_get_contents($pwaManifestPath), true);

            //the html entry (index-<hash>.js) bootstraps the app chunk
            $pwaEntry = $pwaManifest['index.html'];

            //collect every stylesheet the manifest lists (the entry facade carries
            //no css of its own: the app chunk holds the extracted styles)
            $pwaCssFiles = [];
            foreach ($pwaManifest as $pwaChunk) {
                if (!empty($pwaChunk['css'])) {
                    $pwaCssFiles = array_merge($pwaCssFiles, $pwaChunk['css']);
                }
            }
            $pwaCssFiles = array_unique($pwaCssFiles);
        @endphp

        @foreach ($pwaCssFiles as $pwaCssFile)
            <link rel="stylesheet" type="text/css" href="{{ asset('pwa/'.$pwaCssFile) }}">
        @endforeach
    @endif
    {{-- boot spinner loader: stable-named public asset, because site.css would break pwa layout --}}
    <link rel="stylesheet" type="text/css"
          href="{{ asset('pwa/assets/css/laravel-server-loader.css').'?v='.config('app.release') }}">
</head>

<body>

<div id="app">
    <div class="loader-placeholder">Loading...</div>
</div>

@if ($pwaHotUrl !== null)
    <script type="module" crossorigin src="{{ $pwaHotUrl }}/src/main.js"></script>
@else
    <script type="module" crossorigin src="{{ asset('pwa/'.$pwaEntry['file']) }}"></script>
@endif
</body>

</html>
