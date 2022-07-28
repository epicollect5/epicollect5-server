<tr id="{{$ref}}" class="manage-entries-limits__table__row @if($isBranch) entries-limits-branch__row @endif">
    <td>
        <span>{{$name}}</span>
    </td>
    <td>
        <div class="text-center">
        <input
                name="{{$ref}}[limit]"
                type="checkbox"
                class="input__set-limit"
                value="1"
               @if($projectExtra->getEntriesLimit($ref) !== null) checked @endif
        >
        </div>
    </td>
    <td>
        <div class="form-group">
        <input
                name="{{$ref}}[limitTo]"
                type="number"
                min="0"
                max="100000"
                step="1"
                class="input__limit-to form-control"
                value="{{$projectExtra->getEntriesLimit($ref) ?? ''}}"

                @if($projectExtra->getEntriesLimit($ref) === null) disabled @endif
        >
            <i class="fa fa-2x fa-times form-control-feedback hidden"></i>
        <input
                name="{{$ref}}[formRef]"
                type="hidden"
                value="{{$formRef}}"
        >
        <input
                name="{{$ref}}[branchRef]"
                type="hidden"
                value="{{$branchRef}}"
        >
        </div>
    </td>
</tr>