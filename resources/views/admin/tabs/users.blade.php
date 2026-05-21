<div class="row">
    {{-- All Users --}}
    <div class="col-lg-12 col-md-12 equal-height">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-4">
                        <input type="text"
                               name="search"
                               class="form-control user-administration__user-search"
                               placeholder="{{trans('site.search_for_user')}}">
                    </div>
                    <div class="col-xs-2">
                        <select name="filteroption"
                                class="form-control user-administration__user-filter__server-role">
                            <option value="" selected>All Server Role(s)</option>
                            <option value="superadmin">Superadmin</option>
                            <option value="admin">Admin</option>
                            <option value="basic">Basic</option>
                        </select>
                    </div>
                    <div class="col-xs-2">
                        <select name="filteroption"
                                class="form-control user-administration__user-filter__state">
                            <option value="" selected>All Status(es)</option>
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                            <option value="unverified">Unverified</option>
                        </select>
                    </div>

                    <div class="col-xs-4">
                        <div class="btn-group pull-right" role="group">
                            <button type="button" class="btn btn-action user-administration__user-clear hidden-xs">
                                {{trans('site.clear')}}
                            </button>
                            <button type="button" class="btn btn-action user-administration__user-clear visible-xs">
                                <span class="material-icons">filter_list_off</span>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                {{-- Include Users view --}}
                <div class="user-administration__users table-responsive">
                    @include('admin.tables.users')
                </div>
            </div>
        </div>
    </div>

</div>


@section('scripts')
    <script src="{{ asset('/js/admin/admin.js') }}"></script>
@stop
