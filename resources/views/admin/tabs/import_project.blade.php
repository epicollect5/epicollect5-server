<div class="row flexbox">
    @if (count($errors->getMessages()) > 0)

        @if($errors->has('missing_keys') || $errors->has('extra_keys'))
            <div class="var-holder-error" data-message="{{trans('site.invalid_form')}}"></div>
        @endif
        @if($errors->has('slug'))
            <div class="var-holder-error" data-message="{{trans('site.project_exist')}}"></div>
        @endif
        @if($errors->has('db'))
            @foreach($errors->getMessages() as $key => $error)
                <div class="var-holder-error" data-message="{{$key , $error}}"></div>
            @endforeach
        @endif
        <script>
            var errors = '';
            $('.var-holder-error').each(function () {
                errors += $(this).attr('data-message') + '</br>'
            });
            EC5.toast.showError(errors);
        </script>
    @endif

    <div class="col-sm-6 col-md-6 col-md-offset-3 col-sm-offset-3">
        <!-- sample form1 -->
        <form id="ec5-form" class="create-project-form" method="POST"
              action="{{ url('/admin/import') }}" accept-charset="UTF-8"
              enctype="multipart/form-data" class="form-horizontal">
            {{ csrf_field() }}

            <div id="project-name-form-group"
                 class="form-group has-feedback @if ($errors->has('name') || $errors->has('slug')) has-error @endif">
                <label class="control-label">{{trans('site.project_name')}}</label>
                <input required id="project-name" type="text" name="name" class="form-control"
                       placeholder="{{trans('site.project_placeholder')}}" value="{{ old('name') }}"
                       maxlength="50">

                <span id="project-loader" class="form-control-feedback hidden">
                                <i class="fa fa-2x fa-circle-o-notch fa-spin"></i>
                            </span>

                @if ($errors->has('name'))
                    <small class="text-danger">{{ trans('status_codes.' . $errors->first('name')) }}</small>
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
    </div>
</div>
