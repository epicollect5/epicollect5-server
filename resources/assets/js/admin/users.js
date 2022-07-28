'use strict';
window.EC5 = window.EC5 || {};
window.EC5.users = window.EC5.users || {};

/**
 * Manage the server users
 */
(function users(module) {

    /**
     *
     * @param error
     */
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
     *
     * @param page
     * @param search
     * @param filter
     * @param filterOption
     */
    module.getUsers = function (page, search, filter, filterOption) {

        // Set defaults
        page = typeof page !== 'undefined' ? page : 1;
        search = typeof search !== 'undefined' ? search : '';
        filter = typeof filter !== 'undefined' ? filter : '';
        filterOption = typeof filterOption !== 'undefined' ? filterOption : '';

        // Make ajax request to load users
        $.ajax({
            url: window.EC5.SITE_URL + '/admin',
            type: 'GET',
            dataType: 'json',
            data: { page: page, search: search, filter: filter, filterOption: filterOption }
        }).done(function (data) {

            $('.user-administration__users').html(data);

        }).fail(module.showError);
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
            var filter = container.find('.user-administration__user-filter').val();
            var filterOption = container.find('.user-administration__user-filter-option').val();
            var page = container.find('.pagination .active span').html();

            // get users based on page and any existing search or filter and filter option
            module.getUsers(page, search, filter, filterOption);

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
        var filter = container.find('.user-administration__user-filter').val();
        var filterOption = container.find('.user-administration__user-filter-option').val();

        // Get users based on page and any existing search or filter and filter option
        window.EC5.users.getUsers($(this).attr('href').split('page=')[1], search, filter, filterOption);

        window.scrollTo(0, 0);

    });

    // variable for storing ajax requests
    var requestTimeout;

    // Bind keyup event to search input
    userAdministration.on('keyup', '.user-administration__user-search', function (e) {

        // Get value of input
        var value = this.value;

        // If the length is 3 or more characters, or the user pressed ENTER, search
        if (this.value.length >= 0 || e.keyCode === 13) {

            // Get user-administration container
            var container = $(this).closest('.user-administration');

            // Retrieve filter/filter option values from page elements
            // so pagination works within the current results set
            var filter = container.find('.user-administration__user-filter').val();
            var filterOption = container.find('.user-administration__user-filter-option').val();

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
                window.EC5.users.getUsers(1, value, filter, filterOption);

            }, requestDelay);

        }

    });

    // Default state and access array values
    var stateValues = { active: 'Active', disabled: 'Disabled' };
    var accessValues = { basic: 'Basic', admin: 'Admin', superadmin: 'Superadmin' };

    // Bind on change event for filter drop down to populate filter options
    userAdministration.on('change', '.user-administration__user-filter', function (e) {

        // Get user-administration container
        var container = $(this).closest('.user-administration');
        var userFilterOption = $('.user-administration__user-filter-option');

        // Remove previous options, leaving initial option
        userFilterOption.children('option:not(:first)').remove();

        // Set actions for each case
        switch (this.value) {

            case 'state':
                // Set options for active/disabled (state) filter
                $.each(stateValues, function (key, value) {
                    container.find('.user-administration__user-filter-option').append($('<option/>', {
                        value: key,
                        text: value
                    }));
                });
                userFilterOption.prop('disabled', false);
                break;

            case 'server_role':
                // Set options for access (server_role) filter
                $.each(accessValues, function (key, value) {
                    $('.user-administration__user-filter-option').append($('<option/>', {
                        value: key,
                        text: value
                    }));
                });

                userFilterOption.prop('disabled', false);
                break;

            default:
                // By default, disable filter options
                userFilterOption.prop('disabled', true);

                // Check for search value
                var search = $('.user-administration__user-search').val();
                // Reset table (with search value, if applicable)
                window.EC5.users.getUsers(1, search);
        }


    });


    // Bind on change event for filters to user properties
    userAdministration.on('change', '.user-administration__user-filter-option', function (e) {

        // Get user-administration container
        var container = $(this).closest('.user-administration');

        // Retrieve search and filter/filter option values from page elements
        // so pagination works within the current results set
        var search = container.find('.user-administration__user-search').val();
        var filter = container.find('.user-administration__user-filter').val();

        // Get users based on filter and filter option (with search value, if applicable)
        window.EC5.users.getUsers(1, search, filter, this.value);

    });


    // Bind on click to reset table
    userAdministration.on('click', '.user-administration__user-reset', function (e) {

        e.preventDefault();

        // Get user-administration container
        var container = $(this).closest('.user-administration');

        // Remove search text
        container.find('.user-administration__user-search').val('');
        // Set first filter option as selected
        container.find('.user-administration__user-filter').val(container.find('.user-administration__user-filter option:first').val());
        // Remove previous filter options and disable
        container.find('.user-administration__user-filter-option').children('option:not(:first)').remove();

        container.find('.user-administration__user-filter-option').prop('disabled', true);

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
            modalAddUser.find('input.password-input').each(function (iput) {
                $(this).attr('type', 'password');
            });
        }
    })
});
