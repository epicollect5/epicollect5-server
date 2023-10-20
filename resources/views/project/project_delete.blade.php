@extends('app')
@section('title', 'Delete Project')
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')
    <div class='container-fluid page-project-delete'>
        @if (session('message'))
            {{--trigger toast confirming a project was created successfully--}}
            <div class="var-holder-success" data-message="{{trans('status_codes.'.session('message'))}}"></div>
            <script>
                EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
            </script>
        @endif

        @include('modals/modal_deletion')

        <div class="row">
            <div href="#" class="project-home-wrapper col-sm-12 col-md-6 col-lg-6 col-md-offset-3 col-lg-offset-3">
                <div id="" class="panel panel-default ">

                    <div class="panel-body">
                        <a href="{{url('project/' . $project->slug . '/data')}}">
                            <img class="project-home__logo img-responsive img-circle" width="256" height="256"
                                 alt="Project logo" src="@if($project->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                 @else
                                 {{ url('/api/internal/media/'.$project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                 @endif">
                        </a>
                    </div>

                    <div class="panel-body text-center">
                        <h3 data-project-name="{{$project->name}}">{{ trans('site.confirm_delete_project', ['projectName' => $project->name]) }}</h3>

                        <form action="{{ url('myprojects') . '/' . $project->slug . '/delete' }}" class="delete-project"
                              method="POST">

                            {{ csrf_field() }}

                            <div class="form-group">
                                <label for="project-name"
                                       class="control-label">{{ trans('site.confirm_project_name') }}</label>
                                <input id="project-name" type="text" class="form-control" name="project-name"
                                       placeholder="{{ trans('site.project_name') }}" required>
                            </div>
                            <a class="btn btn-sm btn-action pull-left"
                               href="{{ url('myprojects') . '/' . $project->slug }}">{{ trans('site.cancel') }}</a>
                            <div class="form-group">
                                <input required type="submit" class="btn btn-danger btn-sm pull-right submit-delete"
                                       disabled name="submit" value="{{ trans('site.delete') }}">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div><!-- end col -->
    </div><!-- end row -->
@stop

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.ENV('RELEASE') }}"></script>
@stop