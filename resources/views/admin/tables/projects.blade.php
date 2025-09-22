<div class="projects__table__wrapper">
    @if (count($projects) === 0)
        <p class="well">{{ trans('site.no_projects_found') }}</p>
    @else
        <table class="table table-bordered table-striped table-hover table-condensed projects__table">
            <tr>
                <th></th>
                <th class="text-center">{{ trans('site.name') }}</th>
                <th class="text-center">{{ trans('site.creator') }}</th>
                <th class="text-center" style="width:130px">Created On</th>
                <th class="text-center">{{ trans('site.status') }}</th>
                <th class="text-center">{{ trans('site.visibility') }}</th>
                <th class="text-center">{{ trans('site.access') }}</th>
                <th class="text-center">{{ trans('site.entries') }}</th>
                <th class="text-center">Storage</th>
            </tr>
            @foreach ($projects as $project)
                <tr>
                    <td class="text-center">
                        <img class="project-logo" width="32" height="32"
                             src=" {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_mobile_logo') }}"
                             alt="logo"/>
                    </td>
                    <td>
                        <a title="{{ trans('site.view_project') }}" href="{{ url('project/' . $project->slug) }}"
                           target="_blank">
                            <span class="project-name">&nbsp;
                                {{ str_limit($project->name, $limit = 36, $end = '...') }}
                            </span>
                        </a>
                    </td>
                    <td class="text-center">
                        {{ str_limit($project->user_name . ' ' . $project->user_last_name, $limit = 20, $end = '...') }}
                    </td>
                    <td class="text-center">
                        {{ Carbon\Carbon::parse($project->created_at)->format('jS M, Y')  }}
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
                    <td class="text-center"
                    >
                        {{ Common::formatBytes($project->total_bytes) }}
                    </td>
                </tr>
            @endforeach
        </table>
        {{ $projects->links() }}
</div>
@endif
