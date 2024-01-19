@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.project_details'))
@section('content')

    @include('toasts/success')
    @include('toasts/error')

    <div class="container-fluid page-project-details">
        <div class="warning-well visible-xs-block">This section is best viewed on a larger screen</div>

        <div class="row">
            <h1 class="page-title">{{ $requestAttributes->requestedProject->name }} <br/>
                <small class="project-homepage-url">
                    {{ trans('site.project_home_page') }}:
                    <a target="_blank"
                       href="{{ url('project') . '/' . $requestAttributes->requestedProject->slug }}">{{ url('project') . '/' . $requestAttributes->requestedProject->slug }}</a>
                    <i class="material-icons copy-btn" data-toggle="tooltip" data-placement="top" title="Copied!"
                       data-trigger="manual">
                        content_copy
                    </i>
                </small>
            </h1>
            <div class="col-xs-12 col-sm-2 col-md-2 col-lg-2 sidebar">
                @include('project.project_details_sidebar')
            </div>
            <div class="col-xs-12 col-sm-10 col-md-10 col-lg-10 main">

                @include('toasts/success')
                @include('toasts/error')

                @if (isset($includeTemplate))
                    @if ($includeTemplate == 'view')
                        @include('project.project_details_content')
                    @endif
                    @if ($includeTemplate == 'manage-users')
                        @include('project.project_details_manage_users')
                    @endif
                    @if ($includeTemplate == 'mapping')
                        @include('project.project_details_mapping_data')
                    @endif
                    @if ($includeTemplate == 'clone')
                        @include('project.project_clone')
                    @endif
                    @if ($includeTemplate == 'manage-entries')
                        @include('project.project_details_manage_entries')
                    @endif
                    @if ($includeTemplate == 'api')
                        @include('project.project_api')
                    @endif
                    @if ($includeTemplate == 'apps')
                        @include('project.project_apps')
                    @endif
                @else
                    @include('project.project_details_content')
                @endif

            </div>
        </div>
    </div>
@stop
