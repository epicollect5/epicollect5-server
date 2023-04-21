'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     *
     * @param projectSlug
     * @param email
     * @param currentRole
     * @param newRole
     */
    module.updateRoleCounters = function () {

        var pageManageUser = $('.page-manage-users');
        var deferred = new $.Deferred();
        var projectSlug = pageManageUser.data('project-slug');

        //get hold of users-by-role counters
        var counterHandlers = {
            all: pageManageUser.find('.count-overall'),
            creator: pageManageUser.find('.nav-tabs-roles').find('.count-creator'),
            manager: pageManageUser.find('.nav-tabs-roles').find('.count-manager'),
            curator: pageManageUser.find('.nav-tabs-roles').find('.count-curator'),
            collector: pageManageUser.find('.nav-tabs-roles').find('.count-collector'),
            viewer: pageManageUser.find('.nav-tabs-roles').find('.count-viewer')
        }

        var total = 0;
        $.when(window.EC5.project_users.getTotalsByRole(projectSlug)).then(function (counts) {
            Object.keys(counterHandlers).forEach(function (k) {
                counterHandlers[k].text(counts[k]);
                console.log(counts[k]);
                total += counts[k] || 0;
            });
            counterHandlers.all.text(total);

        }).fail(function (error) {
            deferred.reject(error);
        }).always(function () {
            deferred.resolve();
        });

        return deferred.promise();
    };

}(window.EC5.project_users));
