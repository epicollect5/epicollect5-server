@if (count($projectUsers) == 0) <p class="well"> {{ trans('site.no_users_found')}}</p>
@else
    <table class="table table-bordered manage-project-users__table">
        <tr>
            <th>{{ trans('site.name')}}</th>
            <th>{{ trans('site.email')}}</th>
            @if ($key == 'creator')
                <th class="text-center">{{ trans('site.transfer_ownership')}}</th>
            @else
                <th></th>
                <th></th>
            @endif

        </tr>
        @foreach ($projectUsers as $user)
            <tr>
                <td>@if(!empty($user->name)){{ $user->name . ' ' . $user->last_name }} @else <i>n/a</i> @endif
                </td>
                <td>{{ $user->email }}</td>
                <td class="text-center">
                    <form method="POST" action="{{ url('myprojects/' . $project->slug . '/remove-role') }}"
                          accept-charset="UTF-8" class="manage-project-users__table__remove-form">

                        {{ csrf_field() }}

                        <input type="hidden" name="per-page" value="{{ $projectUsers->perPage() }}">
                        <input type="hidden" name="total-users" value="{{ count($projectUsers) }}">

                        <input type="hidden" name="email" value="{{ $user->email }}">
                        @if ($key === 'creator')
                            {{--Show button to transfer ownership to project creator or admins--}}
                            @if($canTransferOwnership)
                                <a class="btn btn-action btn-sm"
                                   href="{{ url('myprojects') . '/' . $project->slug . '/transfer-ownership' }}"
                                >
                                    {{trans('site.transfer_ownership')}}
                                </a>
                            @else
                                <span>
                                <i> {{trans('site.cannot_transfer_ownership')}}</i>
                                </span>
                            @endif


                            {{--{{dd(is_array(Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole())), in_array($key, Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole())), $key )}}--}}

                        @elseif(is_array(Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole())) &&
                                in_array($key, Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole())))
                            <div class="form-group">
                                <input type="submit"
                                       class="btn btn-xs btn-danger manage-project-users__table__remove-form__submit"
                                       value="{{trans('site.remove')}}">
                            </div>
                        @else
                            <span><i>{{trans('site.cannot_remove_a') . $requestedProjectRole->getRole()}}</i></span>
                        @endif
                    </form>
                </td>
                @if ($key !== 'creator')
                    <td class="text-center">
                        {{--Active user cannot remove himself--}}
                        @if($user->email !== $requestedProjectRole->getUser()->email
                        && in_array($key, Config::get('ec5Permissions.projects.roles.' . $requestedProjectRole->getRole())))
                            <button class="btn btn-xs btn-action manage-project-users__switch-role"
                                    type="button"
                                    data-project-slug="{{$project->slug}}"
                                    data-user-role="{{$key}}"
                                    data-user-email="{{$user->email}}"
                            >
                                {{trans('site.switch_role')}}
                            </button>

                        @else
                            <span><i>{{trans('site.cannot_switch_role')}}</i></span>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </table>
    {{ $projectUsers->render() }}
@endif
