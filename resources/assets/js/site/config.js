'use strict';
window.EC5 = window.EC5 || {};
window.EC5.overlay = window.EC5.overlay || {};

(function app(module) {

    /**
     * Show errors
     * @param error
     */
    module.showError = function (error) {

        if (error.responseJSON.errors) {
            // Show the errors
            if (error.responseJSON.errors.length > 0) {
                var i;
                for (i = 0; i < error.responseJSON.errors.length; i++) {
                    window.EC5.toast.showError(error.responseJSON.errors[i].title);
                }
            }
        }
    };
})(window.EC5);

$(document).ready(function () {
    /**
     * Get XSRF token from cookie
     * @returns {string}
     */
    var getXsrfToken = function () {
        var cookies = document.cookie.split(';');
        var token = '';

        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].split('=');
            if (cookie[0].trim() === 'XSRF-TOKEN') {
                token = decodeURIComponent(cookie[1]);
            }
        }
        return token;
    };

    $.ajaxSetup({
        headers: {
            'X-XSRF-TOKEN': getXsrfToken()
        }
    });
    window.EC5.overlay = $('.wait-overlay');

    //fade in project logo thumbnails all over the site (not my projects and project search, as it has got another way to render, from server generated markup)
    if ($('.page-my-projects').length === 0 && $('.page-search-projects').length === 0 && $('.page-formbuilder').length === 0) {
        $('.thumbnail, .intro-thumbnail, .project-home__logo-wrapper, .project-open__logo-wrapper').imagesLoaded().progress(function (instance, image) {
            if (image.isLoaded) {
                //fade in image and remove loader when it is done
                $(image.img).fadeTo(250, 1, function () {
                    //remove image loader
                    $(this).next('.loader').remove();
                });
            }
        });
    }
    //extend string function for truncating
    String.prototype.trunc = function (n) {
        return this.substr(0, n - 1) + (this.length >= n ? '...' : '');
    };

    //debounce helper

});

