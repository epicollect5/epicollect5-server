<!-- Modal -->
<div id="modal-app-link" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                <h4 class="modal-title text-center">
                    <strong>Project: {{$requestAttributes->requestedProject->name}}</strong>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row text-center">
                    <div class="col-md-6 col-md-offset-3">
                        <a class="btn btn-action"
                           href="{{ url('/open/project/'.$requestAttributes->requestedProject->slug) }}">
                            <span class="material-icons">send_to_mobile</span>
                            Tap to Add
                        </a>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 col-md-offset-3">
                        <p class="text-center">
                            Or scan the QR code below
                        </p>
                        <div id="qrcode"
                             data-url=" {{ url('/open/project/'.$requestAttributes->requestedProject->slug) }}">
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">

            </div>

        </div>

    </div>
</div>
<script>
    //show modal if project not ready
    $('#modal-project-not-ready').modal();
</script>
