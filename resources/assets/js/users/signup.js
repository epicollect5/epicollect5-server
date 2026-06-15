'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var pageSignup = $('.page-signup');

    //do not do anything if not on the mapping page
    if (pageSignup.length === 0) {
        return false;
    }

    //handle show password checkbox
    pageSignup.find('.show-password-control').on('click', function () {

        if ($(this).prop('checked')) {
            pageSignup.find('input.password-input').each(function () {
                $(this).attr('type', 'text');
            });
        } else {
            pageSignup.find('input.password-input').each(function (iput) {
                $(this).attr('type', 'password');
            });
        }
    })
});
