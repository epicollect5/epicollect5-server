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
