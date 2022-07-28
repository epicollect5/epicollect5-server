@extends('app')
@section('title', trans('site.projects_search'))
@section('page-name', Route::getCurrentRoute()->uri())
@section('content')

    <div class='container-fluid page-search-projects'>

        @include('projects.project_list_navbar')

        <div class="row page-search-projects__filter-controls">

            <div class="col-xs-12 col-sm-6 col-md-6 col-lg-8">
                <input id="project-filter" type="text" name="search"
                       class="projects-list__project-filter form-control"
                       maxlength="50"
                       placeholder="{{trans('site.filter_projects_by_name')}}">
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">
                <div id="projects-order" class="input-group text-right">
                    <div class="input-group-btn filter">
                        <button type="button"
                                class="btn btn-default dropdown-toggle"
                                data-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false"
                                data-js="filter-dropdown"
                                data-order-selected-value="entries-high-low"
                        >
                            <span class="dropdown-text">Entries high - low</span>
                            <span class="caret"></span>
                        </button>

                        <ul class="dropdown-menu pull-right">
                            <li data-order-value="entries-high-low">
                                <a class="option" href="#">
                                    Entries high - low
                                </a>
                            </li>


                            <li data-order-value="entries-low-high">
                                <a class="option" href="#">
                                    Entries low - high
                                </a>
                            </li>
                            <li role="separator" class="divider"></li>

                            <li data-order-value="newest">
                                <a class="option" href="#">
                                    Newest
                                </a>
                            </li>
                            <li data-order-value="oldest">
                                <a class="option" href="#">
                                    Oldest
                                </a>
                            </li>
                            <li role="separator" class="divider"></li>

                            <li data-order-value="a-z">
                                <a class="option"
                                   href="#">
                                    A - Z
                                </a>
                            </li>
                            <li data-order-value="z-a">
                                <a class="option" href="#">
                                    Z - A
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="loader hidden">Loading...</div>

        <div class="project_search_cards">
            @include('projects.project_search_cards', ['projects'=> $projects])
        </div>
    </div>
    <span class="endpoint hidden" data-route="{{route('projects-search')}}"></span>

@stop

@section('scripts')
    <script src="{{ asset('/js/projects/projects.js').'?'.ENV('RELEASE') }}"></script>
@stop



