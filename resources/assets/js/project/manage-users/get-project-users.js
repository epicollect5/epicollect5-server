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

        // Make ajax request to load users
        $.ajax({
            url: '',
            type: 'GET',
            dataType: 'json',
            data: data
        }).done(function (data) {
            //update role counters
            $.when(window.EC5.project_users.updateRoleCounters()).always(function () {
                deferred.resolve(data);
            });
        }).fail(function (error) {
            //update role counters anyway
            $.when(window.EC5.project_users.updateRoleCounters()).always(function () {
                deferred.reject(error);
            });
        });

        return deferred.promise();
    };
}(window.EC5.project_users));
