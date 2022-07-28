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
