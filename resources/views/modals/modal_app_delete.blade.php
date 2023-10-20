<div id="modal-app-delete" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="Confirm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">{{trans('site.confirm')}}</h4>
            </div>
            <form class="hidden" id="ec5-form-project-app-delete" method="POST"
                  action="{{ url('myprojects/' . $project->slug . '/app-delete') }}" accept-charset="UTF-8">

                {!! csrf_field() !!}

                <div class="modal-body">
                    <p class="modal-app-delete-text">{{trans('site.delete_app_confirm')}}</p>
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
            <form class="hidden" id="ec5-form-project-app-revoke" method="POST"
                  action="{{ url('myprojects/' . $project->slug . '/app-revoke') }}" accept-charset="UTF-8">

                {!! csrf_field() !!}

                <div class="modal-body">
                    <p class="modal-app-delete-text">{{trans('site.revoke_token_confirm')}}</p>
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
