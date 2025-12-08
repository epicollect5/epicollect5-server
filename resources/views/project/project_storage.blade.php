<div class="panel panel-default panel-quota">
    <div class="panel-heading">
        <span>Quota</span>
        <div class="pull-right">
            <span class="bytes-updated-at">
                Updated <span>{{$mediaUsage['updated_at_human_readable']}}</span>
            </span>
            <button class="btn btn-action btn-sm btn-refresh-media-overview">
                <span class="material-symbols-outlined">
                    autoplay
                </span>
                Refresh
            </button>
        </div>

    </div>
    <div class="panel-body">
        <div class="counter-quota" data-project-slug="{{ $requestAttributes->requestedProject->slug }}">
            <div class="loader loader-quota-stats"></div>
            <div class="quota-stats hidden">
                <p>Quota information will be displayed here.</p>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default panel-storage">
    <div class="panel-heading">
        <span>{{ trans('site.storage') }}</span>
        <a href="#" target="_blank">
            <i class="material-symbols-outlined">help</i>
        </a>
    </div>
    <div class="panel-body">
        <div class="counter-media" data-project-slug="{{ $requestAttributes->requestedProject->slug }}">
            <div class="loader loader-media-stats"></div>
            <div class="media-stats hidden">
                <!-- Progress bar -->
                <div class="progress">
                    <div class="progress-bar progress-bar__photo "
                         role="progressbar" style="width:0" data-type="photo">
                    </div>
                    <div class="progress-bar progress-bar__audio "
                         role="progressbar" style="width:0" data-type="audio">
                    </div>
                    <div class="progress-bar progress-bar__video"
                         role="progressbar" style="width:0" data-type="video">
                    </div>
                </div>

                <!-- Sizes table -->
                <table class="table table-condensed media-sizes">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Total Files</th>
                        <th>Total Size</th>
                        <th>Ratio</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <span class="color-indicator color-photo"></span>
                            Photo
                        </td>
                        <td class="count-photo" data-photo-files="{{$mediaUsage['counters']['photo']}}">0</td>
                        <td class="size-photo" data-photo-bytes="{{$mediaUsage['sizes']['photo_bytes']}}">0 B</td>
                        <td class="ratio-photo">0</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="color-indicator color-audio"></span>
                            Audio
                        </td>
                        <td class="count-audio" data-audio-files="{{$mediaUsage['counters']['audio']}}">0</td>
                        <td class="size-audio" data-audio-bytes="{{$mediaUsage['sizes']['audio_bytes']}}">0 B</td>
                        <td class="ratio-audio">0</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="color-indicator color-video"></span>
                            Video
                        </td>
                        <td class="count-video" data-video-files="{{$mediaUsage['counters']['video']}}">0</td>
                        <td class="size-video" data-video-bytes="{{$mediaUsage['sizes']['video_bytes']}}">0 B</td>
                        <td class="ratio-video">0</td>
                    </tr>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td class="count-total" data-total-files="{{$mediaUsage['counters']['total']}}">0</td>
                        <td class="size-total" data-total-bytes="{{$mediaUsage['sizes']['total_bytes']}}">0 B</td>
                        <td class="ratio-total">0%</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>


@section('scripts')
    <script type="text/javascript"
            src="{{ asset('js/project/project.js') . '?' . config('app.release') }}"></script>
@stop
