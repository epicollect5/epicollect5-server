'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {

    /**
     *
     * @param projectSlug
     */
    module.removeUsersByRole = function (projectSlug, role) {

        var deferred = new $.Deferred();
        var url = window.EC5.SITE_URL + '/api/internal/project-users/' + projectSlug + '/remove-by-role';

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: { role : role}
        }).done(function (response) {
            var pageName = 'page-' + role;
            $('.manage-project-users__' + pageName).html('');
            deferred.resolve(response);
        }).fail(function(error){
            deferred.reject(error);
        });

        return deferred.promise();

    };
}(window.EC5.project_users));
