$(document).ready(function () {

    //skip all if not on the manage-users page
    if ($('.page-manage-users').length === 0) {
        return;
    }

    // Variable for storing ajax requests
    var requestTimeout;
    var manageProjectUsersForm = $('.manage-project-users');
    var pageName = '#creator';
    var config = window.EC5.project_users.config;

    window.EC5.overlay.fadeIn();

    // Dynamically load tab content via ajax request
    $('a[data-toggle="tab"]').on('show.bs.tab', function (e) {
        window.EC5.overlay.fadeIn();
        var target = $(e.target).attr('href');
        pageName = 'page-' + target.substring(1);
        $('.manage-project-users__' + pageName).html('');
        $('.user-search-heading').hide();

        // Load users for this role
        $.when(window.EC5.project_users.getProjectUsers(pageName, 1)).then(function (data) {
            $('.manage-project-users__' + pageName).hide().html(data).fadeIn(config.consts.ANIMATION_FAST);
        }, function (error) {
            if (error.responseJSON) {
                window.EC5.toast.showError(error.responseJSON.errors[0].title);
            } else {
                window.EC5.toast.showError(error);
            }
        }).always(function () {
            window.EC5.overlay.fadeOut();
        })
    });

    //creator tab by default so grab creator
    // Load users for this role
    $.when(window.EC5.project_users.getProjectUsers('page-creator').then(function (data) {
        $('.manage-project-users__' + pageName).remove().html(data);
        window.EC5.overlay.fadeOut();
    }, function (error) {
        window.EC5.overlay.fadeOut();
    }));

    //switch role of a user
    // event is attached to parent and filtered so it will work on newly added elements
    $('.page-manage-users .tab-content .panel-body')
        .off().on('click', '.manage-project-users__switch-role', function () {

        var projectSlug = $(this).data('project-slug');
        var userRole = $(this).data('user-role');
        var userEmail = $(this).data('user-email');
        var modal = $('#ec5SwitchUserRole');

        //pre fill user email
        modal.find('.user-email strong').text(userEmail);

        //show all roles
        var availableRoles = $.map(config.consts.ROLES, function (value) {
            return value;
        });

        $(availableRoles).each(function (key, value) {
            modal.find('.role-' + value).show();
        });

        //hide current role from the radio options (useless to show it)
        modal.find('.role-' + userRole).hide();

        //disable confirm button
        modal.find('.switch-role-confirm').attr('disabled', true);

        //deselect all radio
        modal.find('input:radio').prop('checked', false);

        modal.modal('show');

        modal.on('shown.bs.modal', function (e) {
            // do something...
            var newRole = null;

            modal.find('.users__pick-role .radio label').off().on('click', function () {

                //get the new role
                newRole = modal.find('.users__pick-role').find('.radio input:checked').val();

                //enable confirm button only when user pick a role
                modal.find('.switch-role-confirm').attr('disabled', false);
            });

            //on click confirm, switch role
            modal.find('.switch-role-confirm').off().on('click', function () {

                if (newRole === null) {
                    return false;
                }

                if (!projectSlug || !userEmail) {
                    return false;
                }

                window.EC5.overlay.fadeIn();

                //on response, hide modal and show notification
                $.when(window.EC5.project_users
                    .switchUserRole(projectSlug, userEmail, userRole, newRole))
                    .done(function (response) {

                        var pageName = 'page-' + userRole;

                        //update user list for active tab
                        //refresh list of users
                        $.when(window.EC5.project_users.getProjectUsers(pageName, 1).then(function (data) {
                            // Update the relevant page section
                            $('.manage-project-users__' + pageName).html(data);
                            modal.modal('hide');
                            window.EC5.toast.showSuccess(response.data.message);
                            window.EC5.overlay.fadeOut();

                        }, function (error) {
                            window.EC5.overlay.fadeOut();
                            if (error.responseJSON) {
                                window.EC5.toast.showError(error.responseJSON.errors[0].title);
                            } else {
                                window.EC5.toast.showError(error);
                            }
                            modal.modal('hide');

                        }));
                    }).fail(function (error) {
                    console.log(error);
                    window.EC5.overlay.fadeOut();
                    if (error.responseJSON) {
                        window.EC5.toast.showError(error.responseJSON.errors[0].title);
                    } else {
                        window.EC5.toast.showError(error);
                    }
                    modal.modal('hide');
                });
            });
        })
    });


    // Bind on click to pagination links
    manageProjectUsersForm.on('click', '.pagination a', function (e) {

        e.preventDefault();

        window.EC5.overlay.fadeIn();

        // Get manage-project-users container
        var container = $(this).closest('.manage-project-users');

        // Get data-page-name data attribute from the container
        var pageName = container.data('page-name');

        // Get search value
        var search = container.find('.manage-project-users__user-search').val();

        // Get users based on page and any existing search or filter and filter option
        $.when(window.EC5.project_users.getProjectUsers(pageName, $(this).attr('href').split('=')[1], search).then(function (data) {
            // Update the relevant page section
            $('.manage-project-users__' + pageName).html(data);
            window.EC5.overlay.fadeOut()
        }, function () {
            window.EC5.overlay.fadeOut()
        }));
    });

    // Bind keyup event to search input
    manageProjectUsersForm.on('keyup', '.manage-project-users__user-search', function (e) {

        // Get value of input
        var value = this.value;

        // If the length is 3 or more characters, or the user pressed ENTER, search
        if (value.length >= 0 || e.keyCode === 13) {

            window.EC5.overlay.fadeIn();

            // Get manage-project-users container
            var container = $(this).closest('.manage-project-users');

            // Get data-page-name data attribute from the container
            var pageName = container.data('page-name');

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
                $.when(window.EC5.project_users.getProjectUsers(pageName, 1, value).then(function (data) {
                    // Update the relevant page section
                    $('.manage-project-users__' + pageName).html(data);
                    window.EC5.overlay.fadeOut();
                }, function () {
                    window.EC5.overlay.fadeOut();
                }));

            }, requestDelay);
        }

    });

    // Bind on click to reset table
    manageProjectUsersForm.on('click', '.manage-project-users__reset', function (e) {

        window.EC5.overlay.fadeIn();

        e.preventDefault();

        // Get manage-project-users container
        var container = $(this).closest('.manage-project-users');

        // Get data-page-name data attribute from the container
        var pageName = container.data('page-name');

        // Remove search text
        $('.manage-project-users__user-search').val('');

        //refresh list of users
        $.when(window.EC5.project_users.getProjectUsers(pageName, 1).then(function (data) {
            // Update the relevant page section
            $('.manage-project-users__' + pageName).html(data);
            window.EC5.overlay.fadeOut();
        }, function () {
            window.EC5.overlay.fadeOut();
        }));
    });

    // Bind on click to remove user
    manageProjectUsersForm.on('submit', '.manage-project-users__table__remove-form', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = $(this).serializeArray();

        // Get action url
        var url = $(this).attr('action');

        // Get manage-project-users container
        var container = $(this).closest('.manage-project-users');

        // Get data-page-name data attribute from the container
        var pageName = container.data('page-name');

        // Get page number
        var page = container.find('.pagination .active span').html();

        // Get search value
        var search = container.find('.manage-project-users__user-search').val();

        window.EC5.project_users.removeUserProjectRole(url, formData, pageName, page, search);
    });

    // Bind on click to add existing user from modal
    $('.manage-project-users__existing-user-add-form').on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = $(this).serializeArray();

        // Get action url
        var url = $(this).attr('action');

        // Get current page name
        var pageName = formData[1].value ? 'page-' + formData[1].value : 'page-creator';

        window.EC5.project_users.addUserProjectRole(url, formData, pageName, function () {
            // Clear email
            $('#ec5ModalExistingUser').find('input[type=email]').val('');
            // Close this modal
            $('#ec5ModalExistingUser').modal('hide');
        });

    });

    // Bind on click to add new user from modal
    $('.manage-project-users__new-user-add-form').on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var thisFormData = $(this).serializeArray();

        // Retrieve form data from the previous form (email, role)
        var lastFormData = $('.manage-project-users__existing-user-add-form').serializeArray();

        // Concat two arrays
        var postData = thisFormData.concat(lastFormData);

        // Get action url
        var url = $(this).attr('action');

        // Get current page name
        var pageName = postData[2] && postData[2].value ? 'page-' + postData[2].value : 'page-creator';

        window.EC5.project_users.addUserProjectRole(url, postData, pageName, function () {

            // Clear email
            $('#ec5ModalExistingUser').find('input[type=email]').val('');
            // Close previous modal
            $('#ec5ModalExistingUser').modal('hide');
            // Close this modal
            $('#ec5ModalNewUser').modal('hide');
        });

    });

    //pick a csv file
    $('.manage-user-more__import-users').off().on('click', function () {

        var target = $(this);

        //import file first then show modal to pick which column (if more than one)
        //todo not use window, use formbuilder object
        if (!window.EC5.project_users.isOpeningFileBrowser) {

            var file_input = target.find('.manage-user-more__import-users__input-file');

            window.EC5.project_users.isOpeningFileBrowser = true;

            file_input.off('change').on('change', function () {
                //perform the import
                window.EC5.project_users.pickCSVFile(this.files);
                $(this).val(null);
            });

            target.find('.manage-user-more__import-users__input-file').trigger('click');
        }

        //to avoid a infinte loop (since we are triggering the click event)
        //we remove the flag later, to be able to upload another file
        //even if the user tapped on "cancel"
        window.setTimeout(function () {
            window.EC5.project_users.isOpeningFileBrowser = false;
        }, 3000);
    });


    //export users
    $('.manage-user-more__export-users').off().on('click', function () {

        var projectSlug = $(this).find('a').data('project-slug');

        window.EC5.overlay.fadeIn();

        $.when(window.EC5.project_users.exportUsersToCSV(projectSlug)).then(function () {
            window.EC5.overlay.fadeOut();
        }, function (error) {
            if (error.responseJSON) {
                window.EC5.toast.showError(error.responseJSON.errors[0].title);
            } else {
                window.EC5.toast.showError(error);
            }
            window.EC5.overlay.fadeOut();
        });
    });

    /**
     * Remove all users by role
     */
    $('.manage-project-users__delete-by-role').off().on('click', function () {

        var projectSlug = $(this).data('project-slug');
        var role = $(this).data('role');

        window.EC5.overlay.fadeIn();

        $.when(window.EC5.project_users.removeUsersByRole(projectSlug, role)).then(function (response) {
            window.EC5.toast.showSuccess(response.data.message);

            //refresh list of users
            $.when(window.EC5.project_users.getProjectUsers(pageName, 1).then(function (data) {
                // Update the relevant page section
                $('.manage-project-users__' + pageName).html(data);
                window.EC5.overlay.fadeOut();
            }, function () {
                window.EC5.overlay.fadeOut();
            }));

        }, function (error) {
            window.EC5.overlay.fadeOut();
            if (error.responseJSON) {
                window.EC5.toast.showError(error.responseJSON.errors[0].title);
            } else {
                window.EC5.toast.showError(error);
            }
        });
    });
});
