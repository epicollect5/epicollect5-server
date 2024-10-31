<div class="panel panel-default page-mapping-data" data-url="{{ Request::fullUrl() }}">
    <div class="panel-heading">
        <span>{{ trans('site.mapping_data') }}</span>
        <a href="https://docs.epicollect.net/web-application/mapping-data" target="_blank"><i
                    class="material-symbols-outlined">help</i></a>
    </div>
    <div class="panel-body">
        <!-- Nav tabs -->
        {{-- Get list of mappings and set it as tabs --}}
        @include('project.mapping_data.tabs_navbar', [
            'project' => $requestAttributes->requestedProject,
        ])

        {{-- Tab panes --}}
        @include('project.mapping_data.tab_panel', [
            'mappings' => $requestAttributes->requestedProject->getProjectMapping()->getData(),
            'forms' => $requestAttributes->requestedProject->getProjectExtra()->getForms(),
            'projectExtra' => $requestAttributes->requestedProject->getProjectExtra()->getData(),
        ])
        <!-- Modal Mapping Data-->
        @include('project.mapping_data.modal')
    </div>
</div>

@section('scripts')
    <script type="text/javascript"
            src="{{ asset('js/project/project.js') . '?' . config('app.release') }}"></script>
@stop
