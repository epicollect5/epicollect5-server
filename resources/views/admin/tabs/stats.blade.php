<div class="row">
    {{-- SystemStats --}}
    <div id="app"></div>
</div>

<span class="url hidden" data-js="{{url('admin/entries')}}"></span>

<script src="{{ asset('data-admin/js/data-admin.js') .'?'.Config::get('app.release')}}"></script>
<link rel="stylesheet" type="text/css" href="{{ asset('data-admin/css/data-admin.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('data-admin/css/vendor/chartist.min.css') }}">
