<form id="ec5-form" class="create-project-form" method="POST"
      action="{{ url('/myprojects/import') }}" accept-charset="UTF-8"
      enctype="multipart/form-data" class="form-horizontal">
    {{ csrf_field() }}

    <div id="project-name-form-group-import"
         class="form-group has-feedback @if (($errors->has('name') || $errors->has('slug')) && $tab === 'import') has-error @endif">
        <label class="control-label">{{trans('site.project_name')}}</label>
        <input required id="project-name-import" type="text" name="name"
               class="form-control"
               placeholder="{{trans('site.project_placeholder')}}"
               @if ($tab === 'import')
                   value="{{ old('name') }}"
               @else
                   value=""
               @endif
               minlength="3"
               maxlength="50">

        <span id="project-loader" class="form-control-feedback hidden">
                                <i class="fa fa-2x fa-circle-o-notch fa-spin"></i>
                            </span>

        @if ($errors->has('name') && $tab === 'import')
            @if (strpos($errors->first('name'), 'ec5_') === false)
                {{--error was already translated--}}
                <small class="text-danger">{{ $errors->first('name') }}</small>
            @else
                {{--translate error--}}
                <small class="text-danger">{{ config('epicollect.codes.' . $errors->first('name')) }}</small>
            @endif
        @else
            <small>{{trans('site.max_50_chars')}}</small>
        @endif

        <div class="clearfix"></div>
        <div class="form-group project-json-file-picker @if ($errors->has('file')) has-error @endif">
            <!-- Label on its own line -->
            <label class="control-label">Project <code>.json</code> file
                <a href="https://docs.epicollect.net/web-application/import-and-export-projects" target="_blank">
                    <span class="material-symbols-outlined">
                        info
                    </span>
                </a>
            </label>

            <!-- Input group (file input, button, file name) on the next line -->
            <div class="file-input-wrapper">
                <input required type="file" id="file-input" name="file" class="file-input-hidden"
                       onchange="updateFileName(this)">

                <!-- Custom File Button -->
                <button type="button" class="btn btn-default btn-sm" id="file-button"
                        onclick="document.getElementById('file-input').click();">
                    Choose File
                </button>

                <!-- Display File Name -->
                <span id="file-name" class="ml-2">No file chosen</span>
            </div>

            <!-- Display validation error -->
            @if ($errors->has('file'))
                <small class="text-danger">{{ config('epicollect.codes.' . $errors->first('file')) }}</small>
            @endif

            <!-- Optional JavaScript to display selected file name -->
            <script>
                function updateFileName(input) {
                    document.getElementById('file-name').textContent = input.files.length > 0 ? input.files[0].name : 'No file chosen';
                }
            </script>
        </div>
        <hr/>


    </div>

    <div class="form-group text-center">
        <button class="btn btn-default btn-action pull-right"
                type="submit">{{trans('site.import_project')}}</button>
    </div>
</form>
