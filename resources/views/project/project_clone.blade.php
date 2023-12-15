<div class="panel panel-default">
    <div class="panel-heading">
        <span>{{ trans('site.clone_project') }}</span>
    </div>
    <div class="panel-body">

        <form id="ec5-form" method="POST"
              action="{{ url('/myprojects/'.$requestAttributes->requestedProject->slug . '/clone') }}"
              accept-charset="UTF-8" enctype="multipart/form-data">
            {{ csrf_field() }}
            <div id="project-name-form-group" class="form-group @if ($errors->has('name')) has-error @endif">
                <label class="control-label">{{ trans('site.project_name') }}</label>
                <input required id="project-name" type="text" name="name" class="form-control"
                       placeholder="Awesome Project name" value="{{ old('name') }}">
                <img id="project-loader-gif" src="{{ asset('/images/ajax-loader.gif') }}" class="hidden"/>
                @if ($errors->has('name'))
                    <small>{{ $errors->first('name') }}</small>
                @endif
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="clone-users" value="y">
                    {{ trans('site.clone_users') }}
                </label>
            </div>
            <div class="form-group">
                <button class="btn btn-default btn-action pull-right" type="submit">Clone</button>
            </div>
            @section('scripts')
                <script type="text/javascript" src="{{ asset('js/project/check_dupe_name.js') }}">
                </script>
            @stop

        </form>
    </div>
</div>

