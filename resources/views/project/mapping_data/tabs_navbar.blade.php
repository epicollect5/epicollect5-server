<ul class="map-data__tabs nav nav-tabs" role="tablist">
    @foreach($project->getProjectMapping()->getData() as $mapIndex => $mapping)
        <li role="presentation" class="@if ($mapping['is_default']) active @endif" data-map-index="{{$mapIndex}}" data-map-name="{{$mapping['name']}}">
            <a href="#map-data-tabcontent-{{$mapIndex}}" role="tab" data-toggle="tab">
                <span class="map-data__map-name">{{$mapping['name']}}</span>
                &nbsp;
                <i class="fa fa-thumb-tack map-data__default-pin @if (!$mapping['is_default']) invisible @endif" aria-hidden="true"></i>
            </a>
        </li>
    @endforeach

    {{--if we have alreay 3 mappings, do not show the "Add mapping button "--}}
    <li role="presentation" class="map-data__tabs__add-form @if(count($project->getProjectMapping()->getData()) == 4) hidden @endif">
        <!-- trigger add form modal-->
        <a href="#" aria-controls="profile" role="tab"
           data-toggle="modal"
           data-action="add-mapping"
           data-trans="{{trans('site.add_mapping')}}"
           data-target="#modal__mapping-data">
            Add mapping
            <i class="fa fa-plus"></i>
        </a>
    </li>
</ul>