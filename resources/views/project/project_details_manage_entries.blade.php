<div class="panel panel-default page-manage-entries">
    <div class="panel-heading">
        <span>{{ trans('site.manage_entries') }}</span>
    </div>
    <div class="panel-body">
        {{-- Nav tabs --}}
        <ul class="nav nav-tabs">
            <li role="presentation" class="active">
                <a href="#limits" aria-controls="creator" role="tab" data-toggle="tab">{{ trans('site.limits') }}</a>
            </li>
            <li role="presentation">
                <a href="#deletion" aria-controls="creator" role="tab"
                   data-toggle="tab">{{ trans('site.deletion') }}</a>
            </li>
            <li role="presentation">
                <a href="#bulk-upload" aria-controls="creator" role="tab"
                   data-toggle="tab">{{ trans('site.bulk_upload') }}</a>
            </li>
        </ul>

        {{-- Tab panes --}}
        <div class="tab-content">
            @include('project.manage_entries.limits_tab_panel')

            @include('project.manage_entries.deletion_tab_panel')

            @include('project.manage_entries.bulk_upload_tab_panel')
        </div>
    </div>
</div>

@section('scripts')
    <script type="text/javascript"
            src="{{ asset('js/project/project.js') . '?' . config('app.release') }}"></script>
@stop
