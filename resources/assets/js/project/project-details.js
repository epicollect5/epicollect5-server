'use strict';
window.EC5 = window.EC5 || {};
window.EC5.projectDetails = window.EC5.projectDetails || {};

(function projectDetails(module) {

    module.statusPairs = {
        trashed: ['restore', 'delete'], locked: ['unlock'], active: ['trashed', 'locked']
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
                    window.EC5.toast.showSuccess('Settings updated.');
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

    //run only on project details page
    if ($('.page-project-details').length === 0) {
        return false;
    }

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
        slug: project_details.attr('data-js-slug'),
        app_link_visibility: project_details.attr('data-js-app_link_visibility')
    };

    //generate QR Code on project details page only
    if ($('.deeplink-btn-panel').length > 0) {
        var qrCodeWrapper = $('#qrcode');
        var qrcode = new window.QRCode('qrcode', {
            text: qrCodeWrapper.data('url'),
            width: 1024,
            height: 1024,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        //download QR code (the hidden one, size is bigger)
        $('#qrcode-download').on('click', function () {
            // Find the image inside the #qrcode div
            var image = qrCodeWrapper.find('img');
            // Copy that to the download link
            $(this).attr('href', image.attr('src'));
        })
    }

    $('.btn-settings-submit').on('click', function () {
        var ele = $(this);
        var action = ele.attr('data-setting-type');
        var setTo = ele.attr('data-value');

        if (setTo !== window.EC5.projectDetails.currentSettingsValue(action)) {
            window.EC5.overlay.fadeIn();
            window.EC5.projectDetails.update(action, setTo);
        }

    }); //end my button

    $(['access', 'status', 'visibility', 'app_link_visibility']).each(function (i, action) {
        window.EC5.projectDetails.updateSettings(action);
    });

    $('#project-category').on('change', function () {
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

    // Loop through all counter-media panels
    $('.counter-media').each(function () {
        var $wrapper = $(this);
        var projectSlug = $wrapper.data('project-slug');

        // AJAX call using EC5 conventions (jQuery)
        $.get(window.EC5.SITE_URL + '/api/internal/counters/media/' + projectSlug)
            .done(function (response) {
                var counters = response.data.counters;
                var sizes = response.data.sizes;

                // hide spinner, show stats
                $wrapper.find('.spinner').fadeOut(function () {
                    $wrapper.find('.media-stats').removeClass('hidden').fadeIn();
                });

                var total = counters.total || 1; // prevent divide by 0
                var photoPct = (counters.photo / total * 100).toFixed(1);
                var audioPct = (counters.audio / total * 100).toFixed(1);
                var videoPct = (counters.video / total * 100).toFixed(1);

                $wrapper.find('.progress-bar[data-type="photo"]')
                    .css('width', photoPct + '%')
                    .find('.percent').text('(' + photoPct + '%)');
                $wrapper.find('.progress-bar[data-type="audio"]')
                    .css('width', audioPct + '%')
                    .find('.percent').text('(' + audioPct + '%)');
                $wrapper.find('.progress-bar[data-type="video"]')
                    .css('width', videoPct + '%')
                    .find('.percent').text('(' + videoPct + '%)');

                // Table counts
                $wrapper.find('.count-photo').text(counters.photo);
                $wrapper.find('.count-audio').text(counters.audio);
                $wrapper.find('.count-video').text(counters.video);
                $wrapper.find('.count-total').text(counters.total);

                // Table sizes
                $wrapper.find('.size-photo').text(window.EC5.common.formatBytes(sizes.photo_bytes));
                $wrapper.find('.size-audio').text(window.EC5.common.formatBytes(sizes.audio_bytes));
                $wrapper.find('.size-video').text(window.EC5.common.formatBytes(sizes.video_bytes));
                $wrapper.find('.size-total').text(window.EC5.common.formatBytes(sizes.total_bytes));
            })
            .fail(function () {
                $wrapper.find('.spinner').text('Error loading data');
            });
    });
});


