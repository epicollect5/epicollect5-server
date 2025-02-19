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
