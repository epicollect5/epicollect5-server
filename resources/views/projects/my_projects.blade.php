@extends('app')
@section('title', trans('site.my_projects'))
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-my-projects'>

        @include('toasts/success')
        @include('toasts/error')

        <div class="row">

            <div class="col-md-3">
                <h1 class="page-title">{{trans('site.my_projects')}}</h1>
                <div class="account">
                    <a href="{{ url('/profile')}}">
                    <span class="material-icons">
                          account_box
                    </span>
                        <small>
                            {{$email}}
                        </small>
                    </a>
                </div>
            </div>

            <div class="col-md-9 projects-list__filter-controls">
                <div class="input-group pull-right">

                    <input id="project-search" type="text" name="search"
                           class="projects-list__project-search form-control"
                           placeholder="{{trans('site.search_for_project')}}">

                    <div class="input-group-btn filter">
                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false" data-js="filter-dropdown">
                            {{ trans('site.show_all') }}
                            <span class="caret"></span>
                        </button>

                        <ul class="dropdown-menu">
                            <li><a class="option" data-filter-type="" data-filter-value="show all"
                                   href="#">{{ trans('site.show_all') }}</a></li>
                            <li class="dropdown-header">Access</li>
                            <li><a class="option" data-filter-type="access" data-filter-value="public"
                                   href="#">{{ trans('site.public') }}</a></li>
                            <li><a class="option" data-filter-type="access" data-filter-value="private"
                                   href="#">{{ trans('site.private') }}</a>
                            </li>
                            <li class="dropdown-header">Status</li>
                            <li><a class="option" data-filter-type="status" data-filter-value="active"
                                   href="#">{{ trans('site.active') }}</a></li>
                            <li><a class="option" data-filter-type="status" data-filter-value="locked"
                                   href="#">{{ trans('site.locked') }}</a></li>
                            <li><a class="option" data-filter-type="status" data-filter-value="trashed"
                                   href="#">{{ trans('site.trashed') }}</a>
                            </li>
                            <li class="dropdown-header">Role</li>
                            <li><a class="option" data-filter-type="role" data-filter-value="creator"
                                   href="#">{{ trans('site.creator') }}</a></li>
                            <li><a class="option" data-filter-type="role" data-filter-value="manager"
                                   href="#">{{ trans('site.project_roles.manager') }}</a></li>
                            <li><a class="option" data-filter-type="role" data-filter-value="curator"
                                   href="#">{{ trans('site.project_roles.curator') }}</a></li>
                            <li><a class="option" data-filter-type="role" data-filter-value="collector"
                                   href="#">{{ trans('site.project_roles.collector') }}</a></li>
                            <li><a class="option" data-filter-type="role" data-filter-value="viewer"
                                   href="#">{{ trans('site.project_roles.viewer') }}</a></li>
                        </ul>

                        <button type="button" class="btn btn-default" data-js="grid">
                            <i class="fa fa-th-large" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-default" data-js="list">
                            <i class="fa fa-th-list" aria-hidden="true"></i>
                        </button>

                    </div>

                </div>

            </div>
        </div>

        <div class="my-projects projects-list hidden">
            @include('projects.project_cards')
        </div>
    </div>
    <span class="url hidden" data-js="{{url('myprojects')}}"></span>
@stop

@section('scripts')
    <script src="{{ asset('/js/projects/projects.js') }}"></script>
    <script>

        $(document).ready(function () {

            // Initialise the projects' object with variables/dom objects
            window.EC5.projects.init($('.url').attr('data-js'), {
                projects_div: $('.projects-list'),
                filter_controls: $('.projects-list__filter-controls'),
                pagination_class: '.pagination > li > a',
                search_input_class: '.projects-list__project-search',
                filter_reset_class: '.projects-list__project-reset',
                filter_dropdown_class: '.dropdown-menu > li > a',
                filter_dropdown: $('.projects-list__filter-controls .dropdown-toggle[data-js="filter-dropdown"]'),
                grid: $('.projects-list__filter-controls .btn[data-js="grid"]'),
                list: $('.projects-list__filter-controls .btn[data-js="list"]'),
                projects_item_class: '.projects-list .item',
                grid_view_class: '.grid-view',
                list_view_class: '.list-view'

            });
            // Set up the event listeners
            window.EC5.projects.setUpListeners();
            // Get an initial list of projects
            window.EC5.projects.getProjects();
        });
    </script>
@stop
