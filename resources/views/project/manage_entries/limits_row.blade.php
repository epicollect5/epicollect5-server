<tr id="{{$ref}}" class="manage-entries-limits__table__row @if($isBranch) entries-limits-branch__row @endif">
    <td>
        <span>{{$name}}</span>
    </td>
    <td>
        <div class="text-center">
            <input
                    name="{{$ref}}[setLimit]"
                    type="checkbox"
                    class="input__set-limit"

                    @if($projectDefinition->getEntriesLimit($ref) !== null)
                        checked value="true"
                    @else
                        value="false"
                    @endif
            >
        </div>
    </td>
    <td>
        <div class="form-group">
            <input
                    name="{{$ref}}[limitTo]"
                    type="number"
                    min="0"
                    max="50000"
                    step="1"
                    class="input__limit-to form-control"
                    value="{{$projectDefinition->getEntriesLimit($ref) ?? ''}}"

                    @if($projectDefinition->getEntriesLimit($ref) === null) disabled @endif
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