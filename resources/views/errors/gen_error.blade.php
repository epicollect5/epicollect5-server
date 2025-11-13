<!DOCTYPE html>
<html>
<head>
    @section('title', trans('site.error'))
    @include('header')
</head>
<body>

<div class="container-fluid">

    <nav class="navbar navbar-default navbar-fixed-top site-navbar">
        <div class="navbar-header">
            <a class="navbar-brand" href="{{ url('/') }}">
                <img src="{{ asset('/images/brand.png') }}"
                     width="180"
                     height="40"
                     alt="Epicollect5"
                >
            </a>
        </div>
    </nav>

    <div class='container-fluid page-error'>
        @if (count($errors->getMessages()) > 0)
            <div class="alert alert-danger">
                @foreach($errors->getMessages() as $key => $error)
                    @foreach($error as $key2 => $error2)
                        <p class="text-center">{{ config('epicollect.codes.' . $error2) }}</p>
                    @endforeach
                @endforeach

            </div>
        @endif

    </div>
</div>
@include('footer')
</body>
</html>
