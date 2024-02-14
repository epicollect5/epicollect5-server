<div id="modal-create-app" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">{{ trans('site.create_new_app') }}</h4>
            </div>

            <form id="ec5-form-project-apps" class="create-project-app" method="POST" action="" accept-charset="UTF-8"
                  class="form-horizontal">

                {!! csrf_field() !!}

                <div class="modal-body">

                    <div id="project-application_name-form-group"
                         class="form-group has-feedback @if ($errors->has('application_name')) has-error @endif">
                        <label class="control-label">{{trans('site.app_name')}}</label>
                        <input required id="project-application_name" type="text" name="application_name"
                               class="form-control"
                               placeholder="{{trans('site.project_app_placeholder')}}"
                               value="{{ old('application_name') }}"
                               maxlength="50">

                        <span id="project-loader" class="form-control-feedback hidden">
                                <i class="fa fa-2x fa-circle-o-notch fa-spin"></i>
                        </span>

                        @if($errors->has('application_name'))
                            <i class="fa fa-2x fa-times form-control-feedback project-application_name-error"></i>
                            <small class="text-danger">{{ config('epicollect.codes.' . $errors->first('application_name'))  }}</small>
                            <small class="text-hint hidden">{{trans('site.max_50_chars')}}</small>
                        @else
                            <i class="fa fa-2x fa-times form-control-feedback project-application_name-error hidden"></i>
                            <small class="text-danger hidden">{{ config('epicollect.codes.' . $errors->first('application_name')) }}</small>
                            <small class="text-hint">{{trans('site.max_50_chars')}}</small>
                        @endif
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        {{trans('site.cancel')}}
                    </button>
                    <button class="btn btn-default btn-action"
                            type="submit">{{trans('site.create')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
