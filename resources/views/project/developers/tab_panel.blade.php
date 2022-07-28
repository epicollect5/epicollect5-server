<div class="tab-content">
    <div role="tabpanel" class="tab-pane active fade in" id="home">
        @include('project.developers.tab_panel_parameters', [
        'project' => $project,
        'forms' => $project->getProjectExtra()->getData()['forms'],
        'projectExtra' => $project->getProjectExtra()->getData(),
        'projectMapping' => $project->getProjectMapping(),
         'mappings' => $project->getProjectMapping()->getData()
        ])
    </div>
    <div role="tabpanel" class="tab-pane fade" id="profile">
        @include('project.developers.tab_panel_endpoints', [
           'forms' => $project->getProjectExtra()->getData()['forms'],
           'projectExtra' => $project->getProjectExtra()->getData(),
           'projectMapping' => $project->getProjectMapping(),
           'mappings' => $project->getProjectMapping()->getData()
        ])
    </div>
</div>