'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageForgot = $('.page-forgot');

    //do not do anything if not on the mapping page
    if (pageForgot.length === 0) {
        return false;
    }

    //get client site ID
    var captchaContainer = $('.gcaptcha');
    var siteId = captchaContainer.text();

    captchaContainer.remove();

    grecaptcha.ready(function () {

        var timeout;
        // create debounced function
        var attemptSubmission = function (e) {
            var form = pageForgot.find('form#page-forgot__form');

            e.preventDefault();

            function _execute() {
                //show overlay

                window.EC5.overlay.fadeIn();
                //get grecaptcha token
                grecaptcha.execute(siteId, { action: 'forgot' }).then(function (token) {

                        //embed token and send it to server for verification
                        form.prepend('<input type="hidden" name="g-recaptcha-response" value="' + token + '">')
                            .submit();
                        //hide overlay
                        window.setTimeout(window.EC5.overlay.fadeOut(), 10000);
                    }
                );
            }

            //use html5 validation first (if supported)
            if (typeof form.get(0).reportValidity === "function") {
                if (form.get(0).reportValidity()) {
                    _execute()
                }
            }
            else {
                _execute()
            }
            window.clearTimeout(timeout);
        };

        $('#send').on('click', function (e) {
            if (!timeout) {
                timeout = window.setTimeout(attemptSubmission(e), 1000);
            }
        });
    });
});

'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageLogin = $('.page-login');

    //do not do anything if not on the mapping page
    if (pageLogin.length === 0) {
        return false;
    }

    var appleLoginBtn = pageLogin.find('.btn-login-apple');

    //check if Google Recaptcha is enabled
    var captchaContainer = $('.gcaptcha');
    if (captchaContainer.length > 0) {
        //get client site ID
        var siteId = captchaContainer.text().trim();

        captchaContainer.remove();


        window.grecaptcha.ready(function () {

            var timeout;
            // create debounced function
            var attemptSubmission = function (e) {
                var form = pageLogin.find('form#page-login__passwordless');

                e.preventDefault();

                function _execute() {
                    //show overlay

                    window.EC5.overlay.fadeIn();
                    //get grecaptcha token
                    try {
                        window.grecaptcha.execute(siteId, {action: 'passwordless'}).then(function (token) {
                                //embed token and send it to server for verification
                                form.prepend('<input type="hidden" name="g-recaptcha-response" value="' + token + '">')
                                    .submit();
                                //hide overlay
                                window.setTimeout(window.EC5.overlay.fadeOut(), 10000);
                            }
                        );
                    } catch (e) {
                        window.setTimeout(window.EC5.overlay.fadeOut(), 500);
                        window.EC5.toast.showError('Google ReCaptcha ' + e);
                    }
                }

                //use html5 validation first (if supported)
                if (typeof form.get(0).reportValidity === "function") {
                    if (form.get(0).reportValidity()) {
                        _execute();
                    }
                } else {
                    _execute();
                }
            };

            $('#passwordless').on('click', function (e) {
                window.clearTimeout(timeout);
                timeout = window.setTimeout(attemptSubmission(e), 1000);
            });
        });

        //handle show password checkbox
        pageLogin.find('.show-password-control').on('click', function () {

            if ($(this).prop('checked')) {
                pageLogin.find('input.password-input').each(function () {
                    $(this).attr('type', 'text');
                });
            } else {
                pageLogin.find('input.password-input').each(function (iput) {
                    $(this).attr('type', 'password');
                });
            }
        });
    }

    appleLoginBtn.on('click', function (e) {

        var clientID = $('meta[name=appleid-signin-client-id]').attr('content');
        var scope = $('meta[name=appleid-signin-scope]').attr('content');
        var redirectURI = $('meta[name=appleid-signin-redirect-uri]').attr('content');
        var nonce = $('meta[name=appleid-signin-nonce]').attr('content');

        //get parameters from meta tags
        window.AppleID.auth.init({
            clientId: clientID,
            scope: scope,
            redirectURI: redirectURI,
            nonce: nonce,
            usePopup: false
        });

        window.AppleID.auth.signIn();
    });
});

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

        AppleID.auth.signIn();

        try {
            window.EC5.overlay.fadeIn();

            AppleID.auth.signIn().then(function (appleResponse) {
                //check if response is legit (csrf token)
                if (appleResponse.authorization.state === state) {

                    //post data to apple endpoint (x-csrf is included in header)
                    $.ajax({
                        url: redirectURI,
                        type: 'POST',
                        // contentType: 'application/json',
                        dataType: 'json',
                        data: appleResponse,
                        success: function (ec5Response) {
                            //hide any overlay or redirect?
                            if (ec5Response.data.authorized) {
                                window.location.href = window.EC5.SITE_URL + '/myprojects';
                            } else {
                                window.EC5.toast.showError('User not authorized');
                                window.EC5.overlay.fadeOut();
                            }
                        },
                        error: function (error) {
                            window.EC5.overlay.fadeOut();
                            window.EC5.toast.showError(error.errors[0].title);
                        }
                    });
                } else {
                    //state does not match, bail out!
                    window.EC5.overlay.fadeOut();
                    window.EC5.toast.showError('Invalid state');
                }
            }, function (error) {
                //it gets here when the Apple modal is dismissed, do not show error if not sent
                window.EC5.overlay.fadeOut();
                if (error.error) {
                    window.EC5.toast.showError(error.error);
                }
            })
        } catch (error) {
            window.EC5.overlay.fadeOut();
            if (error.error) {
                window.EC5.toast.showError(error.error);
            }
        }
    });
});

'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageReset = $('.page-staff-reset');

    //do not do anything if not on the mapping page
    if (pageReset.length === 0) {
        return false;
    }

    //handle show password checkbox
    pageReset.find('.show-password-control').on('click', function () {

        if ($(this).prop('checked')) {
            pageReset.find('input.password-input').each(function () {
                $(this).attr('type', 'text');
            });
        }
        else {
            pageReset.find('input.password-input').each(function (iput) {
                $(this).attr('type', 'password');
            });
        }
    })
});

'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageSignup = $('.page-signup');

    //do not do anything if not on the mapping page
    if (pageSignup.length === 0) {
        return false;
    }

    //get client site ID
    var captchaContainer = $('.gcaptcha');
    var siteId = captchaContainer.text();

    captchaContainer.remove();

    grecaptcha.ready(function () {

        var timeout;
        // create debounced function
        var attemptSubmission = function (e) {
            var form = pageSignup.find('form#page-signup__form');

            e.preventDefault();

            function _execute() {
                //show overlay

                window.EC5.overlay.fadeIn();
                //get grecaptcha token
                grecaptcha.execute(siteId, { action: 'signup' }).then(function (token) {
                        //embed token and send it to server for verification
                        form.prepend('<input type="hidden" name="g-recaptcha-response" value="' + token + '">')
                            .submit();
                        //hide overlay
                        window.setTimeout(window.EC5.overlay.fadeOut(), 10000);
                    }
                );
            }

            //use html5 validation first (if supported)
            if (typeof form.get(0).reportValidity === "function") {
                if (form.get(0).reportValidity()) {
                    _execute()
                }
            }
            else {
                _execute()
            }
        };

        $('#signup').on('click', function (e) {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(attemptSubmission(e), 1000);
        });
    });

    //handle show password checkbox
    pageSignup.find('.show-password-control').on('click', function () {

        if ($(this).prop('checked')) {
            pageSignup.find('input.password-input').each(function () {
                $(this).attr('type', 'text');
            });
        }
        else {
            pageSignup.find('input.password-input').each(function (iput) {
                $(this).attr('type', 'password');
            });
        }
    })
});
