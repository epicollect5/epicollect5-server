<div class="panel panel-default page-manage-users">

    <div class="panel-heading">
        <span>{{trans('site.manage_users')}}</span>
        @if($requestedProjectRole->canAddUsers())
            <div class="btn-group pull-right" role="group">
                <button
                        class="btn btn-action btn-sm"
                        data-toggle="modal"
                        data-target="#ec5ModalExistingUser"
                >
                    <i class="material-icons">account_circle</i>
                    Add
                </button>

                <div class="btn-group manage-user-more" role="group">
                    <button type="button" class="btn btn-action btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
                            aria-expanded="false">
                        <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li class="dropdown-header text-center text-warning">Currently in beta.</li>
                        <li class="manage-user-more__import-users">
                            <input type="file" class="manage-user-more__import-users__input-file" style="display:none" accept=".csv">
                            <a href="#">Import users csv</a>
                        </li>
                        <li class="manage-user-more__export-users">
                            <a href="#" data-project-slug="{{$project->slug}}">Export users csv</a>
                        </li>
                    </ul>
                </div>
            </div>
        @endif
    </div>
    <div class="panel-body">

        {{-- Nav tabs --}}
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active">
                <a class="creator-tab-btn" href="#creator" aria-controls="creator" role="tab" data-toggle="tab">{{trans('site.creator')}}</a>
            </li>
            <li role="presentation">
                <a class="manager-tab-btn" href="#manager" aria-controls="manager" role="tab" data-toggle="tab">{{trans('site.managers')}}</a>
            </li>
            <li role="presentation">
                <a class="curator-tab-btn" href="#curator" aria-controls="curator" role="tab" data-toggle="tab">{{trans('site.curators')}}</a>
            </li>
            <li role="presentation">
                <a class="collector-tab-btn" href="#collector" aria-controls="collector" role="tab"
                   data-toggle="tab">{{trans('site.collectors')}}</a>
            </li>
            <li role="presentation">
                <a class="viewer-tab-btn" href="#viewer" aria-controls="viewer" role="tab"
                   data-toggle="tab">{{trans('site.viewers')}}</a>
            </li>
        </ul>

        {{-- Tab panes --}}
        <div class="tab-content">

            {{-- Loop through each set of users - creator, manager, curator, collector --}}
            @foreach ($users as $key => $projectUsers)

                <div role="tabpanel" class="tab-pane @if($key == 'creator') active @endif manage-project-users"
                     id="{{ $key }}" data-page-name="page-{{ $key }}">
                    <div class="row flexbox">

                        {{-- Project Users --}}

                        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 equal-height">
                            <div class="panel panel-default">
                                @if($key !== 'creator')
                                    <div class="panel-heading">
                                        <div class="row">

                                            <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
                                                <input type="text" name="search"
                                                       class="form-control manage-project-users__user-search"
                                                       placeholder="Search for {{ ucfirst($key) }}">
                                            </div>
                                            <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">

                                                <div class="btn-group manage-project-users_by-role pull-right" role="group">
                                                    <button type="button" class="btn btn-sm btn-default manage-project-users__reset">Clear Search
                                                    </button>
                                                    @if(!($key === 'manager' && $requestedProjectRole->getRole() === 'manager'))
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-default dropdown-toggle"
                                                                    data-toggle="dropdown"
                                                                    aria-haspopup="true" aria-expanded="false">
                                                                <span class="caret"></span>
                                                            </button>
                                                            @if($requestedProjectRole->canRemoveUsers())

                                                                <ul class="dropdown-menu dropdown-menu-right">
                                                                    <li class="dropdown-header text-center text-warning">Currently in beta.</li>
                                                                    <li class="manage-project-users__delete-by-role" data-role="{{$key}}"
                                                                        data-project-slug="{{$project->slug}}">
                                                                        <a href="#">Remove all {{ ucfirst($key) }}s</a>
                                                                    </li>
                                                                </ul>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div class="panel-body">
                                    {{-- Include Users page view --}}
                                    <div class="manage-project-users__page-{{ $key }} table-responsive">
                                        @include('project.project_users', ['projectUsers' => $projectUsers])
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach

        </div>
    </div>
</div>

<!-- Modal 1 -->
<div class="modal fade" id="ec5ModalExistingUser" tabindex="-1" role="dialog" aria-labelledby="ec5ModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ url('myprojects/' . $project->slug . '/add-role') }}"
                  accept-charset="UTF-8" class="manage-project-users__existing-user-add-form">

                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="ec5ModalLabel">{{ trans('site.add_user_to_project') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="email">{{ trans('site.email') }}</label>
                        <input type="email" name="email" class="form-control manage-project-users__user-add-form__email"
                               placeholder="{{trans('site.email_address')}}" required>
                    </div>

                    <div class="form-group">
                        <label for="role">{{ trans('site.role') }}</label>
                        <select name="role" class="form-control" required>
                            @if(is_array(Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole())))
                                {{--If we have a creator/admin/superadmin, they get creator priviledges --}}
                                @if($requestedProjectRole->isCreator())
                                    @foreach (Config::get('ec5Permissions.projects.roles.creator') as $role)
                                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                    @endforeach
                                @else
                                    @foreach (Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole()) as $role)
                                        <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                                    @endforeach
                                @endif
                            @endif
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">{{ trans('site.close') }}</button>
                    <input type="submit" class="btn btn-primary" value="{{trans('site.add_user')}}">
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="ec5ModalImportUsers" tabindex="-1" role="dialog" aria-labelledby="ec5ModalLabel"
     aria-hidden="true"
     data-post-url="{{ url('api/internal/project-users/' . $project->slug . '/add-users-bulk') }}"
>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Import users by email</h4>
            </div>
            <div class="modal-body">
                <form>

                    <div class="form-group users__selected-column">
                        <label>Select email column</label>
                        <div class="users-column-picker btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                Pick column <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                            </ul>
                        </div>
                    </div>

                    <hr />

                    <div class="form-group users__first-row-headers">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" checked> First row contains headers
                            </label>
                        </div>
                    </div>

                    <hr />

                    <div class="form-group users__pick-role">
                        <p><strong>Select user role</strong></p>
                       {{--Only CREATOR role can add managers--}}
                        @if($requestedProjectRole->getRole() === 'creator')
                            <div class="radio">
                                <label>
                                    <input type="radio" name="userRoleOptions" id="manager" value="manager">
                                    Manager
                                </label>
                            </div>
                        @endif
                        <div class="radio">
                            <label>
                                <input type="radio" name="userRoleOptions" id="curator" value="curator">
                                Curator
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="userRoleOptions" id="collector" value="collector" checked>
                                Collector
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="userRoleOptions" id="viewer" value="viewer">
                                Viewer
                            </label>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-default pull-left" data-dismiss="modal">Dismiss</button>
                <button type="button" class="btn btn-default btn-action users-perform-import" disabled>Import</button>
            </div>
        </div>
    </div>
</div>

{{-- Switch user modal--}}
<div class="modal fade" id="ec5SwitchUserRole" tabindex="-1" role="dialog" aria-labelledby="ec5ModalLabel"
     aria-hidden="true"
>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Switch user role</h4>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <p>
                            You are about to change the user role for:
                        </p>
                        <p class="user-email"><strong></strong></p>
                    </div>
                    <hr />
                    <div class="form-group users__pick-role">
                        <p><strong>Select new role</strong></p>
                        @if($requestedProjectRole->getRole() === 'creator')
                            <div class="radio role-manager">
                                <label>
                                    <input type="radio" name="userRoleOptions" id="manager" value="manager">
                                    Manager
                                </label>
                            </div>
                        @endif
                        <div class="radio role-curator">
                            <label>
                                <input type="radio" name="userRoleOptions" id="curator" value="curator">
                                Curator
                            </label>
                        </div>
                        <div class="radio role-collector">
                            <label>
                                <input type="radio" name="userRoleOptions" id="collector" value="collector">
                                Collector
                            </label>
                        </div>
                        <div class="radio role-viewer">
                            <label>
                                <input type="radio" name="userRoleOptions" id="viewer" value="viewer">
                                Viewer
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-default pull-left" data-dismiss="modal">Dismiss</button>
                <button type="button" class="btn btn-default btn-action switch-role-confirm" disabled>Confirm</button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.ENV('RELEASE') }}"></script>
@stop
