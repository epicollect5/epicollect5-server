<meta
        property="og:url"
        content=" {{ url('/project/'.$requestAttributes->requestedProject->slug)}}"
/>
<meta property="og:title" content="{{$requestAttributes->requestedProject->name}}"/>
<meta property="og:description" content="{{$requestAttributes->requestedProject->small_description}}"/>
<meta property="og:type" content="article"/>
<meta property="og:image" content="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
              @else
              {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
              @endif"/>
<meta property="og:image:width" content="128"/>
<meta property="og:image:height" content="128"/>

{{--add twitter card metadata--}}
<meta name="twitter:card" content="summary"/>
<meta name="twitter:site" content="@EpiCollect"/>
<meta name="twitter:title" content="{{$requestAttributes->requestedProject->name}}"/>
<meta name="twitter:description" content="{{$requestAttributes->requestedProject->small_description}}"/>
<meta name="twitter:image" content="@if($requestAttributes->requestedProject->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
              @else
              {{ url('/api/internal/media/'.$requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb') }}
              @endif"/>


