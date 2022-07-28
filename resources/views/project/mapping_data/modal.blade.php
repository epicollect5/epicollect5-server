<div id="modal__mapping-data" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="Add mapping">
    <!-- load modal dynamically-->
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"> - title -</h4>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <input type="text" class="map-data__modal__mapping-name form-control" placeholder="Type here..." maxlength="10" required aria-required="true">
                        <small class="map-data__modal__name-rules hidden">From 3 to 10 chars, only letters, numbers, dash '-' or underscore '_'</small>
                        <span class="map-data__modal-rename__error hidden">(error)</span>

                    </div>
                </form>
                <p class="map-data__modal-delete-warning hidden">Are you sure? It cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default map-data__modal__cancel-btn" data-dismiss="modal">{{trans('site.cancel')}}</button>
                <button type="button" class="btn btn-action map-data__modal__save-btn">
                    {{trans('site.confirm')}}
                </button>
            </div>
        </div>
    </div>
</div>
