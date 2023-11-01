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
            </tr>
        @endforeach
    </table>
    {{ $users->render() }}
@endif
