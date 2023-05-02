{{-- Success Message --}}
@if (session('projectCreated') && session('tab') === 'create')
    <!-- Modal -->
    <div id="modal__project-not-ready" class="modal fade" tabindex="-1" role="dialog">
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
                        <br /><br />
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
        $('#modal__project-not-ready').modal();
    </script>
@endif


@if (!$hasInputs)
    <div class="warning-well warning__project-not-ready">
        <strong>This project is not ready for data collection! You will have to
            <a href="{{ url('myprojects') . '/' . $project->slug . '/formbuilder' }}">add some questions to your
                form(s)</a>
        </strong>
    </div>
@endif

<div class="row flexbox">
    <div class="col-sm-12 col-md-12 col-lg-7 equal-height details-view__wrapper">
        <div id="details-view"
            class="panel panel-default project-details-panel @if ($showPanel != 'details-view') ec5-hide-block @endif ">
            <div class="panel-heading">
                <h4>{{ trans('site.project_details') }}</h4>
                @if ($requestedProjectRole->canEditProject())
                    <button class="btn btn-default btn-action btn-sm pull-right project-details__edit">
                        <i class="material-icons">&#xE254;</i>
                    </button>
                @endif
            </div>
            <div class="panel-body">
                <div class="details-view__logo-wrapper">
                    <img class="img-responsive img-thumbnail img-circle pull-left" width="128" height="128"
                        alt="Project logo"
                        src=" @if ($project->logo_url == '') {{ url('/images/' . 'ec5-placeholder-256x256.jpg') }}
                @else
                    {{ url('/api/internal/media/' . $project->slug . '?type=photo&name=logo.jpg&format=project_thumb') }} @endif">
                </div>

                <div class="details-view__project-details">
                    <h5 class="details-view__project-details__small-description"><i class="material-icons">
                            &#xE0C9;</i>&nbsp;{{ $project->small_description }}
                    </h5>
                    <h5 class="details-view__project-details__created-at"><i
                            class="material-icons">&#xE878;</i>&nbsp;Created
                        on {{ $project->createdAt('l d M Y, H:i') }}</h5>
                </div>

                <div class="clearfix"></div>
                @if ($project->description === '')
                    <p class="text-center">{{ trans('site.no_desc_yet') }}</p>
                @else
                    <p>{!! nl2br(e($project->description)) !!}</p>
                @endif
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

                <form method="POST" action="{{ url('/myprojects/' . $project->slug . '/details') }}"
                    enctype="multipart/form-data" accept-charset="UTF-8">
                    {{ csrf_field() }}
                    <div class="form-group @if ($errors->has('small_description')) has-error @endif">
                        <label class="control-label">{{ trans('site.small_desc') }}</label>
                        <input required type="text" class="form-control" name="small_description"
                            placeholder="{{ trans('site.small_desc_placeholder') }}" maxlength="100"
                            value="@if (old('small_description')) {{ old('small_description') }}@else{{ $project->small_description }} @endif">
                        {{-- @if ($errors->has('small_description'))
                            <small>{{ $errors->first('small_description') }}</small> @endif --}}
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
                        <label class="control-label">{{ trans('site.desc') }}</label>
                        <textarea class="form-control project-long-description" name="description" rows="5">
@if (old('description'))
{{ old('description') }}@else{{ $project->description }}
@endif
</textarea>
                    </div>

                    <div class="form-group">
                        <button class="btn btn-default btn-action pull-right"
                            type="submit">{{ trans('site.update') }}</button>
                    </div>
                </form>
            </div><!-- end panel body -->

        </div><!-- details edit -->
    </div><!-- end col -->


    @if ($requestedProjectRole->canEditProject())
        <div class="col-sm-12 col-md-12 col-lg-5 equal-height">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>{{ trans('site.settings') }}</h4>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table summary="" class="table">
                            <caption class="text-center">
                            </caption>
                            <tbody>
                                <tr>
                                    <th>{{ trans('site.access') }}</th>
                                    <td>

                                        <div class="btn-group">
                                            @foreach (Config::get('ec5Enums.projects_access') as $p)
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
                                                $requestedProjectRole->getUser()->isSuperAdmin() ||
                                                    $requestedProjectRole->getUser()->isAdmin() ||
                                                    $requestedProjectRole->canDeleteProject())
                                                <a data-setting-type="status" data-value="delete"
                                                    class="btn btn-danger btn-sm settings-status"
                                                    href="{{ url('myprojects') . '/' . $project->slug . '/delete' }}">
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
                                            @foreach (Config::get('ec5Enums.projects_visibility') as $p)
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
                                                @foreach (Config::get('ec5Enums.project_categories') as $cat)
                                                    <option value="{{ $cat }}"
                                                        @if ($project->category == $cat) selected @endif>
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

<div class="row">
    <div class="col-sm-12 col-md-12 col-lg-12">
        <div class="panel panel-default">
            <div class="panel-body project-details-panel--feedback text-center">
                <span>

                    <strong>Found a bug? Have a question? Ask the Community at
                        <a href="https://community.epicollect.net" target="_blank">
                            community.epicollect.net
                        </a>
                    </strong>
                </span>
            </div>
        </div>

    </div>
</div>


@if (Auth::check() && Auth::user()->server_role == 'superadmin')
    <div class="row">
        <div class="col-sm-12 col-md-12 col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <span>{{ trans('site.admin') }}</span>
                </div>
                <div class="panel-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="active" role="presentation">
                            <a href="#project-details" aria-controls="project-details" role="tab"
                                data-toggle="tab">{{ trans('site.details') }}</a>
                        </li>
                        <li role="presentation">
                            <a href="#json-view" aria-controls="json-view" role="tab"
                                data-toggle="tab">{{ trans('site.project_definition') }}</a>
                        </li>
                        <li role="presentation">
                            <a href="#jsonextra-view" aria-controls="jsonextra-view" role="tab"
                                data-toggle="tab">{{ trans('site.project_extra') }}</a>
                        </li>

                    </ul>
                    <div class="tab-content">

                        <div role="tabpanel" class="tab-pane active" id="project-details">

                            <p>Project Id : {{ $project->getId() }}</p>

                            <p>Project Ref : {{ $project->ref }}</p>

                            <p>Created By : {{ ec5\Models\Users\User::find($project->created_by)->email }}</p>

                            <p>Updated at : {{ $project->getUpdatedAt() }}</p>
                        </div>

                        <div role="tabpanel" class="tab-pane" id="json-view">
                            <pre style="background:#fff;">{{ $jsonPretty }}</pre>
                        </div>

                        <div role="tabpanel" class="tab-pane" id="jsonextra-view">
                            <pre style="background:#fff;">{{ $jsonPrettyExtra }}</pre>
                        </div>

                    </div><!-- end tabcontent -->
                </div>
            </div>
        </div>
    </div>
@endif

{{-- js variables --}}
<div class="js-project-details hidden" data-js-status="{{ $project->status }}"
    data-js-access="{{ $project->access }}" data-js-visibility="{{ $project->visibility }}"
    data-js-logo_url="{{ $project->logo_url }}" data-js-category="{{ $project->category }}"
    data-js-slug="{{ $project->slug }}">
</div>

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js') . '?' . ENV('RELEASE') }}"></script>
@stop
