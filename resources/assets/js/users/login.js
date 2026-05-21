'use strict';
window.EC5 = window.EC5 || {};

// Global Turnstile state
window.EC5.turnstileToken = null;
window.EC5.turnstileReady = false;

/**
 * Global callback invoked by Cloudflare Turnstile when the script finishes loading.
 * Cloudflare will call this automatically based on the onload parameter in the script URL.
 */
window.onTurnstileLoad = function () {
    window.EC5.turnstileReady = true;
    // Render widget immediately if DOM is ready, otherwise wait for DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderTurnstileWidget);
    } else {
        renderTurnstileWidget();
    }
};

/**
 * Render the Turnstile widget on the page if the container exists.
 */
function renderTurnstileWidget() {
    var turnstileContainer = document.getElementById('cf-turnstile');
    if (!turnstileContainer) {
        return;
    }

    var siteKey = turnstileContainer.getAttribute('data-sitekey');
    var isMobile = window.innerWidth < 600;

    window.turnstile.render(turnstileContainer, {
        sitekey: siteKey,
        size: isMobile ? 'compact' : 'normal',
        callback: function (token) {
            window.EC5.turnstileToken = token;
            var form = $('form#page-login__passwordless');
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

$(document).ready(function () {

    var pageLogin = $('.page-login');

    //do not do anything if not on the login page
    if (pageLogin.length === 0) {
        return false;
    }

    var appleLoginBtn = pageLogin.find('.btn-login-apple');
    var turnstileContainer = document.getElementById('cf-turnstile');

    // If Turnstile container exists but onload callback hasn't fired yet (edge case),
    // render it now. Normally onTurnstileLoad will fire first.
    if (turnstileContainer && window.EC5.turnstileReady) {
        renderTurnstileWidget();
    }

    // Handle passwordless form submission
    var timeout;
    var attemptSubmission = function (e) {
        var form = pageLogin.find('form#page-login__passwordless');

        if (typeof form.get(0).reportValidity === "function") {
            if (!form.get(0).reportValidity()) {
                return;
            }
        }

        // Only check for Turnstile token if Turnstile container exists
        if (turnstileContainer && !window.EC5.turnstileToken) {
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
