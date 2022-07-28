<div class="map-data-actions" data-mapping="auto">
    <div class="map-data--actions__btns pull-right">
        <button class="btn btn-action btn-sm"
                data-action="delete"
                data-toggle="modal"
                data-target="#modal__mapping-data"
                data-trans="{{trans('site.delete_mapping')}}"
                @if ($mapIndex == 0) disabled
                @endif>
            {{trans('site.delete')}}
        </button>
        <button class="btn btn-action btn-sm"
                data-action="rename"
                data-toggle="modal"
                data-target="#modal__mapping-data"
                data-trans="{{trans('site.rename_mapping')}}"
                @if ($mapIndex == 0) disabled
                @endif>
            {{trans('site.rename')}}
        </button>
        <button class="btn btn-action btn-sm"
                data-action="make-default"
                data-toggle="push"
        >
            {{trans('site.make_default')}}
        </button>
        <button class="btn btn-action btn-sm"
                data-action="update"
                data-toggle="push"
                @if ($mapIndex == 0) disabled
                @endif
        >
            {{trans('site.update')}}
        </button>
    </div>
</div>

{{--List all forms in a dropdown, top form selected by default--}}
@if(isset($forms))
    <div class="btn-group form-list-selection">
        <button type="button"
                class="btn btn-default btn-sm dropdown-toggle"
                data-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false">
                                    <span class="form-list-selection__form-name">
                                        {{--Set the top level from as the selected one--}}
                                        {{reset($forms)['details']['name']}}
                                    </span>
            <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            @foreach($forms as $formRef => $form)
                <li data-form-ref="{{$formRef}}"><a href="#">{{$form['details']['name']}}</a></li>
            @endforeach
        </ul>
    </div>
@endif