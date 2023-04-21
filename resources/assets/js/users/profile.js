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
    var deleteAccountModal = pageLogin.find('#modal__account-deletion');

    deleteAccountBtn.on('click', function (e) {
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
            }
            else {
                window.EC5.toast.showError('Somethng went wrong');
            }
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

        AppleID.auth.signIn();

        // try {
        //     window.EC5.overlay.fadeIn();
        //
        //     AppleID.auth.signIn().then(function (appleResponse) {
        //         //check if response is legit (csrf token)
        //         if(appleResponse.authorization.state === state) {
        //
        //             //post data to apple endpoint (x-csrf is included in header)
        //             $.ajax({
        //                 url: redirectURI,
        //                 type: 'POST',
        //                // contentType: 'application/json',
        //                 dataType: 'json',
        //                 data: appleResponse,
        //                 success: function(ec5Response){
        //                     //hide any overlay or redirect?
        //                     if(ec5Response.data.authorized) {
        //                         window.location.href =   window.EC5.SITE_URL + '/myprojects';
        //                     } else {
        //                         window.EC5.toast.showError('User not authorized');
        //                         window.EC5.overlay.fadeOut();
        //                     }
        //                 },
        //                 error: function(error){
        //                     window.EC5.overlay.fadeOut();
        //                     window.EC5.toast.showError(error.errors[0].title);
        //                 }
        //             });
        //         }
        //         else {
        //             //state does not match, bail out!
        //             window.EC5.overlay.fadeOut();
        //             window.EC5.toast.showError('Invalid state');
        //         }
        //     }, function (error) {
        //         //it gets here when the Apple modal is dismissed, do not show error if not sent
        //         window.EC5.overlay.fadeOut();
        //         if(error.error) {
        //             window.EC5.toast.showError(error.error);
        //         }
        //     })
        // } catch (error) {
        //     window.EC5.overlay.fadeOut();
        //     if(error.error) {
        //         window.EC5.toast.showError(error.error);
        //     }
        // }
    });
});
