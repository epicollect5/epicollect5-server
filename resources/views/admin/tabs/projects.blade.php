<div class="row">
    {{-- All Projects --}}
    <div class="col-lg-12 col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row projects-list__filter-controls">
                    <div class="col-xs-4">
                        <input type="text"
                               name="search"
                               class="form-control projects-list__project-search"
                               placeholder="{{trans('site.search_for_project')}}">
                    </div>

                    <div class="col-xs-8">
                        <div class="projects-list__filter-controls_dropdowns pull-right">
                            <div class="btn-group filter-controls__access" role="group">
                                <button type="button" class="btn btn-static btn-sm">Access:</button>
                                <div class="btn-group" role="group">
                                    <button type="button"
                                            class="btn btn-sm btn-action dropdown-toggle"
                                            data-toggle="dropdown"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            data-selected-value="any"
                                    >
                                        <span class="dropdown-text">Any</span>
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu pull-right">
                                        <li data-filter-value="any"><a href="#">Any</a></li>
                                        <li role="separator" class="divider"></li>
                                        <li data-filter-value="private"><a href="#">Private</a></li>
                                        <li data-filter-value="public"><a href="#">Public</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="btn-group filter-controls__visibility" role="group">
                                <button type="button" class="btn btn-sm btn-static">Visibility:</button>
                                <div class="btn-group" role="group">
                                    <button type="button"
                                            class="btn btn-sm btn-action dropdown-toggle"
                                            data-toggle="dropdown"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            data-selected-value="any"
                                    >
                                        <span class="dropdown-text">Any</span>
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu pull-right">
                                        <li data-filter-value="any"><a href="#">Any</a></li>
                                        <li role="separator" class="divider"></li>
                                        <li data-filter-value="hidden"><a href="#">Hidden</a></li>
                                        <li data-filter-value="listed"><a href="#">Listed</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="btn-group filter-controls__order_by" role="group">
                                <button type="button" class="btn btn-sm btn-static">Sort By:</button>
                                <div class="btn-group" role="group">
                                    <button type="button"
                                            class="btn btn-sm btn-action dropdown-toggle"
                                            data-toggle="dropdown"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            data-selected-value="any"
                                    >
                                        <span class="dropdown-text">Entries</span>
                                        <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu pull-right">
                                        <li data-filter-value="entries"><a href="#">Entries</a></li>
                                        <li data-filter-value="storage"><a href="#">Storage</a></li>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                {{-- Include Projects table view --}}

                <div class="projects-list table-responsive">
                    <div class="loader projects-loader hidden">Loading...</div>
                    @include('admin.tables.projects')
                </div>

            </div>
        </div>

    </div>
</div>
<span class="url hidden" data-js="{{url('admin/projects')}}"></span>

@section('scripts')
    <script src="{{ asset('/js/admin/admin.js') }}"></script>
@stop
