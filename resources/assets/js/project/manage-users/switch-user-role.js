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
    module.switchUserRole = function (projectSlug, email, currentRole, newRole) {

        var url = window.EC5.SITE_URL + '/api/internal/project-users/' + projectSlug + '/switch-role';
        var config = window.EC5.project_users.config;
        var availableRoles = $.map(config.consts.ROLES, function(value){return value;});
        var deferred = new $.Deferred();

        //invalid role
        if ($.inArray(currentRole, availableRoles) === -1 || $.inArray(newRole, availableRoles) === -1) {
            deferred.reject('Invalid role!');
        }

        //same role?
        if (currentRole === newRole) {
            deferred.reject('Old role and new role are the same!');
        }

        var params = {
            email: email,
            currentRole: currentRole,
            newRole: newRole
        };

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: params
        }).done(function (response) {
            console.log(response);
            deferred.resolve(response);
        }).fail(function (error) {
            console.log(error);
            deferred.reject(error);
        });

        return deferred.promise();
    };

}(window.EC5.project_users));
