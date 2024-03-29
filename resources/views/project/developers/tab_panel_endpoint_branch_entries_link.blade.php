@if($requestAttributes->requestedProject->access === 'public')
    @if(isset($mapIndex))
        <a href="{{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug.'?map_index='.$mapIndex}}&form_ref={{$formRef}}&branch_ref={{$branchRef}}"
           target="_blank">
    <span class="break-word">
        {{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}?map_index={{$mapIndex}}
        &form_ref={{$formRef}}&branch_ref={{$branchRef}}
    </span>
        </a>
    @else
        <a href="{{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}?form_ref={{$formRef}}&branch_ref={{$branchRef}}"
           target="_blank">
    <span class="break-word">
        {{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}?form_ref={{$formRef}}
        &branch_ref={{$branchRef}}
    </span>
        </a>

    @endif
@else

    <span class="break-word">
    {{url('/')}}/api/export/entries/{{$requestAttributes->requestedProject->slug}}
    ?map_index={{$mapIndex}}&form_ref={{$formRef}}&branch_ref={{$branchRef}}
</span>
@endif