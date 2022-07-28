<table class="table table-bordered">
    <colgroup>
        <col width="100">
    </colgroup>
    <thead>
    <tr>
        <th>project</th>
        <th>{{ $project->name }}</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            <span>slug</span>
        </td>
        <td>
            <code>{{ $project->slug }}</code>
        </td>
    </tr>
    <tr>
        <td>
            <span>ref</span>
        </td>
        <td>
            <code>{{ $project->ref }}</code>
        </td>
    </tr>
    </tbody>
</table>


@foreach($forms as $form)
    <table class="table table-bordered">
        <colgroup>
            <col width="100">
        </colgroup>
        <thead>
        <tr>
            <th>form</th>
            <th>{{ $form['details']['name'] }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <span>slug</span>
            </td>
            <td>
                <code>{{ $form['details']['slug'] }}</code>
            </td>
        </tr>
        <tr>
            <td>
                <span>ref</span>
            </td>
            <td>
                <code>{{ $form['details']['ref'] }}</code>
            </td>
        </tr>


        {{--loop inputs in order--}}
        @foreach($projectExtra['forms'][$form['details']['ref']]['inputs'] as $index => $inputRef)
            @if ($projectExtra['inputs'][$inputRef]['data']['type'] == Config::get('ec5Strings.inputs_type.branch'))
                <tr>
                    <th>branch</th>
                    <th>{{$projectExtra['inputs'][$inputRef]['data']['question']}}</th>
                </tr>
                <tr>
                    <td>
                        <span>ref</span>
                    </td>
                    <td>
                        <code>{{$projectExtra['inputs'][$inputRef]['data']['ref']}}</code>
                    </td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>
@endforeach

@foreach($mappings as $mapIndex => $mapping)
    <table class="table table-bordered">
        <colgroup>
            <col width="100">
        </colgroup>
        <thead>
        <tr>
            <th>mapping</th>
            <th>
                {{ $mapping['name'] }}
                @if ($mapping['is_default']) <em>(default)</em>
                @endif
            </th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <span>map_index</span>
            </td>
            <td>
                <code>{{$mapIndex}}</code>
            </td>
        </tr>
        </tbody>
    </table>
@endforeach



<p>
    <a class="btn btn-action btn-sm pull-right" href="{{ url('/myprojects/'.$project->slug.'/download-structure')}}">
        <i class="material-icons">archive</i>
        {{trans('site.download_project_definition')}}
    </a>
</p>
