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

    var $counterMediaWrapper = $('.counter-media');
    var $counterQuotaWrapper = $('.counter-quota');
    var $panelQuotaWrapper = $('.panel-quota');
    var projectSlug = $counterMediaWrapper.data('project-slug');
    var refreshMediaOverviewBtn = $('.btn-refresh-media-overview');
    refreshMediaOverviewBtn.on('click', function () {
        updateMediaCountersAPI();
    });

    updateMediaCountersDB();

    function updateMediaCountersDB() {
        //get cached media stats from database
        var photoFiles = $counterMediaWrapper.find('.count-photo').data('photo-files');
        var audioFiles = $counterMediaWrapper.find('.count-audio').data('audio-files');
        var videoFiles = $counterMediaWrapper.find('.count-video').data('video-files');
        var photoBytes = $counterMediaWrapper.find('.size-photo').data('photo-bytes');
        var audioBytes = $counterMediaWrapper.find('.size-audio').data('audio-bytes');
        var videoBytes = $counterMediaWrapper.find('.size-video').data('video-bytes');
        var totalBytes = $counterMediaWrapper.find('.size-total').data('total-bytes');
        var totalFiles = $counterMediaWrapper.find('.count-total').data('total-files');
        var photoPct, audioPct, videoPct;
        if (totalBytes === 0) {
            photoPct = audioPct = videoPct = 0;
        } else {
            photoPct = (photoBytes / totalBytes * 100).toFixed(1);
            audioPct = (audioBytes / totalBytes * 100).toFixed(1);
            videoPct = (videoBytes / totalBytes * 100).toFixed(1);
        }

        $counterMediaWrapper.find('.progress-bar[data-type="photo"]')
            .css('width', photoPct + '%');

        $counterMediaWrapper.find('.progress-bar[data-type="audio"]')
            .css('width', audioPct + '%');

        if (totalBytes > 0) {
            videoPct = (100 - parseFloat(photoPct) - parseFloat(audioPct)).toFixed(1);
        }
        $counterMediaWrapper.find('.progress-bar[data-type="video"]')
            .css('width', videoPct + '%');

        // Table counts
        $counterMediaWrapper.find('.count-photo').text(photoFiles);
        $counterMediaWrapper.find('.count-audio').text(audioFiles);
        $counterMediaWrapper.find('.count-video').text(videoFiles);
        $counterMediaWrapper.find('.count-total').text(totalFiles);

        // Table sizes
        $counterMediaWrapper.find('.size-photo').text(window.EC5.common.formatBytes(photoBytes, 2));
        $counterMediaWrapper.find('.size-audio').text(window.EC5.common.formatBytes(audioBytes, 2));
        $counterMediaWrapper.find('.size-video').text(window.EC5.common.formatBytes(videoBytes, 2));
        $counterMediaWrapper.find('.size-total').text(window.EC5.common.formatBytes(totalBytes, 2));


        //Update table text percentage
        $counterMediaWrapper.find('.ratio-photo').text(photoPct + '%');
        $counterMediaWrapper.find('.ratio-audio').text(audioPct + '%');
        $counterMediaWrapper.find('.ratio-video').text(videoPct + '%');
        if (totalBytes === 0) {
            $counterMediaWrapper.find('.ratio-total').text('0%');
        } else {
            $counterMediaWrapper.find('.ratio-total').text('100%');
        }

        // hide loader, show stats
        $counterMediaWrapper.find('.loader').fadeOut(function () {
            $counterMediaWrapper.find('.media-stats').removeClass('hidden').fadeIn();
        });
        $counterQuotaWrapper.find('.loader').fadeOut(function () {
            $counterQuotaWrapper.find('.quota-stats').removeClass('hidden').fadeIn();
        });

    }

    // AJAX call using EC5 conventions (jQuery)
    function updateMediaCountersAPI() {
        window.EC5.overlay.fadeIn();
        /* ---------- Media counter ---------- */
        $counterMediaWrapper.find('.media-stats')
            .addClass('hidden')      // hide instantly
            .hide();                 // (optional) force display:none

        $counterMediaWrapper.find('.loader')
            .fadeIn();

        /* ---------- Quota counter ---------- */
        $counterQuotaWrapper.find('.quota-stats')
            .addClass('hidden')
            .hide();

        $counterQuotaWrapper.find('.loader')
            .fadeIn();
        $.get(window.EC5.SITE_URL + '/api/internal/counters/media/' + projectSlug)
            .done(function (response) {
                var counters = response.data.counters;
                var sizes = response.data.sizes;

                var totalBytes = sizes.total_bytes;
                var photoPct, audioPct, videoPct;
                if (totalBytes === 0) {
                    photoPct = audioPct = videoPct = 0;
                } else {
                    photoPct = (sizes.photo_bytes / totalBytes * 100).toFixed(1);
                    audioPct = (sizes.audio_bytes / totalBytes * 100).toFixed(1);
                    videoPct = (sizes.video_bytes / totalBytes * 100).toFixed(1);
                }

                $counterMediaWrapper.find('.progress-bar[data-type="photo"]')
                    .css('width', photoPct + '%');

                $counterMediaWrapper.find('.progress-bar[data-type="audio"]')
                    .css('width', audioPct + '%');

                if (totalBytes > 0) {
                    videoPct = (100 - parseFloat(photoPct) - parseFloat(audioPct)).toFixed(1);
                }
                $counterMediaWrapper.find('.progress-bar[data-type="video"]')
                    .css('width', videoPct + '%');

                // Table counts
                $counterMediaWrapper.find('.count-photo').text(counters.photo);
                $counterMediaWrapper.find('.count-audio').text(counters.audio);
                $counterMediaWrapper.find('.count-video').text(counters.video);
                $counterMediaWrapper.find('.count-total').text(counters.total);

                // Table sizes
                $counterMediaWrapper.find('.size-photo').text(window.EC5.common.formatBytes(sizes.photo_bytes, 2));
                $counterMediaWrapper.find('.size-audio').text(window.EC5.common.formatBytes(sizes.audio_bytes, 2));
                $counterMediaWrapper.find('.size-video').text(window.EC5.common.formatBytes(sizes.video_bytes, 2));
                $counterMediaWrapper.find('.size-total').text(window.EC5.common.formatBytes(sizes.total_bytes, 2));


                //Update table text percentage
                $counterMediaWrapper.find('.ratio-photo').text(photoPct + '%');
                $counterMediaWrapper.find('.ratio-audio').text(audioPct + '%');
                $counterMediaWrapper.find('.ratio-video').text(videoPct + '%');
                if (totalBytes === 0) {
                    $counterMediaWrapper.find('.ratio-total').text('0%');
                } else {
                    $counterMediaWrapper.find('.ratio-total').text('100%');
                }
                //update last updated at
                $panelQuotaWrapper.find('.bytes-updated-at span').text(response.data.updated_at_human_readable);
            })
            .fail(function () {
                $counterMediaWrapper.find('.loader').text('Error loading data');
                $counterQuotaWrapper.find('.loader').text('Error loading data');
            }).always(function () {
            // hide loader, show stats
            $counterMediaWrapper.find('.loader').fadeOut(function () {
                $counterMediaWrapper.find('.media-stats').removeClass('hidden').fadeIn();
            });
            $counterQuotaWrapper.find('.loader').fadeOut(function () {
                $counterQuotaWrapper.find('.quota-stats').removeClass('hidden').fadeIn();
            });

            window.EC5.overlay.fadeOut();
        });
    }
});


