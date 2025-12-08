'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {

    function _isValidEmailAddress(emailAddress) {

        var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
        return pattern.test(emailAddress);
    }

    module.importUsersByEmail = function (params) {

        var validEmailAddresses = [];
        var selectedHeaderIndex = params.selectedHeaderIndex;
        var importedJson = params.importedJson;
        var headers = importedJson.meta.fields;
        var doesFirstRowContainsHeaders = params.doesFirstRowContainsHeaders;
        var selectedUserRole = params.selectedUserRole;
        var postURL = params.postURL;
        var deferred = new $.Deferred();
        var config = window.EC5.project_users.config;

        //reset invalid email addresses
        config.invalidEmailAddresses = [];

        //if no column is selected abort
        if (selectedHeaderIndex === null) {
            return false;
        }

        //headers on first row or not?
        if (!doesFirstRowContainsHeaders) {

            var first_row = {};
            first_row[importedJson.meta.fields[selectedHeaderIndex]] = importedJson.meta.fields[selectedHeaderIndex];
            //csv file does not have any headers, prepend meta.fields (which is the headers)
            importedJson.data.unshift(first_row);
        }

        $(importedJson.data).each(function (index, item) {
            var userEmail = item[headers[selectedHeaderIndex]];

            //validate emails addresses front end and reject both empty and invalid
            if (userEmail.trim() !== '') {
                if (_isValidEmailAddress(userEmail)) {
                    validEmailAddresses.push(userEmail)
                } else {
                    config.invalidEmailAddresses.push(userEmail);
                }
            }
        });

        var data = {
            role: selectedUserRole,
            emails: $.unique(validEmailAddresses).slice(0, 100)//duplicates get removed
        };

        $.ajax({
            url: postURL,
            type: 'POST',
            dataType: 'json',
            data: data
        }).done(function (response) {
            deferred.resolve(response);
        }).fail(function (error) {
            deferred.reject(error);
        });

        return deferred.promise();
    }

}(window.EC5.project_users));
