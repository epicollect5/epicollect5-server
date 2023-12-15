@php
    $projectExtra = $requestAttributes->requestedProject->getProjectExtra();
    $projectStats = $requestAttributes->requestedProject->getProjectStats();
@endphp
<div role="tabpanel" class="tab-pane fade in active" id="limits">
    <div class="panel panel-default">
        <div class="panel-body">

            <span>{{ trans('site.entries_limits_description') }}</span>
            <br/>
            <em>{{ trans('site.entries_limits_warning_message', ['entries_limits_max' => $entries_limits_max]) }}.</em>

            <div class="pull-right">
                <button class="btn btn-action btn-sm limits-form__update-btn"
                        data-action="update"
                        data-toggle="push"
                >
                    {{trans('site.update')}}
                </button>
            </div>

            <form action="" method="POST" id="limits-form">
                {{ csrf_field() }}
                <div class="table-responsive manage-entries-limits__table-container">
                    <table class="table table-bordered table-condensed manage-entries-limits__table">
                        <thead>
                        <tr>
                            <th scope="col">{{trans('site.form_branch')}}</th>
                            <th scope="col" class="row__set-limit">{{trans('site.set_limit')}}</th>
                            <th scope="col" class="row__limit-to">{{trans('site.limit_to')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($projectExtra->getForms() as $formRef => $form)
                            @include('project.manage_entries.limits_row',
                                    [
                                    'projectExtra' => $projectExtra,
                                    'ref' => $formRef,
                                    'name' => $form['details']['name'],
                                    'currentlyCollected' => $projectStats->getFormCounts()[$formRef]['count'] ?? 0,
                                    'isBranch' => false,
                                    'formRef' => $formRef,
                                    'branchRef' => ''
                                    ]
                                    )
                            @if(count($form['branch']) > 0)
                                @foreach($form['branch'] as $branchRef => $branchInputs)
                                    @include('project.manage_entries.limits_row',
                                    [
                                    'projectExtra' => $projectExtra,
                                    'ref' => $branchRef,
                                    'name' => $projectExtra->getInputData($branchRef)['question'],
                                    'currentlyCollected' => $projectStats->getBranchCounts()[$branchRef]['count'] ?? 0,
                                    'isBranch' => true,
                                    'formRef' => $formRef,
                                    'branchRef' => $branchRef
                                    ]
                                    )
                                @endforeach
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>
