<div class="tab-content">
    <div role="tabpanel" class="tab-pane active fade in" id="home">
        @include('project.developers.tab_panel_parameters', [
        'project' => $requestAttributes->requestedProject,
        'forms' => $requestAttributes->requestedProject->getProjectExtra()->getData()['forms'],
        'projectExtra' => $requestAttributes->requestedProject->getProjectExtra()->getData(),
        'projectMapping' => $requestAttributes->requestedProject->getProjectMapping(),
         'mappings' => $requestAttributes->requestedProject->getProjectMapping()->getData()
        ])
    </div>
    <div role="tabpanel" class="tab-pane fade" id="profile">
        @include('project.developers.tab_panel_endpoints', [
           'forms' => $requestAttributes->requestedProject->getProjectExtra()->getData()['forms'],
           'projectExtra' => $requestAttributes->requestedProject->getProjectExtra()->getData(),
           'projectMapping' => $requestAttributes->requestedProject->getProjectMapping(),
           'mappings' => $requestAttributes->requestedProject->getProjectMapping()->getData()
        ])
    </div>
</div>