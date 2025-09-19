<div class="panel panel-default panel-quota">
    <div class="panel-heading">
        <span>Quota</span>
    </div>
    <div class="panel-body">
        <p>Quota information will be displayed here.</p>
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
            <div class="loader"></div>
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
                        <th>Count</th>
                        <th>Size</th>
                        <th>Ratio</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <span class="color-indicator color-photo"></span>
                            Photo
                        </td>
                        <td class="count-photo">0</td>
                        <td class="size-photo">0 B</td>
                        <td class="ratio-photo">0</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="color-indicator color-audio"></span>
                            Audio
                        </td>
                        <td class="count-audio">0</td>
                        <td class="size-audio">0 B</td>
                        <td class="ratio-audio">0</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="color-indicator color-video"></span>
                            Video
                        </td>
                        <td class="count-video">0</td>
                        <td class="size-video">0 B</td>
                        <td class="ratio-video">0</td>
                    </tr>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td class="count-total">0</td>
                        <td class="size-total">0 B</td>
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
