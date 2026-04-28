@php
    use Carbon\Carbon;
    $updatedAt = $requestAttributes->requestedProject->getUpdatedAt();
    $createdAtUTC = Carbon::parse($requestAttributes->requestedProject->created_at)->setTimezone('UTC');
    $createdOnForHumans = $createdAtUTC->format('D d M Y, H:i');
    $logoUrl = url('/api/internal/media/' . $requestAttributes->requestedProject->slug . '?type=photo&name=logo.jpg&format=project_thumb&v=' . $requestAttributes->requestedProject->project_definition_version);
@endphp
{{-- Success Message --}}
@if (session('projectCreated') && session('tab') === 'create')
    @include('modals/modal_project_not_ready')
@endif


@if (sizeof($requestAttributes->requestedProject->getProjectDefinition()->getData()['project']['forms'][0]['inputs']) === 0)
    <div class="warning-well warning__project-not-ready">
        <strong>This project is not ready for data collection! You will have to
            <a href="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug . '/formbuilder' }}">
                add some questions to your form(s)
            </a>
        </strong>
    </div>
@endif

<div class="row flexbox">
    <div class="col-sm-12 col-md-12 col-lg-7 equal-height details-view__wrapper">
        <div id="details-view"
             class="panel panel-default project-details-panel @if ($showPanel != 'details-view') ec5-hide-block @endif ">
            <div class="panel-heading">
                <div class="panel-title">{{ trans('site.project_details') }}</div>
                @if ($requestAttributes->requestedProjectRole->canEditProject())
                    <button class="btn btn-default btn-action btn-sm pull-right project-details__edit">
                        <i class="material-icons">&#xE254;</i>
                    </button>
                @endif
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-3 col-md-3">
                        <div class="project-logo-wrapper">
                            <img class="project-logo img-responsive img-circle" width="128" height="128"
                                 alt="Project logo"
                                 src="{{ $logoUrl }}"
                            >

                            <div class="loader"></div>
                        </div>
                    </div>

                    <div class="col-sm-9 col-md-9">
                        <div class="details-view__project-details">
                            <h5 class="details-view__project-details__small-description">
                                <i class="material-icons">
                                    assignment
                                </i>
                                &nbsp;{{ $requestAttributes->requestedProject->small_description }}
                            </h5>
                            <h5 class="details-view__project-details__created-at">
                                <i class="material-icons">
                                    calendar_today
                                </i>
                                &nbsp;Created on
                                {{ $createdOnForHumans }}
                                UTC
                            </h5>
                        </div>
                    </div>

                </div>

                <div class="clearfix"></div>

                <div class="row">
                    <div class="col-md-12">
                        @if ($requestAttributes->requestedProject->description === '')
                            <p class="well margin-top-lg text-center">
                                {{ trans('site.no_desc_yet') }}
                            </p>
                        @else
                            <p class="well details-view__project-details__description margin-top-lg">
                                {!! nl2br(e($requestAttributes->requestedProject->description)) !!}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div id="details-edit"
             class="panel panel-default project-details-panel @if ($showPanel != 'details-edit') ec5-hide-block @endif ">
            <div class="panel-heading">
                <h4>{{ trans('site.project_details') }}</h4>
                <button class="btn btn-default btn-action pull-right btn-sm project-details__edit">
                    <i class="material-icons">&#xE14C;</i>
                </button>
            </div>
            <div class="panel-body">

                <form method="POST"
                      action="{{ url('/myprojects/' . $requestAttributes->requestedProject->slug . '/details') }}"
                      enctype="multipart/form-data" accept-charset="UTF-8">
                    {{ csrf_field() }}
                    <div class="form-group @if ($errors->has('small_description')) has-error @endif">
                        <label class="control-label">{{ trans('site.small_desc') }}</label>
                        <input required type="text" class="form-control" name="small_description"
                               placeholder="{{ trans('site.small_desc_placeholder') }}" minlength="15" maxlength="100"
                               value="@if (old('small_description')) {{ old('small_description') }}@else{{ $requestAttributes->requestedProject->small_description }} @endif">

                        <small>
                            <em>
                                {{
                                config('epicollect.limits.project.small_desc.min') .
                                ' to '.
                                config('epicollect.limits.project.small_desc.max') .
                                ' chars'
                                }}
                            </em>
                        </small>
                    </div>

                    <div class="form-group file-upload-input">
                        <div class="input-group">
                            <label class="input-group-btn">
                                <span class="btn btn-action">
                                    {{ trans('site.upload_logo') }}&hellip; <input type="file" name="logo_url"
                                                                                   style="display: none;">
                                </span>
                            </label>
                            <input type="text" class="form-control" readonly>
                        </div>
                        <em>{{ trans('site.max_logo_image_file_size') }}</em>
                    </div>

                    <div class="form-group">
                        <label for="description" class="control-label">{{ trans('site.desc') }}</label>
                        @if (old('description'))
                            <textarea
                                    class="form-control project-long-description"
                                    name="description"
                                    rows="5"
                                    minlength="3"
                                    maxlength="3000"
                            >{{ trim(old('description')) }}</textarea>
                        @else
                            <textarea
                                    class="form-control project-long-description"
                                    name="description"
                                    rows="5"
                                    minlength="3"
                                    maxlength="3000"
                            >{{ trim($requestAttributes->requestedProject->description) }}</textarea>
                        @endif
                        <small>
                            <em>
                                {{
                                config('epicollect.limits.project.description.min') .
                                 ' to ' .
                                 config('epicollect.limits.project.description.max') .
                                 ' chars'
                                }}
                            </em>
                        </small>
                    </div>

                    <div class="form-group">
                        <button class="btn btn-default btn-action pull-right"
                                type="submit">{{ trans('site.update') }}</button>
                    </div>
                </form>
            </div><!-- end panel body -->

        </div><!-- details edit -->
    </div><!-- end col -->


    @if ($requestAttributes->requestedProjectRole->canEditProject())
        <div class="col-sm-12 col-md-12 col-lg-5 equal-height">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <div class="panel-title">Project {{ trans('site.settings') }}
                        <a href="https://docs.epicollect.net/web-application/set-project-details#project-settings"
                           target="_blank">
                            <i class="material-symbols-outlined">help</i></a>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive table-project-settings">
                        <table class="table">

                            <tbody>
                            <tr>
                                <th>{{ trans('site.access') }}</th>
                                <td>

                                    <div class="btn-group">
                                        @foreach (array_keys(config('epicollect.strings.projects_access')) as $p)
                                            <div data-setting-type="access" data-value="{{ $p }}"
                                                 class="btn btn-default btn-sm settings-access
                                             btn-settings-submit">
                                                {{ $p }}</div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ trans('site.status') }}</th>
                                <td>

                                    <div class="btn-group">

                                        <div data-setting-type="status" data-value="active"
                                             class="btn btn-default btn-sm settings-status
                                             btn-settings-submit">
                                            {{ trans('site.active') }}</div>

                                        <div data-setting-type="status" data-value="trashed"
                                             class="btn btn-default btn-sm settings-status
                                             btn-settings-submit">
                                            {{ trans('site.trash') }}</div>

                                        <div data-setting-type="status" data-value="restore"
                                             class="btn btn-default btn-sm settings-status
                                             btn-settings-submit">
                                            {{ trans('site.restore') }}</div>

                                        @if (
                                            $requestAttributes->requestedProjectRole->getUser()->isSuperAdmin() ||
                                                $requestAttributes->requestedProjectRole->getUser()->isAdmin() ||
                                                $requestAttributes->requestedProjectRole->canDeleteProject())
                                            <a data-setting-type="status" data-value="delete"
                                               class="btn btn-danger btn-sm settings-status"
                                               href="{{ url('myprojects') . '/' . $requestAttributes->requestedProject->slug . '/delete' }}">
                                                {{ trans('site.delete') }}</a>
                                        @endif

                                        <div data-setting-type="status" data-value="locked"
                                             class="btn btn-default btn-sm settings-status
                                             btn-settings-submit">
                                            {{ trans('site.lock') }}</div>

                                        <div data-setting-type="status" data-value="unlock"
                                             class="btn btn-default btn-sm settings-status
                                             btn-settings-submit">
                                            {{ trans('site.unlock') }}</div>


                                    </div>


                                </td>
                            </tr>
                            <tr>
                                <th>{{ trans('site.visibility') }}</th>
                                <td>

                                    <div class="btn-group">
                                        @foreach (array_keys(config('epicollect.strings.projects_visibility')) as $p)
                                            <div data-setting-type="visibility" data-value="{{ $p }}"
                                                 class="btn btn-default btn-sm settings-visibility
                                             btn-settings-submit">
                                                {{ $p }}</div>
                                        @endforeach
                                    </div>

                                </td>
                            </tr>
                            <tr>
                                <th>{{ trans('site.project_category') }}</th>
                                <td>
                                    <div class="btn-group">
                                        <select id="project-category" class="form-control"
                                                aria-labelledby="listProjectCategories">
                                            @foreach (array_keys(config('epicollect.strings.project_categories')) as $cat)
                                                <option value="{{ $cat }}"
                                                        @if ($requestAttributes->requestedProject->category == $cat) selected @endif>
                                                    {{ trans('site.project_categories.' . $cat) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div><!-- end col -->
    @endif


</div><!-- end row -->

<div class="row flexbox">
    <div class="col-sm-12 col-md-12 col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Project Definition</div>
            <div class="panel-body">
                <p class="project-tools-panel__description">
                    Share a project, reuse it as a template, and more.</p>
                <div class="project-tools-panel__actions">
                    <a class="btn btn-action btn-sm btn-180"
                       href="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug.'/download-project-definition')}}">
                        <i class="material-icons">data_object</i>
                        Download JSON
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row flexbox">
    <div class="col-sm-12 col-md-12 col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">Mobile Local Exports</div>
            <div class="panel-body">
                <p class="project-tools-panel__description">
                    Export your project data and media directly from
                    the mobile app.</p>
                <div class="project-tools-panel__actions">
                    <a class="btn btn-action btn-sm btn-180"
                       target="_blank"
                       href="https://docs.epicollect.net/mobile-application/export-entries-mobile">
                        <i class="material-icons">launch</i>
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>



@if(config('epicollect.setup.system.app_link_enabled'))
    @include('project.deeplinks.app_link')
@endif

@if (auth()->check() && auth()->user()->server_role == 'superadmin')
    <div class="row margin-top-lg">
        <div class="col-sm-12 col-md-12 col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <span>{{ trans('site.admin') }}</span>
                </div>
                <div class="panel-body">
                    <div>
                        <p>Project Id : {{ $requestAttributes->requestedProject->getId() }}</p>
                        <p>Project Ref : {{ $requestAttributes->requestedProject->ref }}</p>
                        <p>Created By : {{ $creatorEmail }}</p>
                        <p>Updated at : {{ $updatedAt }}</p>
                    </div>
                    <p>
                        <strong>
                            <a href="{{config('app.url')}}/api/internal/project/{{$requestAttributes->requestedProject->slug}}"
                               target="_blank"
                            >
                                View JSON
                            </a>
                        </strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- js variables --}}
<div class="js-project-details hidden"
     data-js-status="{{ $requestAttributes->requestedProject->status }}"
     data-js-access="{{ $requestAttributes->requestedProject->access }}"
     data-js-visibility="{{ $requestAttributes->requestedProject->visibility }}"
     data-js-logo_url="{{ $logoUrl }}"
     data-js-category="{{ $requestAttributes->requestedProject->category }}"
     data-js-slug="{{ $requestAttributes->requestedProject->slug }}"
     data-js-app_link_visibility="{{ $requestAttributes->requestedProject->app_link_visibility }}">
</div>

@section('scripts')
    <script type="text/javascript"
            src="{{ asset('js/project/project.js') . '?' . config('app.release') }}"></script>
@stop
