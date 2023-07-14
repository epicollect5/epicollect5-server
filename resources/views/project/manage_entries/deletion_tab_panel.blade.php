<div role="tabpanel" class="tab-pane fade in" id="deletion">
    <div class="panel panel-default">
        <div class="panel-body">

            <span>{{ trans('site.entries_deletion_description') }}</span>

            <div class="pull-right">
                @if (
                    $requestedProjectRole->getUser()->isSuperAdmin() ||
                        $requestedProjectRole->getUser()->isAdmin() ||
                        $requestedProjectRole->canDeleteEntries())
                    <a data-setting-type="status" data-value="deletion" class="btn btn-danger btn-sm entries-deletion"
                        href="{{ url('myprojects') . '/' . $project->slug . '/delete-entries' }}">
                        {{ trans('site.delete') }}</a>
                @endif
            </div>

            <p>
            <div class="warning-well">
                Specific entries can be deleted by using the dataviewer
                <a href="#"><strong>Show me how!</strong></a>
            </div>
            </p>
        </div>
    </div>
</div>
