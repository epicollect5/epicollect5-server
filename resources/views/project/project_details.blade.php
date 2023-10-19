@extends('app')
@section('page-name', Route::getCurrentRoute()->uri())
@section('title', trans('site.project_details'))
@section('content')

    <div class="container-fluid page-project-details">
        <div class="warning-well visible-xs-block">This section is best viewed on a larger screen</div>

        <div class="row">
            <h1 class="page-title">{{ $project->name }} <br/>
                <small class="project-homepage-url">
                    {{ trans('site.project_home_page') }}:
                    <a target="_blank"
                       href="{{ url('project') . '/' . $project->slug }}">{{ url('project') . '/' . $project->slug }}</a>
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

                {{-- Error handling --}}
                @if (!$errors->isEmpty())
                    @foreach ($errors->all() as $error)
                        @if (strpos($error, 'ec5_') === false)
                            {{--error was already translated--}}
                            <div class="var-holder-error" data-message="{{$error}}"></div>
                        @else
                            {{--translate error--}}
                            <div class="var-holder-error" data-message="{{trans('status_codes.' . $error)}}"></div>
                        @endif
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
                    <div class="var-holder-success" data-message="{{ trans('status_codes.' . session('message')) }}">
                    </div>
                    <script>
                        EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
                    </script>
                @endif

                @if (isset($includeTemplate))
                    @if ($includeTemplate == 'edit')
                        @include('project.form_edit')
                    @endif
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
