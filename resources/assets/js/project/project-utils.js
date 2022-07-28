'use strict';
window.EC5 = window.EC5 || {};

window.EC5.projectUtils = window.EC5.projectUtils || {};

(function projectUtils(module) {

    module.postRequest = function (url, data) {
        return $.ajax({
            url: url,
            contentType: 'application/vnd.api+json',
            data: JSON.stringify(data),
            dataType: 'json',
            method: 'POST',
            crossDomain: true
        });
    };

    module.showErrors = function (response) {

        var errorMessage = '';

        $(response.responseJSON.errors).each(function (index, error) {
            errorMessage += error.title + '<br/>';
        });
        window.EC5.toast.showError(errorMessage);
    };

}(window.EC5.projectUtils));

