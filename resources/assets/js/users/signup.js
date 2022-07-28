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
