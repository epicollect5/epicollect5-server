'use strict';
//run only on project search page
if ($('.page-search-projects').length > 0) {

    window.EC5 = window.EC5 || {};
    window.EC5.projectsSearch = window.EC5.projectsSearch || {};

    $(document).ready(function () {

        var delay = (function () {
            var timer = 0;
            return function (callback, ms) {
                clearTimeout(timer);
                timer = setTimeout(callback, ms);
            };
        })();

        var endpoint = $('.endpoint').data('route');
        var selectedOption = 'entries-high-low';//default
        var projectName = ''; //default
        var page = 1;

        //the order options
        var orderOptions = {
            'entries-high-low': 'Entries high - low',
            'entries-low-high': 'Entries low - high',
            newest: 'Newest',
            oldest: 'Oldest',
            'a-z': 'A - Z',
            'z-a': 'Z - A'
        };

        //bind dom
        var filterControls = $('.page-search-projects .page-search-projects__filter-controls ');
        var projectsContainer = $('.project_search_cards');
        var orderByDropdownToggle = filterControls.find('.dropdown-toggle');
        var orderByDropdownToggleText = orderByDropdownToggle.find('.dropdown-text');
        var orderByDropdown = filterControls.find('.dropdown-menu');
        var searchBar = filterControls.find('.projects-list__project-filter');
        var loader = $('.loader');

        //trigger a search after typing at least 3 chars (throttle 1 sec)
        searchBar.keyup(function () {

            projectName = $(this).val().trim();

            if (projectName.length >= 1) {
                delay(function () {
                    window.EC5.projectsSearch.getProjects({
                        projectName: projectName,
                        order: selectedOption,
                        page: 1
                    });

                    console.log(projectName);
                }, 500);
            } else {
                //reset giving all projects
                if (projectName.trim() == '') {
                    delay(function () {
                        window.EC5.projectsSearch.getProjects({
                            projectName: '',
                            order: selectedOption,
                            page: 1
                        });

                        console.log(projectName);
                    }, 500);
                }
            }
        });

        orderByDropdown.on('click', 'li', function () {

            selectedOption = $(this).data('order-value');
            projectName = searchBar.val().trim();

            //set dropdown toggle text to currently selected order option
            orderByDropdownToggleText.text(orderOptions[selectedOption]);
            orderByDropdownToggle.data('order-selected-value', selectedOption);

            //trigger a search when changing dropdown option
            window.EC5.projectsSearch.getProjects({
                projectName: projectName,
                order: selectedOption,
                page: 1//when changing order, reset pagination
            });
        });

        //intercept click on pagination links to send ajax request
        $('.pagination').on('click', 'a', onPaginationClick);

        function onPaginationClick(e) {
            e.preventDefault();

            page = $(e.target).attr('href').split('page=')[1];
            //selectedOption = get selected option
            projectName = searchBar.val().trim();

            selectedOption = orderByDropdownToggle.data('order-selected-value');

            console.log('page:', page);
            console.log('projectName:', projectName);
            console.log('selectedOption:', selectedOption);

            //trigger a search when changing page, we current parameters
            window.EC5.projectsSearch.getProjects({
                projectName: projectName,
                order: selectedOption,
                page: page
            });
        }

        (function (module) {

            module.getProjects = function (params) {

                var queryParams = {};

                //hide current projects cards and show loader
                projectsContainer.empty();
                loader.removeClass('hidden');

                //map parameters to api query parames sort_by, sort order
                switch (params.order) {

                    case 'entries-high-low':
                        queryParams.sort_by = 'total_entries';
                        queryParams.sort_order = 'desc';
                        break;

                    case 'entries-low-high':
                        queryParams.sort_by = 'total_entries';
                        queryParams.sort_order = 'asc';
                        break;

                    case 'newest':
                        queryParams.sort_by = 'created_at';
                        queryParams.sort_order = 'desc';
                        break;
                    case 'oldest':
                        queryParams.sort_by = 'created_at';
                        queryParams.sort_order = 'asc';
                        break;
                    case 'a-z':
                        queryParams.sort_by = 'name';
                        queryParams.sort_order = 'asc';
                        break;
                    case 'z-a':
                        queryParams.sort_by = 'name';
                        queryParams.sort_order = 'desc';
                        break;
                }

                //is there any project name?
                queryParams.name = params.projectName;
                //pagination?
                queryParams.page = params.page;

                //ajax GET request to get projects matching the parameters
                $.ajax({
                    url: endpoint,
                    type: 'GET',
                    data: queryParams,
                    success: function (response) {
                        //console.log(response);
                        loader.addClass('hidden');

                        projectsContainer.hide().append(response).fadeIn(1000);

                        //rebind pagination links (empty() removes them)
                        $('.pagination').on('click', 'a', onPaginationClick);

                        window.scrollTo(0, 0);

                    },
                    error: function (error) {
                        console.log(error);
                    }
                });
            }
        }(window.EC5.projectsSearch));
    });
}
