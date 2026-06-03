'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageLogin = $('.page-login');

    //do not do anything if not on the mapping page
    if (pageLogin.length === 0) {
        return false;
    }

    var appleLoginBtn = pageLogin.find('.btn-login-apple');
    var passwordlessBtn = $('#passwordless');

    //check if Google Recaptcha is enabled
    var captchaContainer = $('.gcaptcha');
    if (captchaContainer.length > 0) {
        //get client site ID
        var siteId = captchaContainer.text().trim();

        captchaContainer.remove();


        window.grecaptcha.ready(function () {

            var attemptSubmission = function (e) {
                window.EC5.overlay.fadeIn();
                var form = pageLogin.find('form#page-login__passwordless');

                function _execute() {

                    //get g-recaptcha token
                    try {
                        window.grecaptcha.execute(siteId, {action: 'passwordless'}).then(function (token) {
                                //embed token and send it to server for verification
                                $('input[name="g-recaptcha-response"]').val(token);
                                form.submit();
                            }
                        );
                        window.setTimeout(window.EC5.overlay.fadeOut(), 10000);
                    } catch (e) {
                        window.EC5.toast.showError('Google ReCaptcha ' + e);
                        window.setTimeout(window.EC5.overlay.fadeOut(), 500);
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

            passwordlessBtn.on('click', function (e) {
                e.preventDefault();
                attemptSubmission();
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
