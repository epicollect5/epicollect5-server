<form id="ec5-form" class="create-project-form" method="POST" action="{{ url('/myprojects/create') }}"
      accept-charset="UTF-8"
      enctype="multipart/form-data" class="form-horizontal">
    {{ csrf_field() }}

    <div id="project-name-form-group-create"
         class="form-group has-feedback @if ($errors->has('name') && $tab === 'create') has-error @endif">
        <label class="control-label">{{trans('site.project_name')}}</label>
        <input required id="project-name-create"
               type="text"
               name="name"
               class="form-control"
               placeholder="{{trans('site.project_placeholder')}}"
               @if ($tab === 'create')
                   value="{{ old('name') }}"
               @else
                   value=""
               @endif
               maxlength="50">

        <span id="project-loader" class="form-control-feedback hidden">
                                <i class="fa fa-2x fa-circle-o-notch fa-spin"></i>
                        </span>

        @if($errors->has('name') && $tab === 'create')
            <i class="fa fa-2x fa-times form-control-feedback project-name-error"></i>
            @if (strpos($errors->first('name'), 'ec5_') === false)
                <small class="text-danger">{{ $errors->first('name') }}</small>
            @else
                <small class="text-danger">{{ trans('status_codes.' . $errors->first('name'))  }}</small>
            @endif
            <small class="text-hint hidden">{{trans('site.max_50_chars')}}</small>
        @else
            <i class="fa fa-2x fa-times form-control-feedback project-name-error hidden"></i>
            @if (strpos($errors->first('name'), 'ec5_') === false)
                <small class="text-danger hidden">
                    {{$errors->first('name')}}
                </small>
            @else
                <small class="text-danger hidden">
                    {{ trans('status_codes.' . $errors->first('name'))}}
                </small>
            @endif
            <small class="text-hint">{{trans('site.max_50_chars')}}</small>
        @endif
    </div>

    <div id="small-description-form-group"
         class="form-group has-feedback @if ($errors->has('small_description')) has-error @endif">
        <label class="control-label">{{trans('site.small_desc')}}</label>
        <small class="no-wrap"><em>(A long description can be added later)</em></small>
        <input required type="text" class="form-control" name="small_description"
               placeholder="{{trans('site.small_desc_placeholder')}}..."
               value="{{ old('small_description') }}"
               maxlength="100">
        @if ($errors->has('small_description'))
            <i class="fa fa-2x fa-times form-control-feedback small-description-error"></i>
            @if (strpos($errors->first('small_description'), 'ec5_') === false)
                <small class="text-danger">
                    {{ $errors->first('small_description')}}
                </small>
            @else
                <small class="text-danger">
                    {{ trans('status_codes.' . $errors->first('small_description')) }}
                </small>
            @endif
            <small class="text-hint hidden">{{trans('site.max_100_chars')}}</small>
        @else
            <i class="fa fa-2x fa-times form-control-feedback small-description-error hidden"></i>
            @if (strpos($errors->first('small_description'), 'ec5_') === false)
                <small class="text-danger hidden">
                    {{ $errors->first('small_description') }}
                </small>
            @else
                <small class="text-danger hidden">
                    {{ trans('status_codes.' . $errors->first('small_description')) }}
                </small>
            @endif
            <small class="text-hint">{{trans('site.max_100_chars')}}</small>
        @endif
    </div>

    <div id="form-name-form-group" class="form-group has-feedback @if ($errors->has('form_name')) has-error  @endif">
        <label class="control-label">{{trans('site.form_name')}}</label>
        <input id="form-name" required type="text" class="form-control" name="form_name"
               placeholder="{{trans('site.form_name_placeholder')}}"
               value="{{ old('form_name') }}" maxlength="50">

        @if ($errors->has('form_name'))
            <i class="fa fa-2x fa-times form-control-feedback"></i>
            @if (strpos($errors->first('form_name'), 'ec5_') === false)
                <small class="text-danger">
                    {{ $errors->first('form_name') ?? 'ec5_205'}}
                </small>
            @else
                <small class="text-danger">
                    {{ trans('status_codes.' . $errors->first('form_name') ?? 'ec5_205')}}
                </small>
            @endif
            <small class="text-hint hidden">{{trans('site.max_50_chars')}}</small>
        @else
            <i class="fa fa-2x fa-times form-control-feedback hidden"></i>
            @if (strpos($errors->first('form_name'), 'ec5_') === false)
                <small class="text-danger">
                    {{ $errors->first('form_name') ?? 'ec5_205'}}
                </small>
            @else
                <small class="text-danger hidden">{{ trans('status_codes.' . $errors->first('form_name'))}}</small>
            @endif
            <small class="text-hint">{{trans('site.max_50_chars')}}</small>
        @endif
    </div>

    <div class="form-group @if ($errors->has('access')) has-error @endif">
        <p class="no-margin access-title">{{trans('site.access')}}</p>
        @foreach (array_keys(config('epicollect.strings.projects_access')) as $p)
            <label class="radio-inline">
                <input type="radio" data-required="" name="access" value="{{ $p }}"
                       @if(old('access') && old('access') == $p) checked="checked"
                       @elseif('private' == $p) checked="checked" @endif>{{ ucfirst($p) }}
            </label>
        @endforeach
        @if ($errors->has('access'))
            <small class="text-danger"> {{ $errors->first('access') }}</small>
        @endif
    </div>
    <div class="form-group text-center">
        <button class="btn btn-default btn-action pull-right"
                type="submit">{{trans('site.create')}}</button>
    </div>
</form>
