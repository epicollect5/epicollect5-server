<div
        data-email-subject="Join {{$requestAttributes->requestedProject->name}} on Epicollect5"
        data-title="Join {{$requestAttributes->requestedProject->name}} on Epicollect5"
        data-description="{{$requestAttributes->requestedProject->small_description}}"
        data-url="Join the project at {{ url('/project/'.$requestAttributes->requestedProject->slug) }}"
        class="sharethis-inline-share-buttons text-left"
>
</div>

