'use strict';
window.EC5 = window.EC5 || {};
window.EC5.projectDetails = window.EC5.projectDetails || {};

(function projectDetails(module) {

    module.statusPairs = {
        trashed: ['restore', 'delete'],
        locked: ['unlock'],
        active: ['trashed', 'locked']
    };

    module.currentSettingsValue = function (which) {
        return window.EC5.projectDetails.parameters[which] || '';
    };

    module.updateSettings = function (which) {

        var onValue = module.currentSettingsValue(which);
        var settings_elements = $('.settings-' + which);

        if (which === 'status') {

            var showExtra = (module.statusPairs[onValue]) ? module.statusPairs[onValue] : [];

            settings_elements.each(function (idx, item) {

                if ($(item).data('value') === onValue) {
                    $(this).addClass('btn-action');
                } else {
                    $(this).removeClass('btn-action');
                }

                if (showExtra.indexOf($(item).data('value')) !== -1 || $(item).data('value') === onValue) {
                    $(this).removeClass('ec5-hide-block');
                } else {
                    $(this).addClass('ec5-hide-block');
                }
            });
        }

        settings_elements.each(function (idx, item) {
            if ($(item).data('value') === onValue) {
                $(this).addClass('btn-action');
            } else {
                $(this).removeClass('btn-action');
            }
        });
    };

    module.update = function (action, setTo) {

        var url = window.EC5.SITE_URL + '/myprojects/' + window.EC5.projectDetails.parameters.slug + '/settings/' + action;
        var data = {};

        data[action] = setTo;

        $.when(window.EC5.projectUtils.postRequest(url, data))
            .done(function (response) {
                try {
                    $.each(response.data, function (key, value) {
                        window.EC5.projectDetails.parameters[key] = value;
                    });
                    module.updateSettings(action);
                    window.EC5.toast.showSuccess('Setting updated.');
                } catch (e) {
                    window.EC5.projectUtils.showErrors(e);
                }
            })
            .fail(function (e) {
                window.EC5.overlay.fadeOut();
                //show errors to user
                window.EC5.projectUtils.showErrors(e);
            }).always(function () {
            window.EC5.overlay.fadeOut();
        });
    };

})(window.EC5.projectDetails);

$(document).ready(function () {

    $('[data-toggle="tooltip"]').tooltip();

    var project_details = $('.js-project-details');
    /***************************************************************************/
    //custom file upload button behaviour
    // We can attach the `fileselect` event to all file inputs on the page
    $(document).on('change', ':file', function () {
        var input = $(this);
        var numFiles = input.get(0).files ? input.get(0).files.length : 1;
        var label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
        input.trigger('fileselect', [numFiles, label]);
    });

    $(':file').on('fileselect', function (event, numFiles, label) {
        var input = $(this).parents('.input-group').find(':text'),
            log = numFiles > 1 ? numFiles + ' files selected' : label;
        if (input.length) {
            input.val('    ' + log);
        }
    });
    /***************************************************************************/

    window.EC5.projectDetails.parameters = {
        status: project_details.attr('data-js-status'),
        access: project_details.attr('data-js-access'),
        visibility: project_details.attr('data-js-visibility'),
        logo_url: project_details.attr('data-js-logo_url'),
        category: project_details.attr('data-js-category'),
        slug: project_details.attr('data-js-slug')
    };

    $('.btn-settings-submit').on('click', function () {

        var ele = $(this);
        var action = ele.attr('data-setting-type');
        var setTo = ele.attr('data-value');

        if (setTo !== window.EC5.projectDetails.currentSettingsValue(action)) {
            window.EC5.overlay.fadeIn();
            window.EC5.projectDetails.update(action, setTo);
        }

    }); //end my button

    $(['access', 'status', 'visibility']).each(function (i, action) {
        window.EC5.projectDetails.updateSettings(action);
    });

    $('#project-category').on('change', function (e) {
        var action = 'category';
        var setTo = $(this).val();

        if (setTo !== window.EC5.projectDetails.currentSettingsValue(action)) {
            window.EC5.overlay.fadeIn();
            window.EC5.projectDetails.update(action, setTo);
        }

    });

    $('.project-details__edit').on('click', function () {

        var panel = $(this).parents('.panel');

        window.EC5.overlay.fadeIn();

        //hide current panel
        panel.hide();
        //show the other panel
        switch (panel.attr('id')) {
            case 'details-view':
                //show edit view
                $('#details-edit').fadeIn(function () {
                    window.EC5.overlay.fadeOut();
                });
                break;
            case 'details-edit':
                //show details view
                $('#details-view').fadeIn(function () {
                    window.EC5.overlay.fadeOut();
                });
                break;
        }
    });

    $('.project-homepage-url .copy-btn').on('click', function () {
        var self = $(this);
        console.log(self.parent().find('a').text());
        navigator.clipboard.writeText(self.parent().find('a').text()).then(function () {
            self.tooltip('show');
            window.setTimeout(function () {
                self.tooltip('hide');
            }, 1500);
        }, function () {
            //do nothing
        });
    });

    $('.deeplink-copy-btn').on('click', function () {
        var self = $(this);
        var url = self.data('url')
        console.log(url);
        navigator.clipboard.writeText(url).then(function () {
            self.find('i').tooltip('show');
            window.setTimeout(function () {
                self.find('i').tooltip('hide');
            }, 1500);
        }, function () {
            //do nothing
        });
    });
});


