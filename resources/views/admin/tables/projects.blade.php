<div class="projects__table__wrapper">
    @if (count($projects) == 0)
        <p class="well">{{ trans('site.no_projects_found') }}</p>
    @else
        <table class="table table-bordered table-hover table-condensed projects__table">
            <tr>
                <th></th>
                <th class="text-center">{{ trans('site.name') }}</th>
                <th class="text-center">{{ trans('site.creator') }}</th>
                <th class="text-center" style="width:100px">{{ trans('site.created_at') }}</th>
                <th class="text-center">{{ trans('site.status') }}</th>
                <th class="text-center">{{ trans('site.visibility') }}</th>
                <th class="text-center">{{ trans('site.access') }}</th>
                <th class="text-center">{{ trans('site.entries') }}</th>
                <th class="text-center" style="width:120px">{{ trans('site.my_role') }}</th>
                <th></th>
            </tr>
            @foreach ($projects as $project)
                <tr>
                    <td class="text-center"><img class="project-logo" width="32" height="32"
                            src=" {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_mobile_logo') }}" />
                    </td>
                    <td>
                        <a title="{{ trans('site.view_project') }}" href="{{ url('project/' . $project->slug) }}">
                            <span class="project-name">&nbsp;
                                {{ str_limit($project->name, $limit = 36, $end = '...') }}
                            </span>
                        </a>
                    </td>
                    <td class="text-center">
                        {{ str_limit($project->user->name . ' ' . $project->user->last_name, $limit = 20, $end = '...') }}
                    </td>
                    <td class="text-center">
                        {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $project->created_at)->format('d M y') }}
                    </td>
                    <td class="text-center">
                        {{ trans('site.' . $project->status) }}
                    </td>
                    <td class="text-center">
                        {{ trans('site.' . $project->visibility) }}
                    </td>
                    <td class="text-center">
                        {{ trans('site.' . $project->access) }}
                    </td>
                    <td class="text-center">{{ $project->total_entries }}</td>
                    <td>
                        <div class="btn-group">
                            <select class="form-control project-roles" data-project-id="{{ $project->project_id }}">
                                <option value="">{{ trans('site.no_role') }}</option>
                                @foreach (Config::get('ec5Enums.project_roles') as $role)
                                    <option value="{{ $role }}"
                                        @if ($project->my_role == $role) selected @endif>
                                        {{ trans('site.project_roles.' . $role) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </td>
                    <td><a title="{{ trans('site.view_project_details') }}" class="btn btn-action btn-sm"
                            href="{{ url('myprojects/' . $project->slug) }}">Details</a></td>
                </tr>
            @endforeach
        </table>
        {{ $projects->links() }}
</div>
@endif
