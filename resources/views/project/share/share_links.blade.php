<div
        data-email-subject="Join {{$requestAttributes->requestedProject->name}} on Epicollect5"
        data-title="Join {{$requestAttributes->requestedProject->name}} on Epicollect5"
        data-description="{{$requestAttributes->requestedProject->small_description}}"
        data-url="Tap the link on your Android or iOS device to join {{ url('/open/project/'.$requestAttributes->requestedProject->slug) }}"
        class="sharethis-inline-share-buttons text-left"
>
</div>
