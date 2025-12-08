'use strict';
window.EC5 = window.EC5 || {};
window.EC5.toast = window.EC5.toast || {};

(function toast(module) {

    var options = {
        closeButton: true,
        positionClass: 'toast-top-center',
        preventDuplicates: true,
        onclick: null,
        showDuration: 500,
        hideDuration: 500,
        extendedTimeOut: 0,
        showMethod: 'fadeIn',
        hideMethod: 'fadeOut',
        escapeHtml: true
    };

    module.showSuccess = function (message) {
        options.timeOut = 3000;
        window.toastr.options = options;
        window.toastr.success(message);
    };

    module.showWarning = function (message) {
        options.timeOut = 0;
        window.toastr.options = options;
        window.toastr.warning(message);
    };

    module.showError = function (message) {
        options.timeOut = 0;
        window.toastr.options = options;
        window.toastr.error(message);
    };

    module.clear = function () {
        window.toastr.clear();
    };

})(window.EC5.toast);
