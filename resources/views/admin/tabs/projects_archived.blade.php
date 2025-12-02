<div class="row">
    {{-- All Projects --}}
    <div class="col-lg-12 col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row projects-list__filter-controls">

                    <div class="col-xs-12">
                        <div class="projects-list__filter-controls_dropdowns pull-right">
                            <div class="btn-group filter-controls__order_by" role="group">
                                <button type="button" class="btn btn-sm btn-static">Sort By:</button>
                                <div class="btn-group" role="group">
                                    <button type="button"
                                            class="btn btn-sm btn-action dropdown-toggle"
                                            data-toggle="dropdown"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            data-selected-value="entries"
                                    >
                                        <span class="dropdown-text">Entries</span>
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu__archived pull-right">
                                        <li data-filter-value="entries" data-archived="true"><a href="#">Entries</a>
                                        </li>
                                        <li data-filter-value="storage" data-archived="true"><a href="#">Storage</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                {{-- Include Projects Archived table view --}}

                <div class="projects-list table-responsive">
                    <div class="loader projects-loader hidden">Loading...</div>
                    @include('admin.tables.projects_archived')
                </div>

            </div>
        </div>

    </div>
</div>
<span class="url hidden" data-js="{{url('admin/projects')}}"></span>

@section('scripts')
    <script src="{{ asset('/js/admin/admin.js') }}"></script>
@stop
