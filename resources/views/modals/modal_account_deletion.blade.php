<div id="modal__account-deletion" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">Request account deletion</h4>
            </div>
            <div class="modal-body">
                <p class="warning-well text-center">
                    Please confirm you would like the account with email <br/>
                    <strong>{{ $email }}</strong>
                    <br/>
                    to be removed from our systems.
                </p>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" class="checkbox-confirm-account-deletion"> <strong>I understand this
                            action cannot be undone.</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    Dismiss
                </button>
                <button type="button" class="btn btn-action btn-confirm-account-deletion" disabled>
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>
