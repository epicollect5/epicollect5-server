'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     * Function to add a user project role
     *
     * @param url
     * @param formData
     * @param pageName
     * @param callBack
     */
    module.addUserProjectRole = function (url, formData, pageName, callBack) {

        var i;

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData
        }).done(function (data) {
            // Show success
            window.EC5.toast.showSuccess(data.data.message);

            // Get project users based on page and any existing search
            $.when(module.getProjectUsers(pageName, 1)).then(function (response) {

                var selectedUserRole = pageName.replace('page-', '');
                // Update the relevant page section
                $('.manage-project-users__' + pageName).html(response);

                //switch tab
                $('.page-manage-users .nav-tabs li').find('a.' + selectedUserRole + '-tab-btn').trigger('click');

                // If passed a callback function
                if (callBack) {
                    callBack();
                }
            });
        }).fail(function (error) {

            var userDoesntExist = 'ec5_90';

            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {

                    for (i = 0; i < error.responseJSON.errors.length; i++) {
                        if (error.responseJSON.errors[i].code === userDoesntExist) {
                            // If the user doesn't exist, ask the user if they want to add
                            $('#ec5ModalNewUser').modal();

                        } else {
                            window.EC5.toast.showError(error.responseJSON.errors[i].title);
                        }
                    }
                }
            }
        });
    };

}(window.EC5.project_users));
