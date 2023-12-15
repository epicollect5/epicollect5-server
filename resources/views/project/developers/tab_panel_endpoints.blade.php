<div>
    @if($requestAttributes->requestedProject->access === 'private')
        <div class="warning-well">Endpoints are not accessible by a browser for <strong>private</strong> projects</div>
    @endif
    {{-- if the project is pubic endpoints are available via the browser--}}
    <table class="table table-bordered project-api__endpoints">
        <colgroup>
            <col width="200">
        </colgroup>
        <thead>
        <tr>
            <th>Resource</th>
            <th>Endpoint</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <span>Project</span>
            </td>
            <td>
                @if($requestAttributes->requestedProject->access === 'public')
                    <a href="https://five.epicollect.net/api/export/project/{{$requestAttributes->requestedProject->slug}}"
                       target="_blank"
                    >
                        {{url('/')}}/api/export/project/{{$requestAttributes->requestedProject->slug}}
                    </a>

                @else
                    <span>{{url('/')}}/api/export/project/{{$requestAttributes->requestedProject->slug}}</span>
                @endif
            </td>
        </tr>

        {{--show endpoint per each form--}}
        @foreach($forms as $form)

            <tr>
                <td>
                    <span>Entries - {{$form['details']['name']}}</span>
                </td>
                <td>
                    @include('project.developers.tab_panel_endpoint_entries_link', [
                            'project' => $requestAttributes->requestedProject,
                            'mapIndex' => null,
                            'mapName' => $projectMapping->getDefaultMapName().' (default)',
                            'formRef' => $form['details']['ref']
                            ])
                    <hr>

                    {{-- Show branches endpoints--}}
                    @if (count($form['branch']) > 0 )
                        @foreach ($form['branch'] as $branchRef => $branchInputRef)
                            <p>Branch - {{$projectExtra['inputs'][$branchRef]['data']['question']}}</p>
                            @include('project.developers.tab_panel_endpoint_branch_entries_link', [
                                                        'project' => $requestAttributes->requestedProject,
                                                        'mapIndex' => null,
                                                        'mapName' => $projectMapping->getDefaultMapName().' (default)',
                                                        'formRef' => $form['details']['ref'],
                                                        'branchRef' => $branchRef
                                                        ])
                            <hr>
                        @endforeach

                    @endif



                    @foreach($mappings as $mapIndex => $mapping)
                        @unless($mapping['is_default'])

                            @include('project.developers.tab_panel_endpoint_entries_link', [
                            'project' => $project,
                            'mapIndex' => $mapIndex,
                            'mapName' => $mapping['name'],
                            'formRef' => $form['details']['ref']
                            ])
                        @endunless
                    @endforeach
                </td>
        @endforeach
        </tbody>
    </table>
</div>
