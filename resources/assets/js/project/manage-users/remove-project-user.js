'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     * Function to remove a user project role
     *
     * @param url
     * @param formData
     * @param pageName
     * @param page
     * @param search
     */
    module.removeUserProjectRole = function (url, formData, pageName, page, search) {

        var config = window.EC5.project_users.config;
        var len = formData.length;
        var dataObj = {};
        var i;

        //show overlay
        window.EC5.overlay.fadeIn();

        // Get all values from form data
        for (i = 0; i < len; i++) {
            dataObj[formData[i].name] = formData[i].value;
        }

        // Reduce total users by one, as we are removing one
        var totalPages = dataObj['total-users'] - 1;

        // If we have no users left on this page,
        // send user to the previous page
        if (totalPages === 0) {
            page = page - 1;
        }

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData,
            page: page
        }).done(function (data) {
            // Get project users based on page and any existing search
            $.when(module.getProjectUsers(pageName, page, search)).then(function (response) {
                // Update the relevant page section
                $('.manage-project-users__' + pageName).html(response);
                // Show success
                window.setTimeout(function () {
                    window.EC5.toast.showSuccess(data.data.message);
                    window.EC5.overlay.fadeOut();
                }, config.consts.ANIMATION_NORMAL);
            }, function (error) {

                window.setTimeout(function () {
                    if (error.responseJSON.errors) {
                        // Show the errors
                        if (error.responseJSON.errors.length > 0) {
                            for (i = 0; i < error.responseJSON.errors.length; i++) {
                                window.EC5.toast.showError(error.responseJSON.errors[i].title);
                            }
                        }
                    }
                    window.EC5.overlay.fadeOut();
                }, config.consts.ANIMATION_NORMAL);
            });
        }).fail(function (error) {
            window.setTimeout(function () {
                if (error.responseJSON.errors) {
                    // Show the errors
                    if (error.responseJSON.errors.length > 0) {
                        for (i = 0; i < error.responseJSON.errors.length; i++) {
                            window.EC5.toast.showError(error.responseJSON.errors[i].title);
                        }
                    }
                }
                window.EC5.overlay.fadeOut();
            }, config.consts.ANIMATION_NORMAL);
        });
    };
}(window.EC5.project_users));
