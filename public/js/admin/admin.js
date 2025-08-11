'use strict';
window.EC5 = window.EC5 || {};
window.EC5.admin = window.EC5.admin || {};
window.EC5.admin.projects = window.EC5.admin.projects || {};

/**
 * Projects module
 */
(function projects(module) {

    /**
     * Update the user's project role
     *
     * @param role
     * @param projectId
     */
    module.updateRole = function (role, projectId) {

        // Make ajax request to load users
        $.ajax({
            url: window.EC5.SITE_URL + '/admin/update-user-project-role',
            type: 'POST',
            dataType: 'json',
            data: {role: role, project_id: projectId}
        }).done(function (data) {
            window.EC5.toast.showSuccess(data.data.title);
        }).fail(window.EC5.showError);
    };

    /**
     * Function for asynchronously retrieving the
     * list of projects, based on any search/filter
     * criteria, with pagination
     */
    module.getProjects = function (params) {

        var deferred = new $.Deferred();

        console.log(params);

        // Make ajax request to load projects
        $.ajax({
            url: params.url,
            type: 'GET',
            dataType: 'json',
            data: params
        }).done(function (response) {
            deferred.resolve(response);
        }).fail(function (error) {
            deferred.reject(error);
        });

        return deferred.promise();
    };

})(window.EC5.admin.projects);

$(document).ready(function () {

    var endpointUrl = $('.url').data('js');

    var capitalize = function (string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    };

    var throttle = (function () {
        var timer = 0;
        return function (callback, ms) {
            clearTimeout(timer);
            timer = setTimeout(callback, ms);
        };
    })();

    var params = {
        url: endpointUrl,
        page: 1,
        name: '',
        access: '',
        visibility: ''
    };

    var searchBar = $('.page-admin .projects-list__filter-controls .projects-list__project-search');
    var filterControls = $(' .page-admin .projects-list__filter-controls_dropdowns');
    var accessDropdownToggle = filterControls.find('.filter-controls__access .dropdown-toggle');
    var accessDropdownMenu = filterControls.find('.filter-controls__access .dropdown-menu');
    var visibilityDropdownToggle = filterControls.find('.filter-controls__visibility .dropdown-toggle');
    var visibilityDropdownMenu = filterControls.find('.filter-controls__visibility .dropdown-menu');
    var projectsList = $(' .page-admin .projects-list');
    var loader = $('.page-admin .projects-loader');

    projectsList.on('change', '.project-roles', function () {

        var projectId = $(this).data('projectId');
        var role = $(this).val();
        // Update role
        window.EC5.admin.projects.updateRole(role, projectId);
    });

    //filter projects based on search text
    searchBar.keyup(function () {

        params.name = $(this).val().trim();

        //get current selection for visibility and access
        var access = accessDropdownToggle.data('selected-value');
        var visibility = visibilityDropdownToggle.data('selected-value');

        //filter "any"
        params.access = (access === 'any') ? '' : access;
        params.visibility = (visibility === 'any') ? '' : visibility;

        _filterProjects(500);
    });

    //filter based on access value
    accessDropdownMenu.on('click', 'li', function () {

        var selected = $(this).data('filter-value');

        params.access = selected === 'any' ? '' : selected;

        accessDropdownToggle.data('selected-value', selected);
        accessDropdownToggle.parent().find('.dropdown-text').text(capitalize(selected));

        console.log(params);

        _filterProjects(0);

    });

    //filter based on visibility value
    visibilityDropdownMenu.on('click', 'li', function () {

        var selected = $(this).data('filter-value');

        params.visibility = selected === 'any' ? '' : selected;

        visibilityDropdownToggle.data('selected-value', selected);
        visibilityDropdownToggle.parent().find('.dropdown-text').text(capitalize(selected));

        _filterProjects(0);

    });

    //intercept click on pagination links to send ajax request
    //important: re-bind event as empty() removes it!!!!
    $('.pagination').on('click', 'a', onPaginationClick);

    function onPaginationClick(e) {
        e.preventDefault();

        var visibility = visibilityDropdownToggle.data('selected-value');
        var access = accessDropdownToggle.data('selected-value');

        params.page = $(e.target).attr('href').split('page=')[1];
        params.name = searchBar.val().trim();
        params.access = access === 'any' ? '' : access;
        params.visibility = visibility === 'any' ? '' : visibility;

        _filterProjects(0);
    }

    //perform project filtering
    function _filterProjects(delay) {

        loader.removeClass('hidden');
        projectsList.find('.projects__table__wrapper').empty();

        throttle(function () {
            window.EC5.admin.projects.getProjects(params).then(function (response) {
                //hide loader and show projects
                loader.addClass('hidden');
                projectsList.hide().append(response).fadeIn(500);

                //important: re-bind event as empty() removes it!!!!
                $('.pagination').on('click', 'a', onPaginationClick);
            }, function (error) {
                console.log(error);
            });
        }, delay);
    }
});

$(document).ready(function () {

    function sendUpdateRequest(key, value) {

        var deferred = new $.Deferred();

        //show overlay
        window.EC5.overlay.fadeIn();
        var url = window.EC5.SITE_URL + '/api/internal/admin/settings';
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                key: key,
                value: value
            }
        }).done(function (response) {
            console.log(response);
            window.EC5.toast.showSuccess('Settings updated successfully.');
            deferred.resolve(response);
        }).fail(function (error) {
            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {
                    for (var i = 0; i < error.responseJSON.errors.length; i++) {
                        window.EC5.toast.showError(error.responseJSON.errors[i].title);
                    }
                }
            }
            deferred.reject(error);
        }).always(function () {
            window.EC5.overlay.fadeOut();
        });

        return deferred.promise();
    }

    $('[data-setting-type]').on('click', function () {
        // Get the value of the `data-setting-type` attribute
        var settingType = $(this).data('setting-type');

        // Perform your action here
        console.log('Setting type clicked:', settingType);
        // You can add more actions here
        switch (settingType) {
            case 'email-notification-version':

                var state = $(this).data('value');
                console.log('Setting type value clicked:', state);

                sendUpdateRequest('SEND_VERSION_NOTIFICATION_EMAIL', state === 'on').then(function () {
                    $(this).siblings().removeClass('btn-action');
                    // Add `btn-action` class to the clicked button
                    $(this).addClass('btn-action');
                }, function (error) {
                    //do not do anything
                });
                break;
        }
    });
});

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.users = window.EC5.users || {};

/**
 * Manage the server users
 */
(function users(module) {

    module.showError = function (error) {

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

    /**
     * Function for asynchronously retrieving the
     * list of users, based on any search/filter
     * criteria, with pagination
     */
    module.getUsers = function (page, search, server_role, state) {

        window.setTimeout(function () {
            window.EC5.overlay.fadeIn();
        }, 500);

        $('.user-administration__users')
            .find('.user-administration__table')
            .animate({opacity: 0}, 500);

        // Set defaults
        page = typeof page !== 'undefined' ? page : 1;
        search = typeof search !== 'undefined' ? search : '';
        server_role = typeof server_role !== 'undefined' ? server_role : '';
        state = typeof state !== 'undefined' ? state : '';

        // Make ajax request to load users
        $.ajax({
            url: window.EC5.SITE_URL + '/admin/users',
            type: 'GET',
            dataType: 'json',
            data: {
                page: page,
                search: search,
                server_role: server_role,
                state: state
            }
        }).done(function (data) {
            $('.user-administration__users').html(data) // Update the content
                .animate({opacity: 1}, 500); // Animate opacity to 1 over 500 milliseconds
        }).fail(module.showError).always(function () {
            window.EC5.overlay.fadeOut();
        });
    };

    /**
     * Function to update a user given the url and formData
     *
     * @param url
     * @param formData
     */
    module.updateUser = function (url, formData) {

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData
        }).done(function () {

            // Get user-administration container
            var container = $('.user-administration');

            // Retrieve search and filter/filter option values from page elements
            // so pagination works within the current results set
            var search = container.find('.user-administration__user-search').val();
            var server_role = container.find('.user-administration__user-filter__server-role').val();
            var state = container.find('.user-administration__user-filter__state').val();
            var page = container.find('.pagination .active span').html();


            // get users based on page and any existing search or filter and filter option
            module.getUsers(page, search, server_role, state);

        }).fail(module.showError);

    };

    /**
     * Function to add a user given the url and formData
     *
     * @param url
     * @param formData
     * @param callBack
     */
    module.addUser = function (url, formData, callBack) {

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData
        }).done(function () {

            window.EC5.toast.showSuccess('New User added.');
            // Get users based on page and any existing search or filter and filter option
            module.getUsers();

            // If passed a callback, call
            if (callBack) {
                callBack();
            }

        }).fail(module.showError);

    };

})(window.EC5.users);

$(document).ready(function () {

    var userAdministration = $('.user-administration');
    var modalAddUser = $('#ec5ModalAddUser');

    // Bind on click to pagination links
    userAdministration.on('click', '.pagination a', function (e) {

        e.preventDefault();

        // Get user-administration container
        var container = $(this).closest('.user-administration');

        // Retrieve search and filter/filter option values from page elements
        // so pagination works within the current results set
        var search = container.find('.user-administration__user-search').val();
        var filterOption = container.find('.user-administration__user-filter-option').val();

        // Get users based on page and any existing search or filter and filter option
        window.EC5.users.getUsers($(this).attr('href').split('page=')[1], search, 'server_role', filterOption);

        window.scrollTo(0, 0);

    });

    // variable for storing ajax requests
    var requestTimeout;

    // Bind keyup event to search input
    userAdministration.on('keyup', '.user-administration__user-search', function (e) {
        // Get value of input
        var needle = this.value;

        // If the length is 3 or more characters, or the user pressed ENTER, search
        if (needle.length >= 3 || e.keyCode === 13) {

            // Get user-administration container
            var container = $(this).closest('.user-administration');

            // Retrieve filter/filter option values from page elements
            // so pagination works within the current results set
            var server_role = container.find('.user-administration__user-filter__server_role').val();
            var state = container.find('.user-administration__user-filter__state').val();

            // Set delay amount
            // for user to stop typing
            var requestDelay = 200;

            /**
             * Throttle user requests so that we can wait until the user
             * has stopped typing before making ajax calls
             */

            // Clear the previous timeout request
            clearTimeout(requestTimeout);


            // Set new timeout request
            requestTimeout = setTimeout(function () {
                // Get users based on this search (with filter values, if applicable)
                window.EC5.users.getUsers(1, needle, server_role, state);
            }, requestDelay);
        }
    });

    // Bind on change event to filter users by server role
    userAdministration.on('change', '.user-administration__user-filter__state', function (e) {
        // Retrieve search and filter/filter option values from page elements
        // so pagination works within the current results set
        var search = $('.user-administration__user-search').val();
        var server_role = $('.user-administration__user-filter__server-role').val();
        // Get users based on filter and filter option (with search value, if applicable)

        window.EC5.users.getUsers(1, search, server_role, this.value);
    });

    userAdministration.on('change', '.user-administration__user-filter__server-role', function (e) {
        // Retrieve search and filter/filter option values from page elements
        // so pagination works within the current results set
        var search = $('.user-administration__user-search').val();
        var state = $('.user-administration__user-filter__state').val();
        // Get users based on filter and filter option (with search value, if applicable)
        window.EC5.users.getUsers(1, search, this.value, state);
    });


    // Bind on click to reset table
    userAdministration.on('click', '.user-administration__user-clear', function (e) {

        e.preventDefault();
        // Remove search text
        $('.user-administration__user-search').val('');
        ///remove server role selection
        $('.user-administration__user-filter__server-role').val('');
        $('.user-administration__user-filter__state').val('');

        // Get users
        window.EC5.users.getUsers();
    });


    // Bind on click to activate/disable (state) buttons
    userAdministration.on('submit', '.user-administration__table__state-form', function (e) {

        e.preventDefault();
        // Retrieve form data
        var formData = $(this).serialize();
        // Get action url
        var url = $(this).attr('action');

        window.EC5.users.updateUser(url, formData);
    });


    // Bind on click to access (server_role) buttons
    userAdministration.on('submit', '.user-administration__table__server-role-form', function (e) {

        e.preventDefault();
        // Retrieve form data
        var formData = $(this).serialize();
        // Get action url
        var url = $(this).attr('action');

        window.EC5.users.updateUser(url, formData);
    });

    // Bind on click to add new user (via modal)
    $('.manage-users__user-add-form').on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = $(this).serialize();

        // Get action url
        var url = $(this).attr('action');

        // Add user and close modal on success
        window.EC5.users.addUser(url, formData, function () {
            $('#ec5ModalAddUser').modal('hide');
        });
    });

    //handle show password checkbox
    modalAddUser.find('.show-password-control').on('click', function () {
        if ($(this).prop('checked')) {
            modalAddUser.find('input.password-input').each(function () {
                $(this).attr('type', 'text');
            });
        } else {
            modalAddUser.find('input.password-input').each(function () {
                $(this).attr('type', 'password');
            });
        }
    })
});
