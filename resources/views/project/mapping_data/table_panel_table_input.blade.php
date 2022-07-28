{{--render a disable input for group owner or when the map is the EC5_AUTO, with map_index=0--}}
<tr
        data-input-ref="{{$input['data']['ref']}}"
        data-input-type="{{$input['data']['type']}}"
        data-top-level-input="true"
>
    <td class="mapping-data__hide-checkbox">
        {{--Get the hide boolean for each input--}}
        <input type="checkbox"
               value="" @if($mappedInput['hide']) checked @endif
               @if ($mapIndex == 0) disabled @endif
               @if ($type == 'group') disabled @endif
        >
    </td>
    <td>
        <div class="cell-overflow">{{$input['data']['question']}}</div>
    </td>
    <td class="mapping-data__map-to">
        <input
                type="text"
                class="form-control"
                placeholder="Type identifier..."
                maxlength="20"
                value="{{$mappedInput['map_to']}}"
                @if ($mapIndex == 0) readonly disabled @endif
                @if ($type == 'group') readonly disabled @endif
        >
    </td>
</tr>
