<div class="tab-content">
@if(isset($mappings))
    @foreach($mappings as $mapIndex => $mapping)
        <div id="map-data-tabcontent-{{$mapIndex}}"
             role="tabpanel"
             class="tab-pane fade @if ($mapping['is_default']) in active @endif"
             data-map-index="{{$mapIndex}}"
             data-map-name="{{$mapping['name']}}"
        >
            <div class="panel panel-default">
                <div class="panel-body">

                    {{--Render mapping controls--}}
                    @include('project.mapping_data.controls', [
                    'mapIndex' => $mapIndex,
                    'forms' => $forms
                    ])

                    {{--render mapping tables--}}
                    @foreach($forms as $formRef => $form)
                        @include('project.mapping_data.tab_panel_table', [
                        'mapping' => $mapping,
                        'projectExtra' => $projectExtra,
                        'formRef' => $formRef,
                        'mapIndex' => $mapIndex,
                        'isHidden' => !$loop->first
                        ])
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endif
</div>