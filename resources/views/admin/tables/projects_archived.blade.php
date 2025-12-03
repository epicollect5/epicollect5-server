<div class="projects__table__wrapper">
    @if (count($projects) === 0)
        <p class="well">{{ trans('site.no_projects_found') }}</p>
    @else
        <table class="table table-bordered table-striped table-hover table-condensed projects__table">
            <tr class="bg-warning">
                <th class="text-center">Database Row ID</th>
                <th class="text-center">Archived Name</th>
                <th class="text-center" style="width:130px">Archived On</th>
                <th class="text-center">{{ trans('site.status') }}</th>
                <th class="text-center">{{ trans('site.entries') }}</th>
                <th class="text-center">Storage</th>
            </tr>
            @foreach ($projects as $project)
                <tr>
                    <td class="text-center">
                        <span class="project-id">&nbsp;
                          {{ $project->id }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="project-name-archived">&nbsp;
                            {{ str_limit($project->name, $limit = 36, $end = '...') }}
                        </span>
                    </td>

                    <td class="text-center">
                        {{ Carbon\Carbon::parse($project->updated_at)->format('jS M, Y')  }}
                    </td>
                    <td class="text-center">
                        {{ trans('site.' . $project->status) }}
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
