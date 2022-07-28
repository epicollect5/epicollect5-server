'use strict';
window.EC5 = window.EC5 || {};
window.EC5.editProjectSettings = window.EC5.editProjectSettings || {};

/**
 * project details page with settings
 */

(function _editProjectSettings(module) {

    module.statusPairs = {
        trashed: ['restore', 'delete'],
        locked: ['unlock'],
        active: ['trashed', 'locked']
    };

    module.toggleEditPanel = function (show, hide) {
        document.getElementById(show).style.display = 'block';
        document.getElementById(hide).style.display = 'none';
    };

    module.currentSettingValue = function (which) {
        return window.EC5.editProjectSettings.projectdetails[which] || '';
    };

    module.updateSettings = function (which) {

        var onValue = module.currentSettingValue(which);
        var settings_elements = $('.settings-' + which);

        if (which === 'status') {

            var showExtra = (module.statusPairs[onValue]) ? module.statusPairs[onValue] : [];

            settings_elements.each(function (idx, item) {

                if ($(item).data('value') === onValue) {
                    $(this).addClass('btn-action');
                }
                else {
                    $(this).removeClass('btn-action');
                }

                if (showExtra.indexOf($(item).data('value')) !== -1 || $(item).data('value') === onValue) {
                    $(this).removeClass('ec5-hide-block');
                } else {
                    $(this).addClass('ec5-hide-block');
                }
            });

            return;
        }

        settings_elements.each(function (idx, item) {
            if ($(item).data('value') === onValue) {
                $(this).addClass('btn-action');
            }
            else {
                $(this).removeClass('btn-action');
            }
        });
    };

    module.postForm = function (action, setTo, url, postData) {

        $.when(module.postJSON(url, postData))
            .done(function (data) {
                try {
                    module.setAjaxResponse(data.data);
                    module.updateSettings(action);
                    window.EC5.toast.showSuccess('Updated. ');
                } catch (e) {
                    window.EC5.toast.showError('There has been an error !');
                }
            })
            .fail(function (e) {
                window.EC5.toast.showError('There has been an error !');
            });
    };

    module.setAjaxResponse = function (data) {
        window.EC5.editProjectSettings.projectdetails = data;
    };

    module.postJSON = function (url, data) {

        console.log(url, JSON.stringify(data));

        return $.ajax({
            type: 'POST',
            url: url,
            data: data,
            dataType: 'json'
            // contentType: "application/json"
        });
    };
})(window.EC5.editProjectSettings);


