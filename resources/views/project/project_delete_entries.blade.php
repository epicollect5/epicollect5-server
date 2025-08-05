@extends('app')
@section('title', 'Delete Project Entries')
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-entries-deletion'
         data-chunk-size="{{config('epicollect.setup.bulk_deletion.chunk_size')}}">
        @include('modals/modal_deletion')

        <div class="row">
            <div href="#" class="col-sm-12 col-md-6 col-lg-6 col-md-offset-3 col-lg-offset-3">
                <div class="panel panel-default ">

                    <div class="panel-body">
                        <div class="project-logo-wrapper">
                            <img class="project-logo img-responsive img-circle" width="256" height="256"
                                 alt="Project logo"
                                 src="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                                 @else
                                 {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
                                 @endif">

                            <div class="loader"></div>
                        </div>
                    </div>

                    <div class="panel-body text-center">
                        <h2 class="project-name">{{ $requestAttributes->requestedProject->name }}</h2>
                        <p class="warning-well">{!! trans('site.confirm_deletion_entries', ['totalEntries' => $totalEntries]) !!}</p>

                        <div class="delete-entries-wrapper" data-total-entries="{{$totalEntries}}">
                            <div class="form-group">
                                <label for="project-name"
                                       class="control-label">{{ trans('site.confirm_project_name') }}</label>
                                <input id="project-name" type="text" class="form-control" name="project-name"
                                       placeholder="{{ trans('site.project_name') }}" required>
                            </div>
                            <a class="btn btn-sm btn-action pull-left btn-cancel-deletion"
                               href="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug }}">{{ trans('site.cancel') }}</a>
                            <div class="form-group">
                                <input required class="btn btn-danger btn-sm pull-right btn-delete-entries" disabled
                                       value="{{ trans('site.delete') }}">
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div><!-- end row -->
    </div>

@stop

@section('scripts')
    <script type="text/javascript"
            src="{{ asset('js/project/project.js') . '?' . config('app.release') }}"></script>
@stop
