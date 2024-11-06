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
