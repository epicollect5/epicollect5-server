@extends('app')
@section('title', 'Delete Project')
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')
    <div class='container-fluid page-project-delete'>
        @if (session('message'))
            @include('toasts/success')
        @endif

        @include('modals/modal_deletion')

        <div class="row">
            <div href="#" class="project-home-wrapper col-sm-12 col-md-6 col-lg-6 col-md-offset-3 col-lg-offset-3">
                <div id="" class="panel panel-default ">

                    <div class="panel-body">
                        <img class="project-home__logo img-responsive img-circle" width="256" height="256"
                             alt="Project logo" src="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                 @else
                                 {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                 @endif">
                    </div>

                    <div class="panel-body text-center">
                        <h3 data-project-name="{{$requestAttributes->requestedProject->name}}">
                            <?php $message = str_replace('\n', "\n", trans('site.confirm_delete_project', ['projectName' => $requestAttributes->requestedProject->name])); ?>
                            <span> {!! nl2br(e($message)) !!}</span>
                            {{--                            {{ trans('site.confirm_delete_project', ['projectName' => $requestAttributes->requestedProject->name]) }}--}}
                        </h3>

                        <form action="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug . '/delete' }}"
                              class="delete-project"
                              method="POST">

                            {{ csrf_field() }}

                            <div class="form-group">
                                <label for="project-name"
                                       class="control-label">{{ trans('site.confirm_project_name') }}</label>
                                <input id="project-name" type="text" class="form-control" name="project-name"
                                       placeholder="{{ trans('site.project_name') }}" required>
                            </div>
                            <a class="btn btn-sm btn-action pull-left"
                               href="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug }}">{{ trans('site.cancel') }}</a>
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
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.config('app.release') }}"></script>
@stop
