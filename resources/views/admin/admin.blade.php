@extends('app')
@section('title', trans('site.admin'))
@section('page-name', 'admin')

@section('content')
    <div class="container-fluid page-admin">
        <h2 class="page-title">{{ trans('site.admin')}}</h2>
        <div class="warning-well visible-xs-block">This section is best viewed on a larger screen</div>

        @include('toasts/success')
        @include('toasts/error')

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
