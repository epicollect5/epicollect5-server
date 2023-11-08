<div class="panel panel-default">
    <div class="panel-heading">
        <span>{{ trans('site.apps') }}</span>
        <button
                @if (count($apps) != 0) disabled @endif
        class="btn btn-sm btn-action pull-right"
                data-toggle="modal"
                id="create-app" data-target="#modal-create-app"
                href="#">{{ trans('site.create_new_app') }}
        </button>
    </div>
    <div class="panel-body project-apps">

        @include('toasts/success')
        @include('toasts/error')

        <div class="project-apps-list">
            @include('project.developers.apps_table')
        </div>

    </div>
</div>

<!-- app create modal -->
@include('modals.modal_app_create')
<!-- app delete modal -->
@include('modals/modal_app_delete')

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.Config::get('app.release') }}"></script>
@stop
