<tr data-input-ref="{{$groupInput['ref']}}" data-input-type="{{$groupInput['type']}}" data-is-group-input="true">
    <td class="mapping-data__group mapping-data__hide-checkbox ">
        {{--Get the hide boolean for each input--}}
        <input type="checkbox"
               value="" @if($mappedGroupInput['hide']) checked @endif
               @if ($mapIndex == 0) disabled @endif>
    </td>
    <td class="mapping-data__group">
        {{--Question text here--}}
        <span>{{$groupInput['question']}}</span>
    </td>
    <td class="mapping-data__group mapping-data__map-to">
        <input
                type="text"
                class="form-control"
                placeholder="Type identifier..."
                maxlength="20"
                value="{{$mappedGroupInput['map_to']}}"
                @if ($mapIndex == 0) readonly disabled
                @endif
        >
    </td>
</tr>

{{--Deal with multiple answer branch inputs, checking if there are any--}}
@if (count($groupInput['possible_answers']) > 0)
    @include('project.mapping_data.tab_panel_table_possible_answers', [
    'possibleAnswers' => $groupInput['possible_answers'],
    'possibleAnswersMapping' => $mappedGroupInput['possible_answers'],
    'isBranch' => false,
     'isGroup' => true
    ])
@endif