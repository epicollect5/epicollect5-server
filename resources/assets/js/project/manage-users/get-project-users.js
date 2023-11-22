'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function getProjectUsers(module) {
    /**
     * Function for asynchronously retrieving the
     * list of project users, based on any search
     * criteria, with pagination
     *
     * @param pageName
     * @param page
     * @param search
     */
    module.getProjectUsers = function (pageName, page, search) {

        var deferred = new $.Deferred();
        var data = {};

        // Set defaults
        page = typeof page !== 'undefined' ? page : 1;
        search = typeof search !== 'undefined' ? search : '';

        // Set up data object
        data[pageName] = page;
        data.search = search;

        var usersRequest = $.ajax({
            url: '',
            type: 'GET',
            dataType: 'json',
            data: data
        });

        var updateCounters = window.EC5.project_users.updateRoleCounters();

        $.when(usersRequest, updateCounters)
            .then(function (data) {
                deferred.resolve(data[0]);
            })
            .fail(function (error) {
                deferred.reject(error[0]);
            });

        return deferred.promise();
    };
}(window.EC5.project_users));
