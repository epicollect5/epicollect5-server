'use strict';
window.EC5 = window.EC5 || {};
window.EC5.projects = window.EC5.projects || {};

/**
 * Retrieving projects
 */
(function projects(module) {

    // Default ordering object
    module.defaultOrdering = {
        field: 'created_at', type: 'asc', label: 'Date Asc'
    };

    // Object to store the current filters
    module.options = {
        page: 1,
        search: '',
        filter_type: '',
        filter_value: '',
        access: '',
        visibility: '',
        order: module.defaultOrdering
    };

    // The dom elements to manipulate
    module.domElements = {};

    /**
     * Function to view projects as list
     *
     * @private
     */
    function _listView() {
        if (module.domElements.projects_item_class && module.domElements.grid_view_class && module.domElements.list_view_class) {
            $(module.domElements.grid_view_class).addClass('hidden');
            $(module.domElements.list_view_class).removeClass('hidden').hide().fadeIn();
            $(module.domElements.projects_item_class).removeClass('grid-group-item').addClass('list-group-item');
        }
    }

    /**
     * Function to view projects as grid
     *
     * @private
     */
    function _gridView() {
        if (module.domElements.projects_item_class && module.domElements.grid_view_class && module.domElements.list_view_class) {
            $(module.domElements.projects_item_class).removeClass('list-group-item').addClass('grid-group-item hidden');
            $(module.domElements.list_view_class).addClass('hidden');
            $(module.domElements.grid_view_class).removeClass('hidden');
            $(module.domElements.projects_item_class).removeClass('hidden').hide().fadeIn();
        }
    }

    /**
     * Initialise this module
     *
     * @param url
     * @param domElements
     * @param options
     * @param successCallback
     * @param errorCallback
     */
    module.init = function (url, domElements, options, successCallback, errorCallback) {

        window.EC5.overlay.fadeIn();

        module.url = url;
        module.domElements = domElements;
        module.options = options ? options : module.options;
        module.successCallback = successCallback ? successCallback : function (data) {

            // If we have a supplied projects div to manipulate
            if (module.domElements.projects_div) {

                // Get the default view
                var view = module.getDefaultView();

                // Load html
                module.domElements.projects_div.html(data);

                // Get the default view
                switch (view) {
                    case 'list':
                        _listView();
                        break;
                    default:
                        _gridView();
                }

                // Make visible if hidden
                module.domElements.projects_div.removeClass('hidden');
                window.scrollTo({top: 0, behavior: 'auto'});
                window.setTimeout(function () {
                    window.EC5.overlay.fadeOut();
                }, 500);
            }
        };
        module.errorCallback = errorCallback ? errorCallback : function (error) {
            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {

                    var i;
                    for (i = 0; i < error.responseJSON.errors.length; i++) {
                        window.EC5.toast.showError(error.responseJSON.errors[i].title);
                    }
                }
            }
        };

    };

    /**
     * Function for asynchronously retrieving the
     * list of projects, based on any search/filter
     * criteria, with pagination
     */
    module.getProjects = function () {
        // Make ajax request to load projects
        $.ajax({
            url: module.options.url, type: 'GET', dataType: 'json', data: {
                page: module.options.page,
                search: module.options.search,
                filter_type: module.options.filter_type,
                filter_value: module.options.filter_value
            }
        }).done(module.successCallback).fail(module.errorCallback);
    };

    /**
     * Get the default view 'list' or 'grid'
     * Via local storage
     *
     * @returns {*}
     */
    module.getDefaultView = function () {
        // Check if default view is a list, Otherwise the grid view will be loaded by default
        return localStorage.getItem('ec5-projects-list-view') === '1' ? 'list' : 'grid';
    };

    /**
     *  Set up all the event listeners
     */
    module.setUpListeners = function () {

        if (module.domElements.projects_div) {
            // Pagination listener
            if (module.domElements.pagination_class) {

                // Bind on click to pagination links
                module.domElements.projects_div.on('click', module.domElements.pagination_class, function (e) {

                    e.preventDefault();

                    module.options.page = $(this).attr('href').split('page=')[1];

                    // Get the projects
                    module.getProjects();

                    //show loader overlay
                    //show overlay
                    window.EC5.overlay.fadeIn();
                });
            }

            // Bind on clicks for list and grid views
            if (module.domElements.list) {
                module.domElements.list.click(function (e) {
                    e.preventDefault();
                    // Add list view as the default in local storage
                    localStorage.setItem('ec5-projects-list-view', 1);
                    _listView();
                });
            }

            if (module.domElements.grid) {
                module.domElements.grid.click(function (e) {
                    e.preventDefault();
                    // Remove list view as default from local storage
                    window.localStorage.removeItem('ec5-projects-list-view');
                    _gridView();
                });
            }

            // Filter controls
            if (module.domElements.filter_controls) {

                // Search input listener
                if (module.domElements.search_input_class) {

                    var requestTimeout;

                    // Bind on keyup event for searching
                    module.domElements.filter_controls.on('keyup', module.domElements.search_input_class, function (e) {

                        // If the length is 0 or more characters, or the user pressed ENTER, search
                        if (this.value.length >= 0 || e.keyCode === 13) {

                            module.options.search = $(this).val();
                            module.options.page = 1;

                            // Set delay amount
                            // for user to stop typing
                            var requestDelay = 300;

                            /**
                             * Throttle user requests so that we can wait until the user
                             * has stopped typing before making ajax calls
                             */

                            // Clear the previous timeout request
                            clearTimeout(requestTimeout);

                            // Set new timeout request
                            requestTimeout = setTimeout(function () {
                                // Get the projects
                                module.getProjects();
                            }, requestDelay);
                        }
                    });
                }

                // Filter listener
                if (module.domElements.filter_dropdown_class && module.domElements.filter_dropdown) {

                    // Bind on click event for filters
                    module.domElements.filter_controls.on('click', module.domElements.filter_dropdown_class, function (e) {

                        e.preventDefault();
                        window.EC5.overlay.fadeIn();

                        // If a different filter has been selected
                        if ($(this).data('filterValue').toLowerCase().trim() !== module.domElements.filter_dropdown.text().toLowerCase().trim()) {

                            // Set filter type and values
                            module.options.filter_type = $(this).data('filterType') ? $(this).data('filterType') : '';
                            module.options.filter_value = $(this).data('filterValue') ? $(this).data('filterValue') : '';
                            // Reset to first page
                            module.options.page = 1;

                            // Get the projects
                            module.getProjects();

                            // Set active filter
                            module.domElements.filter_dropdown.html(module.options.filter_value + '&nbsp;<span class="caret"></span>');

                        }

                    });
                }

                // Filter reset button
                if (module.domElements.filter_reset_class) {
                    // Bind on click to reset table
                    module.domElements.filter_controls.on('click', module.domElements.filter_reset_class, function (e) {

                        e.preventDefault();

                        // Reset ordering object
                        module.defaultOrdering = {
                            field: 'created_at', type: 'asc', label: 'Date Asc'
                        };

                        // Reset object to store the current filters
                        module.options = {
                            page: 1, search: '', filter_type: '', filter_value: '', order: module.defaultOrdering
                        };

                        // Clear any inputs
                        module.domElements.filter_controls.find('input[type="text"]').val('');

                        // Get the projects
                        module.getProjects();
                    });
                }
            }
        }
    };

})(window.EC5.projects);

