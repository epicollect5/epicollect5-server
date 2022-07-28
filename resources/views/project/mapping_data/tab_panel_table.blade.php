{{--generate table for all form,  top level form displayed by default, the others hidden--}}
<div class="table-responsive @if ($isHidden) hidden @endif" data-form-ref="{{$formRef}}" data-map-index="{{$mapIndex}}">
    <table class="table table-bordered table-condensed">
        <thead>
        <tr>
            <th scope="col">{{trans('site.hide')}}</th>
            <th scope="col">{{trans('site.question')}}</th>
            <th scope="col">{{trans('site.map_to')}}</th>
        </tr>
        </thead>
        @if (isset($projectExtra))
            @foreach($projectExtra['forms'][$formRef]['inputs'] as $index => $inputRef)

                {{--skip readme, we do not map those as no answer is given--}}
                {{--todo readme should be a constant--}}
                @if ($projectExtra['inputs'][$inputRef]['data']['type'] != Config::get('ec5Strings.inputs_type.readme'))
                    @include('project.mapping_data.table_panel_table_input' , [
                    'input' => $projectExtra['inputs'][$inputRef],
                    'mappedInput' => $mapping['forms'][$formRef][$inputRef],
                    'type' => $projectExtra['inputs'][$inputRef]['data']['type']
                    ])
                @endif

                {{--Deal with multiple answer inputs, checking if there are any--}}
                @if (count($projectExtra['inputs'][$inputRef]['data']['possible_answers']) > 0)
                    @include('project.mapping_data.tab_panel_table_possible_answers', [
                    'possibleAnswers' => $projectExtra['inputs'][$inputRef]['data']['possible_answers'],
                    'possibleAnswersMapping' => $mapping['forms'][$formRef][$inputRef]['possible_answers'],
                    'isBranch' => false,
                    'isGroup' => false
                    ])
                @endif

                {{--Is there any branches?--}}
                @if(isset($mapping['forms'][$formRef][$inputRef]['branch']))
                    @if(count($mapping['forms'][$formRef][$inputRef]['branch']) > 0)
                        @foreach($projectExtra['forms'][$formRef]['branch'][$inputRef] as $branchInputRef)

                            @if ($projectExtra['inputs'][$branchInputRef]['data']['type'] != Config::get('ec5Strings.inputs_type.readme'))
                                @include('project.mapping_data.tab_panel_table_branch', [
                             'branchInput' => $projectExtra['inputs'][$branchInputRef]['data'],
                             'branchInputRef' => $branchInputRef,
                             'mappedBranchInput' => $mapping['forms'][$formRef][$inputRef]['branch'][$branchInputRef]
                             ])
                            @endif

                        @endforeach
                    @endif
                @endif

                {{--Is there any group?--}}
                @if(isset($mapping['forms'][$formRef][$inputRef]['group']))
                    @if(count($mapping['forms'][$formRef][$inputRef]['group']) > 0)
                        @foreach($projectExtra['forms'][$formRef]['group'][$inputRef] as $groupInputRef)

                            @if ($projectExtra['inputs'][$groupInputRef]['data']['type'] != Config::get('ec5Strings.inputs_type.readme'))
                                @include('project.mapping_data.tab_panel_table_group', [
                            'groupInput' => $projectExtra['inputs'][$groupInputRef]['data'],
                            'mappedGroupInput' => $mapping['forms'][$formRef][$inputRef]['group'][$groupInputRef],
                             'isNestedGroup' => false
                            ])
                            @endif


                        @endforeach
                    @endif
                @endif
            @endforeach
        @endif
    </table>
</div>