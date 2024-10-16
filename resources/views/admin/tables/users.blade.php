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
            <th class="text-center">Server Role</th>
        </tr>
        @foreach ($users as $user)
            <tr>
                <td class="text-right">
                    @if (!empty($user->name))
                        {{ $user->name . ' ' . $user->last_name }}
                    @else
                        <i>n/a</i>
                    @endif
                </td>
                <td class="text-right">{{ $user->email }}</td>
                <td class="text-center">{{ $user->server_role }}</td>
            </tr>
        @endforeach
    </table>
    {{ $users->render() }}
@endif
