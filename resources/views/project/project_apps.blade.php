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
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.ENV('RELEASE') }}"></script>
@stop
