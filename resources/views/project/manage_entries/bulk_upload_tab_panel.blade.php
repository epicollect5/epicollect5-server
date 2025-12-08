<div role="tabpanel" class="tab-pane fade in" id="bulk-upload">
    <div class="panel panel-default">
        <div class="panel-body">
            <p>{{ trans('site.bulk_upload_description') }}</p>
            <p>
            <div class="btn-group bulk-upload-btns"
                 role="group"
                 data-project-slug="{{$projectSlug}}"
            >
                @foreach($can_bulk_upload_enums as $value)
                    <div data-bulk-upload="{{$value}}"
                         class="btn btn-default btn-sm
                         @if($value === $can_bulk_upload) btn-action @else '' @endif"
                    >
                        {{trans('site.'.$value)}}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
