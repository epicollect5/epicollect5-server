@extends('app')
@section('title', trans('site.admin'))
@section('page-name', 'admin')

@section('content')
    <div class="container-fluid page-admin">
        <h2 class="page-title">{{ trans('site.admin')}}</h2>
        <div class="warning-well visible-xs-block">This section is best viewed on a larger screen</div>

        {{-- Error handling --}}
        @if(!$errors->isEmpty())
            @foreach($errors->all() as $error)
                <div class="var-holder-error" data-message="{{trans('status_codes.'.$error)}}"></div>
            @endforeach
            <script>
                //get all errors
                var errors = '';
                $('.var-holder-error').each(function () {
                    errors += $(this).attr('data-message') + '</br>';
                });
                EC5.toast.showError(errors);
            </script>
        @endif

        {{-- Success Message --}}
        @if (session('message'))
            <div class="var-holder-success" data-message="{{trans('status_codes.'.session('message'))}}"></div>
            <script>
                EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
            </script>
        @endif

        {{-- Nav tabs --}}
        <ul class="nav nav-tabs">
            <li role="presentation" @if($action == 'user-administration') class="active" @endif>
                <a href="{{ url('admin/user-administration') }}">{{trans('site.users')}}
                </a>
            </li>
            <li role="presentation" @if($action == 'projects') class="active" @endif>
                <a href="{{ url('admin/projects') }}">{{trans('site.projects')}}
                </a>
            </li>
            <li role="presentation" @if($action == 'stats') class="active" @endif>
                <a href="{{ url('admin/stats') }}">{{trans('site.stats')}}
                </a>
            </li>
        </ul>

        {{-- Tab panes --}}
        <div class="tab-content">
            @if($action == 'user-administration')
                <div class="tab-pane active user-administration" id="user-administration">
                    @include('admin.tabs.users')
                </div>
            @elseif ($action == 'projects')
                <div class="tab-pane active projects" id="projects">
                    @include('admin.tabs.projects')
                </div>
            @elseif ($action == 'stats')
                <div class="tab-pane active stats" id="stats">
                    @include('admin.tabs.stats')
                </div>
            @endif
        </div>
    </div>

@stop
