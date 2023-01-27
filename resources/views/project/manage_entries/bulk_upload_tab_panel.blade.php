<div role="tabpanel" class="tab-pane fade in" id="bulk-upload">
    <div class="panel panel-default">
        <div class="panel-body">
            <p>{{ trans('site.bulk_upload_description') }}</p>
            <p>
            <div class="btn-group bulk-upload-btns" role="group" data-project-slug="{{ $projectSlug }}">
                @foreach ($can_bulk_upload_enums as $value)
                    <div data-bulk-upload="{{ $value }}"
                        class="btn btn-default btn-sm
                         @if ($value === $can_bulk_upload) btn-action @else '' @endif">

                        @if ($value === 'nobody')
                            <i class="material-icons">person_off</i>
                        @endif
                        @if ($value === 'members')
                            <i class="material-icons">people</i>
                        @endif
                        @if ($value === 'everybody')
                            <i class="material-icons">public</i>
                        @endif
                        {{ trans('site.' . $value) }}
                    </div>
                @endforeach
            </div>
            </p>
            <p class="well">
                <strong>
                    <a href="https://docs.epicollect.net/web-application/bulk-uploads">More info on bulk uploads</a>
                </strong>
            </p>
        </div>
    </div>
</div>
