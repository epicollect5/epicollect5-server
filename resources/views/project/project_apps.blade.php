<div class="panel panel-default">
    <div class="panel-heading">
        <span>{{ trans('site.apps') }}</span>
        <button @if (count($apps) != 0) disabled @endif class="btn btn-sm btn-action pull-right" data-toggle="modal"
                id="create-app" data-target="#modal__create-app"
                href="#">{{ trans('site.create_new_app') }}</button>
    </div>
    <div class="panel-body project-apps">

        {{-- Error handling --}}
        @if(!$errors->isEmpty())
            @foreach($errors->all() as $error)
                <div class="var-holder-error" data-message="{{trans('status_codes.'.$error)}}"></div>
            @endforeach
            <script>
                //get all errors
                var errors = '';
                $('.var-holder-error').each(function () {
                    errors += $(this).attr('data-message') + '</br>';
                });
                EC5.toast.showError(errors);
            </script>
        @endif

        {{-- Success Message --}}
        @if (session('message'))
            <div class="var-holder-success" data-message="{{trans('status_codes.'.session('message'))}}"></div>
            <script>
                EC5.toast.showSuccess($('.var-holder-success').attr('data-message'));
            </script>
        @endif

        <div class="project-apps-list">
            @include('project.developers.apps_table')
        </div>

    </div>
</div>

<!-- Add App Modal -->
<div id="modal__create-app" class="modal fade" tabindex="-1" role="dialog">
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
                            <small class="text-danger">{{ trans('status_codes.' . $errors->first('application_name'))  }}</small>
                            <small class="text-hint hidden">{{trans('site.max_50_chars')}}</small>
                        @else
                            <i class="fa fa-2x fa-times form-control-feedback project-application_name-error hidden"></i>
                            <small class="text-danger hidden">{{ trans('status_codes.' . $errors->first('application_name')) }}</small>
                            <small class="text-hint">{{trans('site.max_50_chars')}}</small>
                        @endif
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">
                        {{trans('site.cancel')}}
                    </button>
                    <button class="btn btn-default btn-action"
                            type="submit">{{trans('site.create')}}</button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div id="modal__confirm" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="Confirm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">{{trans('site.confirm')}}</h4>
            </div>
            <form class="hidden" id="ec5-form-project-app-delete" method="POST" action="{{ url('myprojects/' . $project->slug . '/app-delete') }}" accept-charset="UTF-8">

                {!! csrf_field() !!}

                <div class="modal-body">
                    <p class="modal__confirm-text">{{trans('site.delete_app_confirm')}}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default"
                            data-dismiss="modal">
                        {{trans('site.cancel')}}
                    </button>
                    <button type="submit" class="btn btn-action">
                        {{trans('site.confirm')}}
                    </button>
                </div>
            </form>
            <form class="hidden" id="ec5-form-project-app-revoke" method="POST" action="{{ url('myprojects/' . $project->slug . '/app-revoke') }}" accept-charset="UTF-8">

                {!! csrf_field() !!}

                <div class="modal-body">
                    <p class="modal__confirm-text">{{trans('site.revoke_token_confirm')}}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default"
                            data-dismiss="modal">
                        {{trans('site.cancel')}}
                    </button>
                    <button type="submit" class="btn btn-action">
                        {{trans('site.confirm')}}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.ENV('RELEASE') }}"></script>
@stop
