@extends('app')
@section('title', trans('site.create_project'))
@section('page-name', 'project-create')
@section('content')

    <div class='container page-create-project'>
        <div class="row">
            <h1 class="page-title">{{trans('site.create_project')}}</h1>
            @if (count($errors->getMessages()) > 0)
                @if($errors->has('missing_keys') || $errors->has('extra_keys'))
                    <div class="var-holder-error" data-message="{{trans('site.invalid_form')}}"></div>
                @elseif($errors->has('slug'))
                    <div class="var-holder-error"
                         data-message="{{config('epicollect.codes.' . $errors->first())}}"></div>
                @else
                    @foreach($errors->all() as $error)
                        @if (strpos($error, 'ec5_') === false)
                            {{--error was already translated--}}
                            <div class="var-holder-error" data-message="{{$error}}"></div>
                        @else
                            {{--translate error--}}
                            <div class="var-holder-error" data-message="{{config('epicollect.codes.' . $error)}}"></div>
                        @endif
                    @endforeach
                @endif
                <script>
                    var errors = '';
                    $('.var-holder-error').each(function () {
                        errors += $(this).attr('data-message') + '</br>'
                    });
                    EC5.toast.showError(errors);
                </script>
            @endif

            <div class="col-sm-6 col-md-6 col-md-offset-3 col-sm-offset-3">
                {{-- Nav tabs --}}
                <ul class="nav nav-tabs">
                    <li role="presentation" class="{{ ($tab === 'create') ? 'active' : '' }}"
                        aria-controls="new-project">
                        <a href="#new-project" role="tab" data-toggle="tab">{{trans('site.new_project')}}
                        </a>
                    </li>
                    <li role="presentation" class="{{ ($tab === 'import') ? 'active' : '' }}"
                        aria-controls="import-project">
                        <a href="#import-project" role="tab" data-toggle="tab">{{trans('site.import_project')}}
                        </a>
                    </li>
                </ul>

                {{-- Tab panes --}}
                <div class="tab-content">
                    <div class="tab-pane {{ ($tab === 'create') ? 'active' : '' }}  new-project" id="new-project">
                        @include('project.create.tab_new_project', ['tab' => $tab])
                    </div>
                    <div class="tab-pane {{ ($tab === 'import') ? 'active' : '' }} import-project" id="import-project">
                        @include('project.create.tab_import_project',['tab' => $tab] )
                    </div>
                </div>

            </div>
        </div>
    </div>

@stop

@section('scripts')
    <script type="text/javascript" src="{{ asset('js/project/project.js').'?'.config('app.release') }}"></script>
@stop

