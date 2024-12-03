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

        if (response.responseJSON) {
            $(response.responseJSON.errors).each(function (index, error) {
                //cannot use <br/> because HTML is escaped
                errorMessage += error.title + '\n';
            });
        } else {
            //cannot use <br/> because HTML is escaped
            errorMessage += 'Unknown error' + '\n';
        }
        window.EC5.toast.showError(errorMessage);
    };

    module.slugify = function (text) {
        // Convert the text to lowercase
        return text.toLowerCase().replace(/\s+/g, '-');
    }

}(window.EC5.projectUtils));

