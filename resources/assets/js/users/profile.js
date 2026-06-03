'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageLogin = $('.page-profile');

    //do not do anything if not on the mapping page
    if (pageLogin.length === 0) {
        return false;
    }

    var appleConnectBtn = pageLogin.find('.btn-connect-apple');
    var deleteAccountBtn = pageLogin.find('.btn-confirm-account-deletion');
    var deleteAccountCheckboxConfirm = pageLogin.find('.checkbox-confirm-account-deletion');
    var deleteAccountModal = pageLogin.find('#modal__account-deletion');

    deleteAccountModal.on('show.bs.modal', function () {
        deleteAccountBtn.attr('disabled', true);
        deleteAccountCheckboxConfirm.prop('checked', false);
    });

    deleteAccountCheckboxConfirm.on('change', function () {
        if ($(this).is(':checked')) {
            deleteAccountBtn.attr('disabled', false);
        } else {
            deleteAccountBtn.attr('disabled', true);
        }
    });

    deleteAccountBtn.on('click', function (e) {
        if (!deleteAccountCheckboxConfirm.prop('checked')) {
            return false;
        }

        window.EC5.overlay.fadeIn();
        var url = window.EC5.SITE_URL + '/api/internal/profile/account-deletion-request';
        //send request to endpoint for email account deletion
        $.ajax({
            url: url,
            type: 'POST'
        }).done(function (response) {
            console.log(response);
            if (response.data.accepted === true) {
                window.EC5.toast.showSuccess('Account deletion request sent.');
                return;
            }
            if (response.data.deleted === true) {
                window.EC5.toast.showSuccess('Account deleted.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 100);
                return;
            }
            window.EC5.toast.showError('Something went wrong');
        }).fail(function (error) {
            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {
                    for (var i = 0; i < error.responseJSON.errors.length; i++) {
                        window.EC5.toast.showError(error.responseJSON.errors[i].title);
                    }
                }
            }
        }).always(function () {
            window.EC5.overlay.fadeOut();
            deleteAccountModal.modal('hide');
        });
    });

    appleConnectBtn.on('click', function (e) {

        var clientID = $('meta[name=appleid-signin-client-id]').attr('content');
        var scope = $('meta[name=appleid-signin-scope]').attr('content');
        var redirectURI = $('meta[name=appleid-signin-redirect-uri]').attr('content');
        var nonce = $('meta[name=appleid-signin-nonce]').attr('content');

        //get parameters from meta tags
        AppleID.auth.init({
            clientId: clientID,
            scope: scope,
            redirectURI: redirectURI,
            nonce: nonce,
            usePopup: false
        });
        
        //start authentication process
        AppleID.auth.signIn();
    });
});
