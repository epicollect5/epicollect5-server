@if (count($users) == 0)
    <p class="well">
        {{ trans('site.no_users_found') }}</p>
@else
    <div class="row">
    </div>
    <table class="table table-bordered table-hover table-condensed user-administration__table">
        <tr>
            <th class="text-center">{{ trans('site.name') }}</th>
            <th class="text-center">{{ trans('site.email') }}</th>
            <th class="text-center">{{ trans('site.state') }}</th>
            <th class="text-center">{{ trans('site.access') }}</th>
        </tr>
        @foreach ($users as $user)
            <tr>
                <td>
                    @if (!empty($user->name))
                        {{ $user->name . ' ' . $user->last_name }}
                    @else
                        <i>n/a</i>
                    @endif
                </td>
                <td>{{ $user->email }}</td>
                <td class="text-center">
                    <form method="POST" action="{{ url('/admin/update-user-state') }}" accept-charset="UTF-8"
                        class="user-administration__table__state-form form-inline">

                        <input type="hidden" name="email" value="{{ $user->email }}">
                        @if ($user->state === 'active' && $user->server_role !== 'superadmin')
                            <span>Active</span>
                            @if (is_array(Config::get('ec5Permissions.server.roles.' . $adminUser->server_role)) &&
                                    in_array($user->server_role, Config::get('ec5Permissions.server.roles.' . $adminUser->server_role)))
                                <input type="hidden" name="state" value="disabled">
                                <div class="form-group">
                                    <input type="submit"
                                        class="btn btn-xs btn-danger user-administration__table__state-submit--disable"
                                        value="Disable">
                                </div>
                            @endif
                        @elseif ($user->server_role === 'superadmin')
                            <span><i>{{ trans('site.sa_no_disable') }}</i></span>
                        @else
                            <span>{{ trans('site.disabled') }}</span>
                            @if (is_array(Config::get('ec5Permissions.server.roles.' . $adminUser->server_role)) &&
                                    in_array($user->server_role, Config::get('ec5Permissions.server.roles.' . $adminUser->server_role)))
                                <input type="hidden" name="state" value="active">
                                <div class="form-group">
                                    <input type="submit"
                                        class="btn btn-xs btn-action user-administration__table__state-submit--activate"
                                        value="{{ trans('site.activate') }}">
                                </div>
                            @endif
                        @endif
                    </form>
                </td>
                <td class="text-center">
                    <form method="POST" action="{{ url('/admin/update-user-server-role') }}" accept-charset="UTF-8"
                        class="user-administration__table__server-role-form form-inline">

                        <input type="hidden" name="email" value="{{ $user->email }}">
                        @if ($user->server_role === 'admin')
                            <span>{{ trans('site.admin') }}</span>
                            @if ($adminUser->server_role === 'superadmin')
                                <input type="hidden" name="server_role" value="basic">
                                <div class="form-group">
                                    <input type="submit"
                                        class="btn btn-xs btn-danger user-administration__table__server-role-submit--remove-as-admin"
                                        value="{{ trans('site.remove_as_admin') }}">
                                </div>
                            @endif
                        @elseif ($user->server_role === 'basic')
                            <span>{{ trans('site.basic') }}</span>
                            @if ($adminUser->server_role === 'superadmin')
                                <input type="hidden" name="server_role" value="admin">
                                <div class="form-group">
                                    <input type="submit"
                                        class="btn btn-xs btn-action user-administration__table__server-role-submit--make-admin"
                                        value="{{ trans('site.make_admin') }}">
                                </div>
                            @endif
                        @else
                            <span>{{ trans('site.super_admin') }}</span>
                        @endif
                    </form>
                </td>
            </tr>
        @endforeach
    </table>
    {{ $users->render() }}
@endif
