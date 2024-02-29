<div
        data-email-subject="Join {{$requestAttributes->requestedProject->name}} on Epicollect5"
        data-title="Join {{$requestAttributes->requestedProject->name}} on Epicollect5&#10;"
        data-description="{{$requestAttributes->requestedProject->small_description}}&#10;"
        data-url="Tap the link on your Android or iOS device to join &#10; {{ url('/open/project/'.$requestAttributes->requestedProject->slug) }}&#10;&#10;"
        class="sharethis-inline-share-buttons text-left"
>
</div>
