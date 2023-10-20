<!-- Modal -->
<div id="modal-project-not-ready" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel"><strong>{{ $project->name }}</strong> project created!
                </h4>
            </div>
            <div class="modal-body">
                <p class="well text-center">Congratulations, you have now created your project website!</p>
                <p class="warning-well text-center"><strong>Before submitting data you must add some questions to
                        your form(s).</strong>
                    <br/><br/>
                    Click the <em>"Ok, open formbuilder now"</em> button below to open the formbuilder.
                </p>
                <p class="well text-center">
                    For further instructions,
                    <strong>
                        <a href="https://docs.epicollect.net/formbuilder/build-your-questionnaire">
                            please read our friendly User Guide</a>.
                    </strong>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">No thanks, I'll do it later
                </button>
                <a class="btn btn-default btn-action"
                   href="{{ url('myprojects') . '/' . $project->slug . '/formbuilder' }}" role="button">Ok, open
                    formbuilder now</a>
            </div>
        </div>
    </div>
</div>
<script>
    //show modal if project not ready
    $('#modal-project-not-ready').modal();
</script>