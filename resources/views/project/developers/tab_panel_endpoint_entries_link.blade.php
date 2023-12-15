@if($requestAttributes->requestedProject->access === 'public')
    @if(isset($mapIndex))
        <a href="{{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug.'?map_index='.$mapIndex}}&form_ref={{$formRef}}"
           target="_blank"
        >

            {{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}?map_index={{$mapIndex}}
            &form_ref={{$formRef}}
        </a>
    @else
        <a href="{{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}?form_ref={{$formRef}}"
           target="_blank"
        >

            {{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}?form_ref={{$formRef}}
        </a>
    @endif
@else

    <span>
        {{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}
        ?map_index={{$mapIndex}}&form_ref={{$formRef}}
    </span>
@endif
<p><strong>Mapping: </strong> <em>{{$mapName}}</em></p>