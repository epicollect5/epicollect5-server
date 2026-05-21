'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageLogin = $('.page-login');


    //do not do anything if not on the mapping page
    if (pageLogin.length === 0) {
        return false;
    }

    var appleLoginBtn = pageLogin.find('.btn-login-apple');

//check if Turnstile is enabled
    var turnstileContainer = document.getElementById('cf-turnstile');
    var isMobile = window.innerWidth < 600;
    var turnstileToken = null;
    if (turnstileContainer) {
        var siteKey = turnstileContainer.getAttribute('data-sitekey');

        // Wait for Turnstile script to load, then render
        var checkTurnstileTimeout = setTimeout(function () {
            clearInterval(checkTurnstile);
            window.EC5.toast.showError('Cloudflare Turnstile failed to load. Please refresh the page.');
        }, 10000);
        var checkTurnstile = setInterval(function () {
            if (typeof window.turnstile !== 'undefined') {
                clearInterval(checkTurnstile);
                clearTimeout(checkTurnstileTimeout);
                window.turnstile.render(turnstileContainer, {
                    sitekey: siteKey,
                    size: isMobile ? 'compact' : 'normal',
                    callback: function (token) {
                        turnstileToken = token;
                        var form = pageLogin.find('form#page-login__passwordless');
                        form.find('input[name="cf-turnstile-response"]').remove();
                        $('<input>', {
                            type: 'hidden',
                            name: 'cf-turnstile-response',
                            value: token
                        }).prependTo(form);
                    },
                    'error-callback': function () {
                        window.EC5.toast.showError('Cloudflare Turnstile error');
                    }
                });
            }
        }, 100);

        var timeout;
        var attemptSubmission = function (e) {
            var form = pageLogin.find('form#page-login__passwordless');

            if (typeof form.get(0).reportValidity === "function") {
                if (!form.get(0).reportValidity()) {
                    return;
                }
            }

            if (!turnstileToken) {
                window.EC5.toast.showError('Please complete the Turnstile challenge');
                return;
            }

            form.submit();
        };

        $('#passwordless').on('click', function (e) {
            e.preventDefault();
            window.clearTimeout(timeout);
            timeout = window.setTimeout(function () {
                attemptSubmission(e);
            }, 1000);
        });
    }

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
