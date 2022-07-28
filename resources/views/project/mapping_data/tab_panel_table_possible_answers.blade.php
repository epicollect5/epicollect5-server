<tr data-possible-answers="true" @if($isBranch) data-is-branch-input="true" @endif @if($isGroup) data-is-group-input="true" @endif>
    {{--todo 2 loops? can it be better?--}}
    <td class="@if($isBranch) mapping-data__branch @endif @if($isGroup) mapping-data__group @endif"></td>
    <td class="mapping-data__possible_answer__answer @if($isBranch) mapping-data__branch @endif @if($isGroup) mapping-data__group @endif">
        @foreach($possibleAnswers as $answerRef => $possibleAnswer)
            <div class="@if ($mapIndex == 0) cell-overflow-disabled @else cell-overflow @endif">
                {{$possibleAnswer['answer']}}
            </div>
        @endforeach
    </td>
    <td class="@if($isBranch) mapping-data__branch @endif @if($isGroup) mapping-data__group @endif mapping-data__possible_answer__map-to">
        @foreach($possibleAnswers as $answerRef => $possibleAnswer)
            <div>
            <input
                    type="text"
                    class="form-control"
                    placeholder="Type identifier..."
                    maxlength="150"
                    data-answer-ref="{{$possibleAnswer['answer_ref']}}"
                    value="{{$possibleAnswersMapping[$possibleAnswer['answer_ref']]['map_to']}}"
                    @if ($mapIndex == 0) readonly disabled
                    @endif
            >
            </div>
        @endforeach
    </td>
</tr>
