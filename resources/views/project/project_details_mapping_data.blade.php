<div class="panel panel-default page-mapping-data" data-url="{{Request::fullUrl()}}">
    <div class="panel-heading">
        <span>{{ trans('site.mapping_data') }}</span>
        <a href="#" data-toggle="modal" data-target="#mapping-data-info"><i class="material-icons">help</i></a>
    </div>
    <div class="panel-body">
        <!-- Nav tabs -->
    {{--Get list of mappings and set it as tabs--}}
    @include('project.mapping_data.tabs_navbar', [
    'project' => $project
    ])

    {{-- Tab panes --}}
    @include('project.mapping_data.tab_panel', [
    'mappings' => $project->getProjectMapping()->getData(),
    'forms' => $allForms,
    'projectExtra' => $project->getProjectExtra()->getData()
    ])
    <!-- Modal Mapping Data-->
        @include('project.mapping_data.modal')
    </div>
</div>

<div id="mapping-data-info" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Mapping Data</h4>
            </div>
            <div class="modal-body">
                <p>Mapping data is an exclusive Epicollect5 feature where you can assign a <strong>short identifier</strong> to each question or to each possible answer.</p>
                <p>This is particularly useful when you want to download your data in CSV format and you need the column name to match an existing identifier, or some possible answer to be mapped against a number or a code.</p>
                <p>For example, a question like <em>"What is your name"</em> can be mapped against just <em>"name".</em></p>
                <p>For possible answers, if the options are like "Red", "Blue" and "Green", they could be mappend against "R", "B", "G".</p>
                <p>Each short identifier (the 'Mapping To" field) for <strong>questions</strong> must be from 1 to 20 chars in lenght and can contain only alphanumeric and underscores <code>"_"</code></p>
                <p>For <strong>possible answers</strong>, up to 150 chars and any char is accepted aside from <code>'<'</code> and <code>'>'</code></p>
                <p>You can create up to 3 custom mappings and set one as the default, the one that will be used when downloading or accessing data via the API for any user who has got access to your data.</p>
                <p>The default mapping, called "EC5_AUTO", is generated automatically by the system and cannot be modified.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div><
    </div>
</div>


@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.ENV('RELEASE') }}"></script>
@stop
