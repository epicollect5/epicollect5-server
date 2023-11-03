<form id="ec5-form" class="create-project-form" method="POST"
      action="{{ url('/myprojects/import') }}" accept-charset="UTF-8"
      enctype="multipart/form-data" class="form-horizontal">
    {{ csrf_field() }}

    <div id="project-name-form-group-import"
         class="form-group has-feedback @if (($errors->has('name') || $errors->has('slug')) && $tab === 'import') has-error @endif">
        <label class="control-label">{{trans('site.project_name')}}</label>
        <input required id="project-name-import" type="text" name="name"
               class="form-control"
               placeholder="{{trans('site.project_placeholder')}}"
               @if ($tab === 'import')
                   value="{{ old('name') }}"
               @else
                   value=""
               @endif
               minlength="3"
               maxlength="50">

        <span id="project-loader" class="form-control-feedback hidden">
                                <i class="fa fa-2x fa-circle-o-notch fa-spin"></i>
                            </span>

        @if ($errors->has('name') && $tab === 'import')
            @if (strpos($errors->first('name'), 'ec5_') === false)
                {{--error was already translated--}}
                <small class="text-danger">{{ $errors->first('name') }}</small>
            @else
                {{--translate error--}}
                <small class="text-danger">{{ trans('status_codes.' . $errors->first('name')) }}</small>
            @endif
        @else
            <small>{{trans('site.max_50_chars')}}</small>
        @endif
    </div>

    <div class="form-group @if ($errors->has('file')) has-error @endif">
        <label class="control-label">{{trans('site.project_json')}}</label>
        <input required type="file" class="form-control" name="file">
        @if ($errors->has('file'))
            <small class="text-danger">{{ trans('status_codes.' . $errors->first('file')) }}</small>
        @endif
    </div>

    <div class="form-group text-center">
        <button class="btn btn-default btn-action pull-right"
                type="submit">{{trans('site.import_project')}}</button>
    </div>
</form>
