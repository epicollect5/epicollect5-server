<div role="tabpanel" class="tab-pane fade in" id="deletion">
    <div class="panel panel-default">
        @if($requestAttributes->requestedProject->status !== 'locked')
            <div class="panel-body">
                <div class="warning-well">
                    The project must be set as locked before its entries can be deleted in bulk.
                    <a href="https://docs.epicollect.net/web-application/set-project-details#project-status"
                       target="_blank">
                        &nbsp;More info.
                    </a>
                </div>
            </div>
        @else
            <div class="panel-body">
                <span>{{ trans('site.entries_deletion_description') }}</span>

                <div class="pull-right">
                    @if (
                        $requestAttributes->requestedProjectRole->getUser()->isSuperAdmin() ||
                        $requestAttributes->requestedProjectRole->getUser()->isAdmin() ||
                        $requestAttributes->requestedProjectRole->canDeleteEntries())
                        <a data-setting-type="status"
                           data-value="deletion"
                           class="btn btn-danger btn-sm entries-deletion"
                           href="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug . '/delete-entries' }}"
                        >
                            {{ trans('site.delete') }}
                        </a>
                    @endif
                </div>

                <p>
                <div class="warning-well">
                    Specific entries can be deleted by using the dataviewer
                    <a href="https://docs.epicollect.net/web-application/adding-data" target="_blank">
                        &nbsp;More info.
                    </a>
                </div>
                </p>
            </div>
        @endif
    </div>
</div>
