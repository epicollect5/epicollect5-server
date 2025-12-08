'use strict';
window.EC5 = window.EC5 || {};
window.EC5.projectApps = window.EC5.projectApps || {};

/**
 * Manage the server users
 */
(function projectApps(module) {

    // current client ID
    module.currentClientId = '';

    /**
     * Function for asynchronously retrieving the
     * list of users, based on any search/filter
     * criteria, with pagination
     *
     */
    module.getApps = function () {

        // Make ajax request to load users
        $.ajax({
            url: '',
            type: 'GET',
            dataType: 'json',
            data: {}
        }).done(function (data) {
            $('.project-apps-list').html(data);
        }).fail(window.EC5.showError);
    };

    /**
     * Make a post request then get apps on success
     *
     * @param url
     * @param formData
     * @param callBack
     */
    module.post = function (url, formData, callBack) {

        // Make ajax request to load project apps
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData
        }).done(function () {

            // If passed a callback, call
            if (callBack) {
                callBack();
            }

            module.getApps();
        }).fail(window.EC5.showError);

    };

})(window.EC5.projectApps);

$(document).ready(function () {

    var projectApps = $('.project-apps');

    // Bind on click to pagination links
    projectApps.on('click', '.pagination a', function (e) {

        e.preventDefault();
        window.EC5.projectApps.getApps();

    });


    var projectAppsForm = $('#ec5-form-project-apps');

    // Bind on submit to form
    projectAppsForm.on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = $(this).serialize();

        // Get action url
        var url = $(this).attr('action');
        window.EC5.overlay.fadeIn();
        window.EC5.projectApps.post(url, formData, function () {
            // Disable create app button
            $('#create-app').prop('disabled', true);
            // Close modal
            $('#modal-create-app').modal('hide');
            window.EC5.toast.showSuccess('New App added.');
            setTimeout(function () {
                window.EC5.overlay.fadeOut();
            }, 500);
        });

    });


    var projectAppList = $('.project-apps-list');

    projectAppList.on('click', '#delete-app', function (e) {

        e.preventDefault();
        // Hide revoke form
        $('#ec5-form-project-app-revoke').addClass('hidden');
        // Show delete form
        $('#ec5-form-project-app-delete').removeClass('hidden');
        // Get the current project client app id
        window.EC5.projectApps.currentClientId = $(this).data('clientId');
    });

    projectAppList.on('click', '#revoke-app', function (e) {

        e.preventDefault();
        // Hide delete form
        $('#ec5-form-project-app-delete').addClass('hidden');
        // Show revoke form
        $('#ec5-form-project-app-revoke').removeClass('hidden');
        // Get the current project client app id
        window.EC5.projectApps.currentClientId = $(this).data('clientId');

    });

    var projectAppDeleteForm = $('#ec5-form-project-app-delete');

    // Bind on submit to form
    projectAppDeleteForm.on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = {
            client_id: window.EC5.projectApps.currentClientId
        };

        // Get action url
        var url = $(this).attr('action');
        window.EC5.overlay.fadeIn();
        window.EC5.projectApps.post(url, formData, function () {
            // Enable create app button
            $('#create-app').prop('disabled', false);
            // Close modal
            $('#modal-app-delete').modal('hide');
            window.EC5.toast.showSuccess('App deleted.');
            setTimeout(function () {
                window.EC5.overlay.fadeOut();
            }, 500);
        });

    });

    var projectAppRevokeForm = $('#ec5-form-project-app-revoke');

    // Bind on submit to form
    projectAppRevokeForm.on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = {
            client_id: window.EC5.projectApps.currentClientId
        };

        // Get action url
        var url = $(this).attr('action');
        window.EC5.overlay.fadeIn();
        window.EC5.projectApps.post(url, formData, function () {
            // Close modal
            $('#modal-app-delete').modal('hide');
            window.EC5.toast.showSuccess('Access Token revoked.');
            setTimeout(function () {
                window.EC5.overlay.fadeOut();
            }, 500);
        });
    });
});
