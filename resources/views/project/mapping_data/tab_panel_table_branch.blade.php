<tr data-input-ref="{{$branchInput['ref']}}" data-input-type="{{$branchInput['type']}}" data-is-branch-input="true">
    <td class="mapping-data__hide-checkbox mapping-data__branch">
        {{--Get the hide boolean for each input--}}
        <input type="checkbox"
               value="" @if($mappedBranchInput['hide']) checked @endif
               @if ($mapIndex == 0) disabled @endif
        >
    </td>
    <td class="mapping-data__branch">
        {{--Question text here--}}
        <span>{{$branchInput['question']}}</span>
    </td>
    <td class="mapping-data__branch mapping-data__map-to">
        <input
                type="text"
                class="form-control"
                placeholder="Type identifier..."
                maxlength="20"
                value="{{$mappedBranchInput['map_to']}}"
                @if ($mapIndex == 0) readonly disabled
                @endif
        >
    </td>
</tr>

{{--Deal with multiple answer branch inputs, checking if there are any--}}
@if (count($branchInput['possible_answers']) > 0)
    @include('project.mapping_data.tab_panel_table_possible_answers', [
    'possibleAnswers' => $branchInput['possible_answers'],
    'possibleAnswersMapping' => $mappedBranchInput['possible_answers'],
    'isBranch' => true,
    'isGroup' => false
    ])
@endif


{{--Deal with nested group (inside a branch)--}}
{{--<pre>--}}
{{--{{print_r($mapping['forms'][$formRef][$inputRef]['branch'][$branchInputRef]['group'])}}--}}{{--Is there any nested group?--}}
{{--</pre>--}}

@if(isset($mapping['forms'][$formRef][$inputRef]['branch'][$branchInputRef]['group']))
    @if(count($mapping['forms'][$formRef][$inputRef]['branch'][$branchInputRef]['group']) > 0)

        @foreach($mapping['forms'][$formRef][$inputRef]['branch'][$branchInputRef]['group'] as $nestedGroupInputRef => $mappedNestedGroupInput)
            @include('project.mapping_data.tab_panel_table_group', [
            'groupInput' => $projectExtra['inputs'][$nestedGroupInputRef]['data'],
            'mappedGroupInput' => $mappedNestedGroupInput,
            'isNestedGroup' => true
            ])
        @endforeach
    @endif
@endif


