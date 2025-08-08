'use strict';
window.EC5 = window.EC5 || {};
window.EC5.projectApps = window.EC5.projectApps || {};

/**
 * Manage the server users
 */
(function projectApps(module) {

    // current client ID
    module.currentClientId = '';

    /**
     * Function for asynchronously retrieving the
     * list of users, based on any search/filter
     * criteria, with pagination
     *
     */
    module.getApps = function () {

        // Make ajax request to load users
        $.ajax({
            url: '',
            type: 'GET',
            dataType: 'json',
            data: {}
        }).done(function (data) {
            $('.project-apps-list').html(data);
        }).fail(window.EC5.showError);
    };

    /**
     * Make a post request then get apps on success
     *
     * @param url
     * @param formData
     * @param callBack
     */
    module.post = function (url, formData, callBack) {

        // Make ajax request to load project apps
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData
        }).done(function () {

            // If passed a callback, call
            if (callBack) {
                callBack();
            }

            module.getApps();
        }).fail(window.EC5.showError);

    };

})(window.EC5.projectApps);

$(document).ready(function () {

    var projectApps = $('.project-apps');

    // Bind on click to pagination links
    projectApps.on('click', '.pagination a', function (e) {

        e.preventDefault();
        window.EC5.projectApps.getApps();

    });


    var projectAppsForm = $('#ec5-form-project-apps');

    // Bind on submit to form
    projectAppsForm.on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = $(this).serialize();

        // Get action url
        var url = $(this).attr('action');
        window.EC5.overlay.fadeIn();
        window.EC5.projectApps.post(url, formData, function () {
            // Disable create app button
            $('#create-app').prop('disabled', true);
            // Close modal
            $('#modal-create-app').modal('hide');
            window.EC5.toast.showSuccess('New App added.');
            setTimeout(function () {
                window.EC5.overlay.fadeOut();
            }, 500);
        });

    });


    var projectAppList = $('.project-apps-list');

    projectAppList.on('click', '#delete-app', function (e) {

        e.preventDefault();
        // Hide revoke form
        $('#ec5-form-project-app-revoke').addClass('hidden');
        // Show delete form
        $('#ec5-form-project-app-delete').removeClass('hidden');
        // Get the current project client app id
        window.EC5.projectApps.currentClientId = $(this).data('clientId');
    });

    projectAppList.on('click', '#revoke-app', function (e) {

        e.preventDefault();
        // Hide delete form
        $('#ec5-form-project-app-delete').addClass('hidden');
        // Show revoke form
        $('#ec5-form-project-app-revoke').removeClass('hidden');
        // Get the current project client app id
        window.EC5.projectApps.currentClientId = $(this).data('clientId');

    });

    var projectAppDeleteForm = $('#ec5-form-project-app-delete');

    // Bind on submit to form
    projectAppDeleteForm.on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = {
            client_id: window.EC5.projectApps.currentClientId
        };

        // Get action url
        var url = $(this).attr('action');
        window.EC5.overlay.fadeIn();
        window.EC5.projectApps.post(url, formData, function () {
            // Enable create app button
            $('#create-app').prop('disabled', false);
            // Close modal
            $('#modal-app-delete').modal('hide');
            window.EC5.toast.showSuccess('App deleted.');
            setTimeout(function () {
                window.EC5.overlay.fadeOut();
            }, 500);
        });

    });

    var projectAppRevokeForm = $('#ec5-form-project-app-revoke');

    // Bind on submit to form
    projectAppRevokeForm.on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = {
            client_id: window.EC5.projectApps.currentClientId
        };

        // Get action url
        var url = $(this).attr('action');
        window.EC5.overlay.fadeIn();
        window.EC5.projectApps.post(url, formData, function () {
            // Close modal
            $('#modal-app-delete').modal('hide');
            window.EC5.toast.showSuccess('Access Token revoked.');
            setTimeout(function () {
                window.EC5.overlay.fadeOut();
            }, 500);
        });
    });
});

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.projectCreate = window.EC5.projectCreate || {};
(function (module) {

    module.doesProjectExist = function (url) {

        var form_group = $('.page-create-project').find('#project-name-form-group-create');

        $.when($.get(url)).done(function (response) {
            console.log(response.data);

            $('#project-loader').addClass('hidden');

            //is there already a project with that name?
            if (response.data.exists) {
                //we have a duplicate, show errors
                window.EC5.projectCreate.toggleGroupValidation(form_group, false, 'Project already exists');
            } else {
                //hide errors
                window.EC5.projectCreate.toggleGroupValidation(form_group, true);
            }
        })
            .fail(function () {
                form_group.addClass('has-error');
            }).always(function () {
            $('#project-loader').addClass('hidden');
        });

    };

    module.toggleGroupValidation = function (group, is_valid, message) {
        if (is_valid) {
            //hide errors
            group.removeClass('has-error');
            group.find('.form-control-feedback').not('#project-loader').addClass('hidden');
            group.find('.text-danger').addClass('hidden');
            group.find('.text-hint').removeClass('hidden');
        } else {
            //show errors
            group.addClass('has-error');
            group.find('.form-control-feedback').not('#project-loader').removeClass('hidden');
            group.find('.text-danger').removeClass('hidden').text(message);
            group.find('.text-hint').addClass('hidden');
        }
    };

})(window.EC5.projectCreate);

$(document).ready(function () {

    //do nothing if NOT on project create page
    if ($('.page-create-project').length === 0) {
        return;
    }

    var spinner = $('#project-loader');

    //check if a project name exists, on keyup with throttling
    $('.page-create-project #project-name-create').keyup(function (e) {

        var task;
        var form_group = $('.page-create-project #project-name-form-group-create');
        var input = $(this);
        var value = input.val();
        //allow only alphanumeric chars, space and underscore but not accented letters
        var regex = /^[a-zA-Z0-9_ ]*$/;

        //is project name valid?
        //is form name valid?
        if (regex.test(value) || value === '') {
            //project name valid, hide errors
            window.EC5.projectCreate.toggleGroupValidation(form_group, true);

            if (value.length >= 3) {
                var url = window.EC5.SITE_URL + '/api/internal/exists/' + value;
                spinner.removeClass('hidden');
                clearTimeout(task);
                task = setTimeout(function () {
                    window.EC5.projectCreate.doesProjectExist(url);
                }, 500);
            }
        } else {
            //form name invalid, show errors, hide spinner
            spinner.addClass('hidden');
            window.EC5.projectCreate.toggleGroupValidation(form_group, false, 'Please remove invalid chars');
        }
    });

//keyup validation on form name => allow only alphanumeric chars and '-', '_'
    $('.page-create-project #form-name').keyup(function (e) {

        var regex = /^[\w\-\s]+$/;
        var input = $(this);
        var value = input.val();
        var form_group = $('#form-name-form-group');

        //is form name valid?
        if (regex.test(value) || value === '') {
            //form name valid, hide errors
            window.EC5.projectCreate.toggleGroupValidation(form_group, true);

        } else {
            //form name invalid, show errors
            window.EC5.projectCreate.toggleGroupValidation(form_group, false, 'Please remove invalid chars');
        }
    });

//keyup validation on small description => minlength 15 and strip html tags
    $('.page-create-project #small-description-form-group > input').keyup(function (e) {

        var form_group = $('#small-description-form-group');
        var input = $(this);
        var value = input.val();

        if (value.length >= 15) {
            //small description valid, hide errors
            window.EC5.projectCreate.toggleGroupValidation(form_group, true);
        } else {
            //length too short
            window.EC5.projectCreate.toggleGroupValidation(form_group, false, 'Must be at least 15 chars long');
        }
    });

    //Show overlay and disable submit button (CREATE) when form is valid to avois double submissions
    $('.page-create-project .create-project-form').on('submit', function (e) {
        // Check if the form is valid
        if (!this.checkValidity()) {
            e.preventDefault();  // Stop form submission
            window.EC5.overlay.fadeOut();  // Hide the overlay
            return false;
        }

        // Show overlay if form is valid
        window.EC5.overlay.fadeIn();
        $(this).find('button[type="submit"]').prop('disabled', true);
    });

    //Show overlay and disable submit button (IMPORT) when form is valid to avois double submissions
    $('.page-create-project #project-name-form-group-import').on('submit', function (e) {
        // Check if the form is valid
        if (!this.checkValidity()) {
            e.preventDefault();  // Stop form submission
            window.EC5.overlay.fadeOut();  // Hide the overlay
            return false;
        }
        // Show overlay if form is valid
        window.EC5.overlay.fadeIn();
        $(this).find('button[type="submit"]').prop('disabled', true);
    });
});




/**@var {string} window.EC5.SITE_URL*/
/**@var {Object} response.data.counters*/
/**@var {Object} response.data.deleted*/

document.addEventListener('DOMContentLoaded', function () {

    var page = $('.page-entries-deletion');

    //enable only on page-entries-deletion
    if (page.length > 0) {
        var projectName = page.find('.project-name').text();
        var wrapper = $('.delete-entries-wrapper');
        var counterWrapperEntries = $('.progress-entries')
        var counterWrapperMedia = $('.progress-media');
        var modal = $('#modal-deletion');
        var backURL = $('.btn-cancel-deletion').attr('href');
        var chunkSizeEntries = page.data('chunk-size-entries');
        var projectSlug = window.EC5.projectUtils.slugify(projectName.trim());
        var endpointEntries = window.EC5.SITE_URL + '/api/internal/deletion/entries/' + projectSlug;
        var endpointMedia = window.EC5.SITE_URL + '/api/internal/deletion/media/' + projectSlug;

        wrapper.on('click', '.btn-delete-entries', function (e) {
            // Don't allow user to submit if the project
            // name they've typed is incorrect
            if (projectName.trim() !== $('#project-name').val().trim()) {
                e.preventDefault();
                window.EC5.projectUtils.showErrors('Project name incorrect. Please try again.');
            }


            var payload = {
                data: {
                    'project-name': projectName.trim()
                }
            }

            modal.modal({backdrop: 'static', keyboard: false}, 'show');
            // Add the event listener to the beforeunload event
            window.addEventListener('beforeunload', handleBeforeUnload);

            //get total media count
            $.get(window.EC5.SITE_URL + '/api/internal/counters/media/' + projectSlug, function (response) {
                totalMedia = response.data.counters.total;
                counterWrapperMedia.find('.spinner')
                    .addClass('hidden') // hidden class will use visibility: hidden;
                    .fadeOut(function () {
                        counterWrapperMedia.find('.counter-total')
                            .text(totalMedia)
                            .removeClass('hidden')
                            .fadeIn();
                    });

                // Call the recursive function to start the deletion process (media first, then entries)
                _deleteMediaRecursively(endpointMedia, payload, projectSlug);
            });
        });

        wrapper.on('keyup', '#project-name', function (e) {
            e.preventDefault();
            // If the project name is correct, enable the delete button
            if (projectName.replace(/\s+/g, ' ') === $(this).val()) {
                $('.btn-delete-entries').prop('disabled', false);
            } else {
                $('.btn-delete-entries').prop('disabled', true);
            }
        });

        // Define a function that handles the beforeunload event
        function handleBeforeUnload(event) {
            event.returnValue = 'ciao'; // Triggers the confirmation prompt
        }

        var deletedEntries = 0;
        var deletedMedia = 0;
        var totalEntries = parseInt(wrapper.data('total-entries'));
        var totalMedia = parseInt(wrapper.data('total-media'));
        var remainingEntries = totalEntries;
        var remainingMedia = totalMedia;
        var progressBarEntries = $('.progress-bar__modal-deletion__entries');
        var progressBarMedia = $('.progress-bar__modal-deletion__media');
        counterWrapperEntries.find('.counter-total').text(remainingEntries);
        counterWrapperMedia.find('.counter-total').text(remainingMedia);

        function _deleteEntriesRecursively(endpoint, payload, projectSlug) {
            // Make the POST request to delete entries
            $.when(window.EC5.projectUtils.postRequest(endpoint, payload))
                .done(function () {
                    if (remainingEntries > chunkSizeEntries) {
                        deletedEntries += chunkSizeEntries;
                    } else {
                        deletedEntries += remainingEntries;
                    }

                    updateProgressBarEntries(deletedEntries, remainingEntries, totalEntries);

                    // Check if there are more entries to delete
                    $.get(window.EC5.SITE_URL + '/api/internal/counters/entries/' + projectSlug, function (response) {
                        try {
                            if (response.data.counters.total > 0) {
                                // If there are more entries to delete, call the function recursively
                                remainingEntries = response.data.counters.total;
                                _deleteEntriesRecursively(endpoint, payload, projectSlug);
                            } else {
                                // To remove the confirmation dialog, remove the event listener
                                window.removeEventListener('beforeunload', handleBeforeUnload);
                                window.location.href = backURL;
                                // Notify the user that the operation was successful
                                window.EC5.toast.showSuccess('All Entries Deleted.');
                            }
                        } catch (e) {
                            // Show errors to the user if the request fails
                            window.EC5.projectUtils.showErrors(e);
                            // Always hide the modal
                            modal.modal('hide');
                        }
                    }).fail(function (e) {
                        // Show errors to the user if the request fails
                        window.EC5.projectUtils.showErrors(e);
                        // Always hide the modal
                        modal.modal('hide');
                    });
                })
                .fail(function (e) {
                    // Show errors to the user if the request fails
                    window.EC5.projectUtils.showErrors(e);
                    // Always hide the modal
                    modal.modal('hide');
                });
        }

        function _deleteMediaRecursively(endpoint, payload, projectSlug) {
            // Make the POST request to delete entries
            $.when(window.EC5.projectUtils.postRequest(endpoint, payload))
                .done(function (response) {
                    //if any unknown errors, bail out
                    if (
                        response &&
                        response.data &&
                        response.data.code !== 'ec5_407'
                    ) {
                        // Show errors to the user if the request fails
                        window.EC5.projectUtils.showErrors(response);
                        // Always hide the modal
                        modal.modal('hide');
                        return;
                    }

                    var deleted = response.data.deleted;
                    if (deleted > 0) {
                        remainingMedia = totalMedia - deleted;
                        deletedMedia += deleted;
                    } else {
                        if (totalMedia === deletedMedia) {
                            remainingMedia = 0;
                        }
                    }
                    updateProgressBarMedia(deletedMedia, remainingMedia, totalMedia);

                    if (remainingMedia === 0) {
                        //all media deleted, start deleting entries
                        _deleteEntriesRecursively(endpointEntries, payload, projectSlug);
                    } else {
                        // If there are more media to delete, call the function recursively
                        _deleteMediaRecursively(endpointMedia, payload, projectSlug);
                    }
                })
                .fail(function (e) {
                    // Show errors to the user if the request fails
                    window.EC5.projectUtils.showErrors(e);
                    // Always hide the modal
                    modal.modal('hide');
                });
        }


        // Function to update the progress bar entries
        function updateProgressBarEntries(deleted, remaining, total) {
            //no entries? bail out
            if (remaining === 0) {
                counterWrapperEntries.find('.counter-percentage').text('100%');
                counterWrapperEntries.find('.counter-deleted').text(total);
                progressBarEntries.attr('aria-valuenow', 0);
                progressBarEntries.css('width', 0 + '%');
                return;
            }
            // Calculate the percentage of progress
            var percentage = ((deleted / total) * 50).toFixed(1);
            var percentageReverse = ((1 - (deleted / total)) * 50).toFixed(1);
            // Get the progress bar element


            // Update the aria-valuenow attribute and the style width
            progressBarEntries.attr('aria-valuenow', percentageReverse);
            progressBarEntries.css('width', percentageReverse + '%');

            // Update the text inside the progress bar
            counterWrapperEntries.find('.counter-percentage').text(percentage * 2 + '%');
            counterWrapperEntries.find('.counter-deleted').text(deleted);
            counterWrapperEntries.find('.counter-total').text(total);
        }

        function updateProgressBarMedia(deleted, remaining, total) {
            //no media? bail out
            if (remaining === 0) {
                counterWrapperMedia.find('.counter-percentage').text('100%');
                counterWrapperMedia.find('.counter-deleted').text(total);
                progressBarMedia.attr('aria-valuenow', 0);
                progressBarMedia.css('width', 0 + '%');
                return;
            }
            // Calculate the percentage of progress
            var percentage = ((deleted / total) * 50).toFixed(1);
            var percentageReverse = ((1 - (deleted / total)) * 50).toFixed(1);
            // Get the progress bar element

            // Update the aria-valuenow attribute and the style width
            progressBarMedia.attr('aria-valuenow', percentageReverse);
            progressBarMedia.css('width', percentageReverse + '%');

            // Update the text inside the progress bar
            counterWrapperMedia.find('.counter-percentage').text(percentage * 2 + '%');
            counterWrapperMedia.find('.counter-deleted').text(deleted);
            counterWrapperMedia.find('.counter-total').text(total);
        }
    }
});

$(document).ready(function () {
    //enable only on page-entries-deletion
    if ($('.page-project-delete').length > 0) {

        var projectName = $('h3[data-project-name]').attr('data-project-name');
        var wrapper = $('.delete-project');
        var modal = $('#modal-deletion');


        wrapper.submit(function (e) {
            // Don't allow user to submit if the project
            // name they've typed is incorrect
            if (projectName.replace(/\s+/g, ' ') !== $('#project-name').val()) {
                e.preventDefault();
            }
            modal.modal({backdrop: 'static', keyboard: false}, 'show');
        });

        wrapper.on('keyup', '#project-name', function (e) {
            e.preventDefault();
            // If the project name is correct, enable submit button
            if (projectName.replace(/\s+/g, ' ') === $(this).val()) {
                $('.submit-delete').prop('disabled', false);
            } else {
                $('.submit-delete').prop('disabled', true);
            }
        });
    }
});

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
});



'use strict';

$(document).ready(function () {
    //generate QR Code on project home page only
    if ($('.page-project-home').length > 0) {
        var qrCodeWrapper = $('#qrcode');
        var qrcode = new window.QRCode('qrcode', {
            text: qrCodeWrapper.data('url'),
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});



$(document).ready(function () {
    //enable only on page-entries-deletion
    if ($('.page-project-leave').length > 0) {

        var projectName = $('h3[data-project-name]').attr('data-project-name');
        var wrapper = $('.leave-project');
        var modal = $('#modal-leave');

        wrapper.submit(function (e) {
            // Don't allow user to submit if the project
            // name they've typed is incorrect
            if (projectName.replace(/\s+/g, ' ') !== $('#project-name').val()) {
                e.preventDefault();
            }
            modal.modal({backdrop: 'static', keyboard: false}, 'show');
        });

        wrapper.on('keyup', '#project-name', function (e) {
            e.preventDefault();
            // If the project name is correct, enable submit button
            if (projectName.replace(/\s+/g, ' ') === $(this).val()) {
                $('.submit-leave').prop('disabled', false);
            } else {
                $('.submit-leave').prop('disabled', true);
            }
        });
    }
});

'use strict';

$(document).ready(function () {

    // Entries Limits
    var entriesTable = $('.manage-entries-limits__table');
    var limitsForm = $('.page-manage-entries #limits-form');
    var limitsFormUpdateBtn = $('.page-manage-entries .limits-form__update-btn');
    var bulkUploadBtns = $('.page-manage-entries .bulk-upload-btns');
    var bulkDeletionBtn = $('.page-manage-entries a.entries-deletion');
    var projectSlug = bulkUploadBtns.data('project-slug');
    var canBulkUploadURL = window.EC5.SITE_URL + '/api/internal/can-bulk-upload/' + projectSlug;

    entriesTable.on('change', 'td input:checkbox', function () {
        // limit input in the the next td
        var limitInput = $(this).parents('td').next().find('input.input__limit-to');

        if (this.checked) {
            // Enable input
            limitInput.prop('disabled', false);
        } else {
            // Disable input
            limitInput.val('');
            limitInput.prop('disabled', true);
        }
    });

    limitsFormUpdateBtn.on('click', function () {
        //clear any toasts
        window.EC5.toast.clear();
        //show overlay
        window.EC5.overlay.fadeIn();
        //clear any error
        limitsForm.find('.input__set-limit').each(function () {
            var currentCheckbox = $(this);
            var currentInput = currentCheckbox.parents('td').next().find('input.input__limit-to');
            var currentInputFormGroup = currentInput.parent();
            var errorFeedback = currentInputFormGroup.find('.form-control-feedback');

            errorFeedback.addClass('hidden');
            currentInputFormGroup.removeClass('has-error has-feedback');
        });

        var isFormValid = true;
        //check for checked "Set Limit" checkboxes
        limitsForm.find('.input__set-limit:checked').each(function () {

            var currentCheckbox = $(this);
            var currentInput = currentCheckbox.parents('td').next().find('input.input__limit-to');
            var currentInputFormGroup = currentInput.parent();
            var errorFeedback = currentInputFormGroup.find('.form-control-feedback');

            if (currentInput.val() === '') {
                currentInputFormGroup.addClass('has-error has-feedback');
                errorFeedback.removeClass('hidden');
                isFormValid = false;
            }
        });

        //if form is valid submit
        if (isFormValid) {
            limitsForm.submit();
        } else {
            window.EC5.overlay.fadeOut();
            window.EC5.toast.showError('Required fields missing!');
        }
    });

    // Entries limits form submit
    limitsForm.on('submit', function (e) {
        e.preventDefault();

        // Post data
        $.ajax({
            url: '',
            type: 'POST',
            dataType: 'json',
            data: $(this).serializeArray()
        }).done(function () {
            window.EC5.toast.showSuccess('Updated.');
        }).fail(function (error) {
            if (error.responseJSON && error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {
                    var i;
                    for (i = 0; i < error.responseJSON.errors.length; i++) {
                        window.EC5.toast.showError(error.responseJSON.errors[i].title);
                    }
                }
            }
        }).always(function () {
            window.EC5.overlay.fadeOut();
        });
    });

    //Bulk Upload
    bulkUploadBtns.on('click', '.btn', function (e) {

        var selectedOption = $(this);
        //show overlay
        window.EC5.overlay.fadeIn();


        //post request to change bulk upload settings
        window.EC5.projectUtils.postRequest(canBulkUploadURL, {
            can_bulk_upload: $(this).data('bulk-upload')
        })
            .done(function () {
                //reset all buttons
                bulkUploadBtns.find('.btn-action').removeClass('btn-action');
                //set current button as active
                selectedOption.addClass('btn-action');

                window.EC5.overlay.fadeOut();
                window.EC5.toast.showSuccess('Settings updated.');
            }).fail(function (error) {
            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {
                    for (var i = 0; i < error.responseJSON.errors.length; i++) {
                        window.EC5.toast.showError(error.responseJSON.errors[i].title);
                    }
                }
            }
            window.EC5.overlay.fadeOut();
        });
    })

    // bulkDeletionBtn.one('click', function (e) {
    //     window.EC5.overlay.fadeIn();
    // });
});

$(document).ready(function () {

    //skip all if not on the manage-users page
    if ($('.page-manage-users').length === 0) {
        return;
    }

    // Variable for storing ajax requests
    var requestTimeout;
    var manageProjectUsersForm = $('.manage-project-users');
    var pageName = '#creator';
    var config = window.EC5.project_users.config;

    window.EC5.overlay.fadeIn();

    // Dynamically load tab content via ajax request
    $('a[data-toggle="tab"]').on('show.bs.tab', function (e) {
        window.EC5.overlay.fadeIn();
        var target = $(e.target).attr('href');
        pageName = 'page-' + target.substring(1);
        $('.manage-project-users__' + pageName).html('');
        $('.user-search-heading').hide();

        // Load users for this role
        $.when(window.EC5.project_users.getProjectUsers(pageName, 1)).then(function (data) {
            $('.manage-project-users__' + pageName).hide().html(data).fadeIn(config.consts.ANIMATION_FAST);
        }, function (error) {
            if (error.responseJSON) {
                window.EC5.toast.showError(error.responseJSON.errors[0].title);
            } else {
                window.EC5.toast.showError(error);
            }
        }).always(function () {
            window.EC5.overlay.fadeOut();
        })
    });

    //creator tab by default so grab creator
    // Load users for this role
    $.when(window.EC5.project_users.getProjectUsers('page-creator').then(function (data) {
        $('.manage-project-users__' + pageName).remove().html(data);
        window.EC5.overlay.fadeOut();
    }, function (error) {
        window.EC5.overlay.fadeOut();
    }));

    //switch role of a user
    // event is attached to parent and filtered so it will work on newly added elements
    $('.page-manage-users .tab-content .panel-body')
        .off().on('click', '.manage-project-users__switch-role', function () {

        var projectSlug = $(this).data('project-slug');
        var userRole = $(this).data('user-role');
        var userEmail = $(this).data('user-email');
        var modal = $('#ec5SwitchUserRole');

        //pre fill user email
        modal.find('.user-email strong').text(userEmail);

        //show all roles
        var availableRoles = $.map(config.consts.ROLES, function (value) {
            return value;
        });

        $(availableRoles).each(function (key, value) {
            modal.find('.role-' + value).show();
        });

        //hide current role from the radio options (useless to show it)
        modal.find('.role-' + userRole).hide();

        //disable confirm button
        modal.find('.switch-role-confirm').attr('disabled', true);

        //deselect all radio
        modal.find('input:radio').prop('checked', false);

        modal.modal('show');

        modal.on('shown.bs.modal', function (e) {
            // do something...
            var newRole = null;

            modal.find('.users__pick-role .radio label').off().on('click', function () {

                //get the new role
                newRole = modal.find('.users__pick-role').find('.radio input:checked').val();

                //enable confirm button only when user pick a role
                modal.find('.switch-role-confirm').attr('disabled', false);
            });

            //on click confirm, switch role
            modal.find('.switch-role-confirm').off().on('click', function () {

                if (newRole === null) {
                    return false;
                }

                if (!projectSlug || !userEmail) {
                    return false;
                }

                window.EC5.overlay.fadeIn();

                //on response, hide modal and show notification
                $.when(window.EC5.project_users
                    .switchUserRole(projectSlug, userEmail, userRole, newRole))
                    .done(function (response) {

                        var pageName = 'page-' + userRole;

                        //update user list for active tab
                        //refresh list of users
                        $.when(window.EC5.project_users.getProjectUsers(pageName, 1).then(function (data) {
                            // Update the relevant page section
                            $('.manage-project-users__' + pageName).html(data);
                            modal.modal('hide');
                            window.EC5.toast.showSuccess(response.data.message);
                            window.EC5.overlay.fadeOut();

                        }, function (error) {
                            window.EC5.overlay.fadeOut();
                            if (error.responseJSON) {
                                window.EC5.toast.showError(error.responseJSON.errors[0].title);
                            } else {
                                window.EC5.toast.showError(error);
                            }
                            modal.modal('hide');

                        }));
                    }).fail(function (error) {
                    console.log(error);
                    window.EC5.overlay.fadeOut();
                    if (error.responseJSON) {
                        window.EC5.toast.showError(error.responseJSON.errors[0].title);
                    } else {
                        window.EC5.toast.showError(error);
                    }
                    modal.modal('hide');
                });
            });
        })
    });


    // Bind on click to pagination links
    manageProjectUsersForm.on('click', '.pagination a', function (e) {

        e.preventDefault();

        window.EC5.overlay.fadeIn();

        // Get manage-project-users container
        var container = $(this).closest('.manage-project-users');

        // Get data-page-name data attribute from the container
        var pageName = container.data('page-name');

        // Get search value
        var search = container.find('.manage-project-users__user-search').val();

        // Get users based on page and any existing search or filter and filter option
        $.when(window.EC5.project_users.getProjectUsers(pageName, $(this).attr('href').split('=')[1], search).then(function (data) {
            // Update the relevant page section
            $('.manage-project-users__' + pageName).html(data);
            window.EC5.overlay.fadeOut()
        }, function () {
            window.EC5.overlay.fadeOut()
        }));
    });

    // Bind keyup event to search input
    manageProjectUsersForm.on('keyup', '.manage-project-users__user-search', function (e) {

        // Get value of input
        var value = this.value;

        // If the length is 3 or more characters, or the user pressed ENTER, search
        if (value.length >= 0 || e.keyCode === 13) {

            window.EC5.overlay.fadeIn();

            // Get manage-project-users container
            var container = $(this).closest('.manage-project-users');

            // Get data-page-name data attribute from the container
            var pageName = container.data('page-name');

            // Set delay amount
            // for user to stop typing
            var requestDelay = 200;

            /**
             * Throttle user requests so that we can wait until the user
             * has stopped typing before making ajax calls
             */

            // Clear the previous timeout request
            clearTimeout(requestTimeout);

            // Set new timeout request
            requestTimeout = setTimeout(function () {
                // Get users based on this search (with filter values, if applicable)
                $.when(window.EC5.project_users.getProjectUsers(pageName, 1, value).then(function (data) {
                    // Update the relevant page section
                    $('.manage-project-users__' + pageName).html(data);
                    window.EC5.overlay.fadeOut();
                }, function () {
                    window.EC5.overlay.fadeOut();
                }));

            }, requestDelay);
        }

    });

    // Bind on click to reset table
    manageProjectUsersForm.on('click', '.manage-project-users__reset', function (e) {

        window.EC5.overlay.fadeIn();

        e.preventDefault();

        // Get manage-project-users container
        var container = $(this).closest('.manage-project-users');

        // Get data-page-name data attribute from the container
        var pageName = container.data('page-name');

        // Remove search text
        $('.manage-project-users__user-search').val('');

        //refresh list of users
        $.when(window.EC5.project_users.getProjectUsers(pageName, 1).then(function (data) {
            // Update the relevant page section
            $('.manage-project-users__' + pageName).html(data);
            window.EC5.overlay.fadeOut();
        }, function () {
            window.EC5.overlay.fadeOut();
        }));
    });

    // Bind on click to remove user
    manageProjectUsersForm.on('submit', '.manage-project-users__table__remove-form', function (e) {

        e.preventDefault();

        // Retrieve form data
        var formData = $(this).serializeArray();

        // Get action url
        var url = $(this).attr('action');

        // Get manage-project-users container
        var container = $(this).closest('.manage-project-users');

        // Get data-page-name data attribute from the container
        var pageName = container.data('page-name');

        // Get page number
        var page = container.find('.pagination .active span').html();

        // Get search value
        var search = container.find('.manage-project-users__user-search').val();

        window.EC5.project_users.removeUserProjectRole(url, formData, pageName, page, search);
    });

    // Bind on click to add existing user from modal
    $('.manage-project-users__existing-user-add-form').on('submit', function (e) {
        e.preventDefault();
        // Retrieve form data
        var formData = $(this).serializeArray();
        // Get action url
        var url = $(this).attr('action');
        // Get current page name to focus on the right tab (by role) after adding user
        var pageName = 'page-creator';
        formData.forEach(function (formItem) {
            if (formItem.name === 'role') {
                pageName = formItem.value ? 'page-' + formItem.value : 'page-creator';
                return false;
            }
        });

        window.EC5.project_users.addUserProjectRole(url, formData, pageName, function () {
            // Clear email
            $('#ec5ModalExistingUser').find('input[type=email]').val('');
            // Close this modal
            $('#ec5ModalExistingUser').modal('hide');
        });
    });

    // Bind on click to add new user from modal
    $('.manage-project-users__new-user-add-form').on('submit', function (e) {

        e.preventDefault();

        // Retrieve form data
        var thisFormData = $(this).serializeArray();

        // Retrieve form data from the previous form (email, role)
        var lastFormData = $('.manage-project-users__existing-user-add-form').serializeArray();

        // Concat two arrays
        var postData = thisFormData.concat(lastFormData);

        // Get action url
        var url = $(this).attr('action');

        // Get current page name
        var pageName = postData[2] && postData[2].value ? 'page-' + postData[2].value : 'page-creator';

        window.EC5.project_users.addUserProjectRole(url, postData, pageName, function () {

            // Clear email
            $('#ec5ModalExistingUser').find('input[type=email]').val('');
            // Close previous modal
            $('#ec5ModalExistingUser').modal('hide');
            // Close this modal
            $('#ec5ModalNewUser').modal('hide');
        });

    });

    //pick a csv file
    $('.manage-user-more__import-users').off().on('click', function () {

        var target = $(this);

        //import file first then show modal to pick which column (if more than one)
        //todo not use window, use formbuilder object
        if (!window.EC5.project_users.isOpeningFileBrowser) {

            var file_input = target.find('.manage-user-more__import-users__input-file');

            window.EC5.project_users.isOpeningFileBrowser = true;

            file_input.off('change').on('change', function () {
                //perform the import
                window.EC5.project_users.pickCSVFile(this.files);
                $(this).val(null);
            });

            target.find('.manage-user-more__import-users__input-file').trigger('click');
        }

        //to avoid a infinte loop (since we are triggering the click event)
        //we remove the flag later, to be able to upload another file
        //even if the user tapped on "cancel"
        window.setTimeout(function () {
            window.EC5.project_users.isOpeningFileBrowser = false;
        }, 3000);
    });


    //export users
    $('.manage-user-more__export-users').off().on('click', function () {

        var projectSlug = $(this).find('a').data('project-slug');

        window.EC5.overlay.fadeIn();

        $.when(window.EC5.project_users.exportUsersToCSV(projectSlug)).then(function () {
            window.EC5.overlay.fadeOut();
        }, function (error) {
            if (error.responseJSON) {
                window.EC5.toast.showError(error.responseJSON.errors[0].title);
            } else {
                window.EC5.toast.showError(error);
            }
            window.EC5.overlay.fadeOut();
        });
    });

    /**
     * Remove all users by role
     */
    $('.manage-project-users__delete-by-role').off().on('click', function () {

        var projectSlug = $(this).data('project-slug');
        var role = $(this).data('role');

        window.EC5.overlay.fadeIn();

        $.when(window.EC5.project_users.removeUsersByRole(projectSlug, role)).then(function (response) {
            window.EC5.toast.showSuccess(response.data.message);

            //refresh list of users
            $.when(window.EC5.project_users.getProjectUsers(pageName, 1).then(function (data) {
                // Update the relevant page section
                $('.manage-project-users__' + pageName).html(data);
                window.EC5.overlay.fadeOut();
            }, function () {
                window.EC5.overlay.fadeOut();
            }));

        }, function (error) {
            window.EC5.overlay.fadeOut();
            if (error.responseJSON) {
                window.EC5.toast.showError(error.responseJSON.errors[0].title);
            } else {
                window.EC5.toast.showError(error);
            }
        });
    });
});

'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var maxAmountOfMaps = 4;
    var pageMappingData = $('.page-mapping-data');
    var postURL = pageMappingData.data('url');

    //do not do anything if not on the mapping page
    if (pageMappingData.length === 0) {
        return;
    }

    //bind form selection dropdown
    $('.form-list-selection').on('click', 'li', function () {

        var selectedForm = $(this);
        var formRef = $(selectedForm).data('form-ref');
        var mapIndex = $('.map-data__tabs li.active').data('map-index');
        var tabContent =  $('#map-data-tabcontent-' + mapIndex + ' .panel-body');
        //set selected option text as the text of the dropdown
        selectedForm
            .parents('.form-list-selection')
            .find('.dropdown-toggle .form-list-selection__form-name')
            .text(selectedForm.text());

        //show mapping for selected form
        tabContent.find('.table-responsive').fadeOut().addClass('hidden');
        tabContent.find('[data-form-ref="' + formRef + '"]').hide().removeClass('hidden').fadeIn();
    });

    //handle update/make default
    $('.map-data--actions__btns').on('click', '[data-toggle="push"]', function () {
        var target = $(this);
        var action = target.data('action');
        var activeTab = $('.map-data__tabs li.active');
        var inactiveTabs = $('.map-data__tabs li').not('.active').not('.map-data__tabs__add-form');
        var mapIndex = activeTab.data('map-index');
        var mapName = activeTab.data('map-name');
        console.log(action);

        switch (action) {
            case 'make-default':
                window.EC5.mapping.makeDefault(postURL, action, mapIndex, mapName, activeTab, inactiveTabs);
                break;
            case 'update':
                window.EC5.mapping.update(postURL, action, mapIndex, mapName);
                break;
        }
    });

    //Handle modals
    $('#modal__mapping-data').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var action = button.data('action'); //action to perform
        var modal = $(this);
        var title = button.data('trans');
        var confirm_btn = modal.find('.map-data__modal__save-btn');
        var activeTab = $('.map-data__tabs li.active');
        var isDefault = !activeTab.find('.map-data__default-pin').hasClass('invisible');
        var mapIndex = activeTab.data('map-index');
        var mapName = activeTab.data('map-name');
        var tabContentWrapper = $('.page-mapping-data .tab-content');

        modal.find('.modal-title').text(title);

        switch (action) {
            case 'delete':
                window.EC5.mapping.delete(postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn, isDefault, tabContentWrapper);
                break;
            case 'rename':
                window.EC5.mapping.rename(postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn);
                break;
            case 'add-mapping':
                window.EC5.mapping.addMapping(postURL, modal, confirm_btn, maxAmountOfMaps, tabContentWrapper, tabContentWrapper);
                break;
        }
    });


    //get active tab and set the active tab content on page load
    //$('.map-data__tabs li.active a').tab('show');
});

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


'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     * Function to add a user project role
     *
     * @param url
     * @param formData
     * @param pageName
     * @param callBack
     */
    module.addUserProjectRole = function (url, formData, pageName, callBack) {

        var i;

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData
        }).done(function (data) {
            // Show success
            window.EC5.toast.showSuccess(data.data.message);

            // Get project users based on page and any existing search
            $.when(module.getProjectUsers(pageName, 1)).then(function (response) {

                var selectedUserRole = pageName.replace('page-', '');
                // Update the relevant page section
                $('.manage-project-users__' + pageName).html(response);

                //switch tab
                $('.page-manage-users .nav-tabs li').find('a.' + selectedUserRole + '-tab-btn').trigger('click');

                // If passed a callback function
                if (callBack) {
                    callBack();
                }
            });
        }).fail(function (error) {

            var userDoesntExist = 'ec5_90';

            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {

                    for (i = 0; i < error.responseJSON.errors.length; i++) {
                        if (error.responseJSON.errors[i].code === userDoesntExist) {
                            // If the user doesn't exist, ask the user if they want to add
                            $('#ec5ModalNewUser').modal();

                        } else {
                            window.EC5.toast.showError(error.responseJSON.errors[i].title);
                        }
                    }
                }
            }
        });
    };

}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function config(module) {

    module.config = {
        messages: {
            error: {
                CSV_FILE_INVALID: 'CSV file is invalid',
                INVALID_EMAILS: 'Invalid emails',
                BROWSER_NOT_SUPPORTED: 'Browser not supported'

            },
            success: {
                USERS_IMPORTED: 'Users added to the project'
            },
            warning: {
                SOME_USERS_NOT_IMPORTED: 'Some users could not be imported'
            }
        },
        consts: {
            CSV_FILE_EXTENSION: 'csv',
            ANIMATION_FAST: 200,
            ANIMATION_NORMAL: 500,
            ROLES: {
                CREATOR: 'creator',
                MANAGER: 'manager',
                CURATOR: 'curator',
                COLLECTOR: 'collector',
                VIEWER: 'viewer'
            }
        },
        errorCodes: {
            userDoesntExist: 'ec5_90',
            importerDoesNotHavePermission: 'ec5_91',
            invalidValue: 'ec5_29', //if role or provider are invalid
            invalidEmailAddress: 'ec5_42',
            creatorEmailAddress: 'ec5_217',
            managerEmailAddress: 'ec5_344'
        },
        invalidEmailAddresses: []
    }

}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {

    module.exportUsersToCSV = function (projectSlug) {

        var self = this;
        var config = self.config;
        var deferred = new $.Deferred();
        var url = window.EC5.SITE_URL + '/api/internal/project-users/' + projectSlug;

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {

            var users = {
                all: [],
                creators: [],
                managers: [],
                curators: [],
                collectors: [],
                viewers: []
            };

            var users_csv = {
                all: '',
                creators: '',
                managers: '',
                curators: '',
                collectors: '',
                viewers: ''
            };

            //do a bit of parsng
            $(response.data).each(function (index, user) {

                users.all.push(user);

                switch (user.role) {
                    case 'creator':
                        users.creators.push(user);
                        break;
                    case 'manager':
                        users.managers.push(user);
                        break;
                    case 'curator':
                        users.curators.push(user);
                        break;
                    case 'collector':
                        users.collectors.push(user);
                        break;
                    case 'viewer':
                        users.viewers.push(user);
                        break;
                    default:
                    //do nothing
                }

            });

            //create a csv string per each role and one with all the users
            $.each(users, function (key) {
                users_csv[key] = Papa.unparse({
                    data: users[key]
                }, {
                    quotes: false,
                    quoteChar: '',
                    delimiter: ',',
                    header: true,
                    newline: '\r\n'
                });
            });

            //create a file per each string
            var zip = new JSZip();
            $.each(users_csv, function (key, content) {
                zip.file(key + '.csv', content);
            });

            //zip files and serve zip
            zip.generateAsync({ type: 'blob' })
                .then(function (content) {
                    // see FileSaver.js
                    try {
                        saveAs(content, projectSlug + '_users.zip');
                        deferred.resolve();
                    }
                    catch (error) {
                        console.log(error);
                        //show error browser not compatible to user (IE <10)
                        deferred.reject(config.messages.error.BROWSER_NOT_SUPPORTED);
                    }
                });

        }).fail(function (error) {
            deferred.reject(error);
        });

        return deferred.promise();
    }

    module.getTotalsByRole = function (projectSlug) {

        var deferred = new $.Deferred();
        var url = window.EC5.SITE_URL + '/api/internal/project-users/' + projectSlug;

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {

            var users = {
                creator: 0,
                manager: 0,
                curator: 0,
                collector: 0,
                viewer: 0
            };
            //do a bit of parsing
            $(response.data).each(function (i, user) {
                users[user.role]++;
            });



            deferred.resolve(users);

        }).fail(function (error) {
            deferred.reject(error);
        });

        return deferred.promise();
    }

}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function getProjectUsers(module) {
    /**
     * Function for asynchronously retrieving the
     * list of project users, based on any search
     * criteria, with pagination
     *
     * @param pageName
     * @param page
     * @param search
     */
    module.getProjectUsers = function (pageName, page, search) {

        var deferred = new $.Deferred();
        var data = {};

        // Set defaults
        page = typeof page !== 'undefined' ? page : 1;
        search = typeof search !== 'undefined' ? search : '';

        // Set up data object
        data[pageName] = page;
        data.search = search;

        var usersRequest = $.ajax({
            url: '',
            type: 'GET',
            dataType: 'json',
            data: data
        });

        var updateCounters = window.EC5.project_users.updateRoleCounters();

        $.when(usersRequest, updateCounters)
            .then(function (data) {
                deferred.resolve(data[0]);
            })
            .fail(function (error) {
                deferred.reject(error[0]);
            });

        return deferred.promise();
    };
}(window.EC5.project_users));

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

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {

    module.pickCSVFile = function (files) {

        var self = this;
        var file = files[0];
        var file_parts;
        var file_ext;
        var config = self.config;

        function _parseErrors(error) {

            var parsedErrors = '';

            if (error.responseJSON) {
                $.each(error.responseJSON.errors, function (index, error) {
                    parsedErrors += error.title + '\n';
                });
            } else {
                parsedErrors = error;
            }
            return parsedErrors;
        }

        //show overlay and cursor
        window.EC5.overlay.fadeIn();

        self.isOpeningFileBrowser = false;

        //if the user cancels the action
        if (!file) {
            //hide overlay
            window.EC5.overlay.fadeOut(config.consts.ANIMATION_FAST);
            window.EC5.toast.showError(config.messages.error.CSV_FILE_INVALID);
            return;
        }

        file_parts = file.name.split('.');
        file_ext = file_parts[file_parts.length - 1];

        //it must be csv
        if (file_ext !== config.consts.CSV_FILE_EXTENSION) {
            //hide overlay
            window.EC5.overlay.fadeOut(config.consts.ANIMATION_FAST);
            window.EC5.toast.showError(config.messages.error.CSV_FILE_INVALID);
            return;
        }
        //file is valid, let's parse it
        var reader = new FileReader();

        reader.onload = function (e) {
            var content = e.target.result;
            var json = Papa.parse(content, {
                header: true,
                delimiter: ',',
                skipEmptyLines: 'greedy'
            });
            var headers = json.meta.fields;
            var modal = $('#ec5ModalImportUsers');

            if (json.data.length === 0) {
                //empty csv file, show error
                window.EC5.overlay.fadeOut(config.consts.ANIMATION_FAST);
                window.EC5.toast.showError(config.messages.error.CSV_FILE_INVALID);
                return;
            }

            modal.modal();

            modal.off().on('shown.bs.modal', function () {

                var column_picker = modal.find('.users-column-picker');
                var column_items = '';
                var selectedHeaderIndex = null;
                var params;
                var doesFirstRowContainsHeaders;
                var selectedUserRole;
                var postURL = modal.data('post-url');

                //reset column picker
                column_picker.find('.btn').html('Pick column' + ' <span class="caret"></span>');
                column_picker.find('.btn').val('');

                //reset other controls
                modal.find('.users__first-row-headers input').prop('checked', true);

                //reset other controls
                modal.find('.users__pick-role input#collector').prop('checked', true);

                //disable import button
                modal.find('.users-perform-import').attr('disabled', true);

                window.EC5.overlay.fadeOut(config.consts.ANIMATION_FAST);

                //show list of headers so the user can select which column to use
                //generate list items
                $(headers).each(function (headerIndex, header) {
                    column_items += '<li>';
                    column_items += '<a href="#">' + header.trunc(25) + '</a>';
                    column_items += '</li>';
                });

                //append items
                column_picker.find('.dropdown-menu').empty().append(column_items);

                //show selected column in dropdown picker
                column_picker.find('.dropdown-menu li').off().on('click', function () {
                    $(this).parents('.users-column-picker').find('.btn').html($(this).text() + ' <span class="caret"></span>');
                    $(this).parents('.users-column-picker').find('.btn').val($(this).data('value'));

                    selectedHeaderIndex = $(this).index();

                    //enable import button
                    modal.find('.users-perform-import').attr('disabled', false);
                });

                $('.users-perform-import').off().on('click', function () {

                    if (selectedHeaderIndex === null) {
                        return false;
                    }

                    //show overlay and cursor
                    window.EC5.overlay.fadeIn();

                    //get parameters from modals
                    doesFirstRowContainsHeaders = modal.find('.users__first-row-headers').find('.checkbox input').is(':checked');

                    selectedUserRole = modal.find('.users__pick-role').find('.radio input:checked').val();

                    //add callback to handle the import
                    params = {
                        doesFirstRowContainsHeaders: doesFirstRowContainsHeaders,
                        selectedUserRole: selectedUserRole,
                        importedJson: json,
                        selectedHeaderIndex: selectedHeaderIndex,
                        postURL: postURL
                    };

                    window.setTimeout(function () {
                        //show overlay and cursor
                        console.log('imported');

                        $.when(self.importUsersByEmail(params)).then(function (response) {
                            //get current active page
                            var pageName = 'page-' + selectedUserRole;

                            $.when(window.EC5.project_users.getProjectUsers(pageName, 1, '').then(function (data) {
                                // Update the relevant page section
                                $('.manage-project-users__' + pageName).html(data);

                                $('.page-manage-users .nav-tabs li').find('a.' + selectedUserRole + '-tab-btn').trigger('click');

                                //hide overlay and modal
                                window.EC5.overlay.fadeOut();
                                $('#ec5ModalImportUsers').modal('hide');

                                //show errors or success
                                if (config.invalidEmailAddresses.length > 0) {
                                    window.EC5.toast.showWarning(config.messages.warning.SOME_USERS_NOT_IMPORTED);
                                    window.EC5.toast.showError(config.messages.error.INVALID_EMAILS + ': \n' + config.invalidEmailAddresses.join('\n'));
                                } else {
                                    window.EC5.toast.showSuccess(config.messages.success.USERS_IMPORTED);
                                }
                            }, function () {
                                window.EC5.overlay.fadeOut()
                            }));
                        }, function (error) {

                            var pageName = 'page-' + selectedUserRole;

                            $.when(window.EC5.project_users.getProjectUsers(pageName, 1, '').then(function (data) {
                                // Update the relevant page section
                                $('.manage-project-users__' + pageName).html(data);

                                //switch tab
                                $('.page-manage-users .nav-tabs li').find('a.' + selectedUserRole + '-tab-btn').trigger('click');

                                //hide overlay and modal
                                window.EC5.overlay.fadeOut();
                                $('#ec5ModalImportUsers').modal('hide');
                                //show errors
                                window.EC5.toast.showError(_parseErrors(error));
                                if (config.invalidEmailAddresses.length > 0) {
                                    window.EC5.toast.showWarning(config.messages.warning.SOME_USERS_NOT_IMPORTED);
                                    window.EC5.toast.showError(config.messages.error.INVALID_EMAILS + ': \n' + config.invalidEmailAddresses.join('\n'));
                                }
                            }, function () {
                                window.EC5.overlay.fadeOut()
                            }));
                        });
                    }, 1000);
                });
            });

            //add events to hide the modal manually (was nt working, go figure)
            modal.find('button[data-dismiss="modal"]').one('click', function () {
                modal.modal('hide');
            });
        };

        reader.readAsText(file);
    };

}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     * Function to remove a user project role
     *
     * @param url
     * @param formData
     * @param pageName
     * @param page
     * @param search
     */
    module.removeUserProjectRole = function (url, formData, pageName, page, search) {

        var config = window.EC5.project_users.config;
        var len = formData.length;
        var dataObj = {};
        var i;

        //show overlay
        window.EC5.overlay.fadeIn();

        // Get all values from form data
        for (i = 0; i < len; i++) {
            dataObj[formData[i].name] = formData[i].value;
        }

        // Reduce total users by one, as we are removing one
        var totalPages = dataObj['total-users'] - 1;

        // If we have no users left on this page,
        // send user to the previous page
        if (totalPages === 0) {
            page = page - 1;
        }

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: formData,
            page: page
        }).done(function (data) {
            // Get project users based on page and any existing search
            $.when(module.getProjectUsers(pageName, page, search)).then(function (response) {
                // Update the relevant page section
                $('.manage-project-users__' + pageName).html(response);
                // Show success
                window.setTimeout(function () {
                    window.EC5.toast.showSuccess(data.data.message);
                    window.EC5.overlay.fadeOut();
                }, config.consts.ANIMATION_NORMAL);
            }, function (error) {

                window.setTimeout(function () {
                    if (error.responseJSON.errors) {
                        // Show the errors
                        if (error.responseJSON.errors.length > 0) {
                            for (i = 0; i < error.responseJSON.errors.length; i++) {
                                window.EC5.toast.showError(error.responseJSON.errors[i].title);
                            }
                        }
                    }
                    window.EC5.overlay.fadeOut();
                }, config.consts.ANIMATION_NORMAL);
            });
        }).fail(function (error) {
            window.setTimeout(function () {
                if (error.responseJSON.errors) {
                    // Show the errors
                    if (error.responseJSON.errors.length > 0) {
                        for (i = 0; i < error.responseJSON.errors.length; i++) {
                            window.EC5.toast.showError(error.responseJSON.errors[i].title);
                        }
                    }
                }
                window.EC5.overlay.fadeOut();
            }, config.consts.ANIMATION_NORMAL);
        });
    };
}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {

    /**
     *
     * @param projectSlug
     */
    module.removeUsersByRole = function (projectSlug, role) {

        var deferred = new $.Deferred();
        var url = window.EC5.SITE_URL + '/api/internal/project-users/' + projectSlug + '/remove-by-role';

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: { role : role}
        }).done(function (response) {
            var pageName = 'page-' + role;
            $('.manage-project-users__' + pageName).html('');
            deferred.resolve(response);
        }).fail(function(error){
            deferred.reject(error);
        });

        return deferred.promise();

    };
}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     *
     * @param projectSlug
     * @param email
     * @param currentRole
     * @param newRole
     */
    module.switchUserRole = function (projectSlug, email, currentRole, newRole) {

        var url = window.EC5.SITE_URL + '/api/internal/project-users/' + projectSlug + '/switch-role';
        var config = window.EC5.project_users.config;
        var availableRoles = $.map(config.consts.ROLES, function(value){return value;});
        var deferred = new $.Deferred();

        //invalid role
        if ($.inArray(currentRole, availableRoles) === -1 || $.inArray(newRole, availableRoles) === -1) {
            deferred.reject('Invalid role!');
        }

        //same role?
        if (currentRole === newRole) {
            deferred.reject('Old role and new role are the same!');
        }

        var params = {
            email: email,
            currentRole: currentRole,
            newRole: newRole
        };

        // Make ajax request to load users
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: params
        }).done(function (response) {
            console.log(response);
            deferred.resolve(response);
        }).fail(function (error) {
            console.log(error);
            deferred.reject(error);
        });

        return deferred.promise();
    };

}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.project_users = window.EC5.project_users || {};

(function (module) {
    /**
     *
     * @param projectSlug
     * @param email
     * @param currentRole
     * @param newRole
     */
    module.updateRoleCounters = function () {

        var pageManageUser = $('.page-manage-users');
        var deferred = new $.Deferred();
        var projectSlug = pageManageUser.data('project-slug');

        //get hold of users-by-role counters
        var counterHandlers = {
            all: pageManageUser.find('.count-overall'),
            creator: pageManageUser.find('.nav-tabs-roles').find('.count-creator'),
            manager: pageManageUser.find('.nav-tabs-roles').find('.count-manager'),
            curator: pageManageUser.find('.nav-tabs-roles').find('.count-curator'),
            collector: pageManageUser.find('.nav-tabs-roles').find('.count-collector'),
            viewer: pageManageUser.find('.nav-tabs-roles').find('.count-viewer')
        }

        var total = 0;
        $.when(window.EC5.project_users.getTotalsByRole(projectSlug)).then(function (counts) {
            Object.keys(counterHandlers).forEach(function (k) {
                counterHandlers[k].text(counts[k]);
                total += counts[k] || 0;
            });
            counterHandlers.all.text(total);

        }).fail(function (error) {
            deferred.reject(error);
        }).always(function () {
            deferred.resolve();
        });

        return deferred.promise();
    };

}(window.EC5.project_users));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.addMapping = function (postURL, modal, confirm_btn, maxAmountOfMaps, tabContentWrapper) {

        modal.find('.map-data__modal__mapping-name').show();
        modal.find('.map-data__modal-delete-warning').addClass('hidden');
        modal.find('map-data__modal__name-rules').removeClass('hidden');

        //set empty name in input field
        modal.find('.map-data__modal__mapping-name').val('');

        confirm_btn.off().on('click', function () {

            window.EC5.toast.clear();

            // Validate mapping name
            var name = modal.find('.map-data__modal__mapping-name').val();

            window.EC5.projectUtils.postRequest(postURL, {
                name: name,
                is_default: false
            }).done(function (response) {
                console.log(response);
                window.EC5.toast.showSuccess(name + ' mapping added');

                var newMapIndex = response.data.map_index;

                //refresh ui adding the new mapping tab
                var html = '<li role="presentation" data-map-index="' + newMapIndex + '" data-map-name="' + name + '">';
                html += ' <a href="#map-data-tabcontent-' + newMapIndex + '" role="tab" data-toggle="tab">';
                html += '<span class="map-data__map-name">' + name + '&nbsp;</span>';
                html += '<i class="fa fa-thumb-tack invisible" aria-hidden="true"></i>';
                html += '</a>';
                html += '</li>';

                $('.map-data__tabs li').not('.map-data__tabs__add-form').last().after(html);
                //remove add mapping tab if we reached the max number of maps

                //mapping object can be object or array as we do not keep the indexes in sequence
                //and the json parsing  will either generate an array or an object dependeing on the numeric keys
                if ($.isArray(response.data.mapping)) {
                    if (response.data.mapping.length === maxAmountOfMaps) {
                        $('.map-data__tabs li.map-data__tabs__add-form').addClass('hidden');
                    }
                }
                else {
                    if (Object.keys(response.data.mapping).length === maxAmountOfMaps) {
                        $('.map-data__tabs li.map-data__tabs__add-form').addClass('hidden');
                    }
                }
                //add mapping markup, cloning the default EC5_AUTO from the dom. (with events)
                var defaultMappingMarkup = tabContentWrapper.find('#map-data-tabcontent-0').clone(true, true);

                //amend id
                defaultMappingMarkup.attr('id', 'map-data-tabcontent-' + newMapIndex);

                //amend data attributes
                defaultMappingMarkup.attr('data-map-index', newMapIndex);
                defaultMappingMarkup.attr('data-map-name', name);

                //update map index in dom
                defaultMappingMarkup.find('[data-map-index]').each(function (index, element) {
                    $(element).attr('data-map-index', newMapIndex);
                });

                //activate all buttons
                defaultMappingMarkup.find('.map-data--actions__btns .btn').each(function (index, element) {
                    $(element).prop('disabled', false);
                });

                //activate all inputs per each row
                defaultMappingMarkup.find('.table-responsive').each(function (index, element) {
                        var inputElement = $(element).find('tr td input');
                        inputElement.prop('disabled', false).prop('readonly', false);
                    }
                );

                //add markup
                tabContentWrapper.append(defaultMappingMarkup);

                //switch to newly created mapping:

                //hide active tab
                $('.map-data__tabs li.active').removeClass('active');
                $('.page-mapping-data .tab-content .tab-pane[data-map-index="0"]').removeClass('active in');

                //show new tab
                $('.map-data__tabs li[data-map-index="' + newMapIndex + '"]').addClass('active');
                $('.map-data__tabs li[data-map-index="' + newMapIndex + '"] a').tab('show');

                window.setTimeout(function () {
                    modal.modal('hide');
                }, 250);

            }).fail(function (error) {
                console.log(error);
                window.EC5.projectUtils.showErrors(error);
            });
        });
    };

}(window.EC5.mapping));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.delete = function (postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn, isDefault, tabContentWrapper) {

        modal.find('.map-data__modal__mapping-name').hide();
        modal.find('.map-data__modal-delete-warning').removeClass('hidden');
        modal.find('map-data__modal__name-rules').addClass('hidden');

        confirm_btn.off().on('click', function () {
            window.EC5.toast.clear();
            window.EC5.projectUtils.postRequest(postURL + '/delete', {
                map_index: mapIndex
            }).done(function (data) {

                var tabs = $('.map-data__tabs');

                window.EC5.toast.showSuccess(mapName + ' deleted');

                //remove active tab
                activeTab.remove();

                //remove mapping markup
                tabContentWrapper.find('#map-data-tabcontent-' + mapIndex).remove();

                //set the EC5 Auto as the selected tab
                tabs.find('a[href="#map-data-tabcontent-0"]').tab('show');

                //if we deleted the default map, set EC5 Auto as default (the first tab)
                if (isDefault) {
                    tabs.find('.map-data__default-pin').first().removeClass('invisible');
                }

                //show "Add Mapping" tab button
                $('.map-data__tabs li.map-data__tabs__add-form').removeClass('hidden');

                modal.modal('hide');

            }).fail(function (error) {
                console.log(error);
                window.EC5.projectUtils.showErrors(error);
            });
        });
    };

}(window.EC5.mapping));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.getDefaultMapIndex = function () {
        var defaultMapIndex = 0;
        var tabs = $('.map-data__tabs li');

        tabs.each(function (index, tab) {

            var mapIndex = $(tab).attr('data-map-index');
            if (!$(tab).find('.map-data__default-pin').hasClass('invisible')) {
                defaultMapIndex = mapIndex;
                return false;
            }
        });
        return parseInt(defaultMapIndex, 10);
    };

}(window.EC5.mapping));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.makeDefault = function (postURL, action, mapIndex, mapName, activeTab, inactiveTabs) {

        window.EC5.toast.clear();

        window.EC5.projectUtils.postRequest(postURL + '/update', {
            action: action,
            map_index: mapIndex
        }).done(function (response) {
            console.log(JSON.stringify(response));
            window.EC5.toast.showSuccess(mapName + ' set as default');

            //update tabs button markup to "pin' the default one only, which is the active tab
            activeTab.find('.map-data__default-pin').removeClass('invisible');
            //hide any other pin
            inactiveTabs.each(function (index, tab) {
                $(tab).find('.map-data__default-pin').addClass('invisible');
            });
        }).fail(function (error) {
            window.EC5.projectUtils.showErrors(error);
        });
    };

}(window.EC5.mapping));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.rename = function (postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn) {

        modal.find('.map-data__modal__mapping-name').show();
        modal.find('.map-data__modal-delete-warning').addClass('hidden');
        modal.find('map-data__modal__name-rules').removeClass('hidden');
        //set existing name in input field
        modal.find('.map-data__modal__mapping-name').val(mapName);

        confirm_btn.off().on('click', function () {

            window.EC5.toast.clear();

            // Validate mapping name
            var name = modal.find('.map-data__modal__mapping-name').val();

            window.EC5.projectUtils.postRequest(postURL + '/update', {
                action: action,
                map_index: mapIndex,
                name: name
            }).done(function (data) {

                activeTab.attr('data-map-name', name);
                activeTab.find('.map-data__map-name').text(name);

                //dismiss modal on success
                modal.modal('hide');

                window.EC5.toast.showSuccess(name + ' renamed');
            }).fail(function (error) {
                console.log(error);
                window.EC5.projectUtils.showErrors(error);
            });
        });
    };

}(window.EC5.mapping));

'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //build mapping object only for active mapping tab
    module.update = function (postURL, action, mapIndex, mapName) {

        window.EC5.toast.clear();
        window.EC5.overlay.fadeIn();

        var mapping = {};
        var tab_panel = $('.page-mapping-data .tab-content .tab-pane[data-map-index="' + mapIndex + '"]');
        var isMappingValid = true;
        var hasDuplicateIdentifier = null;
        var formMapTos = {};

        //alphanumeric with underscore only, 1 to 20 length
        //imp: we do not allow '-' because of the json export
        var mappingToRegex = /^[a-zA-Z0-9_]{1,20}$/;
        var mappingPossibleAnswerToRegex = /^((?![<>]).){1,150}$/;

        var tables = tab_panel.find('.panel-body .table-responsive');

        tab_panel.find('input').parent().removeClass('has-error');

        mapping[mapIndex] = {};
        mapping[mapIndex].name = mapName;
        mapping[mapIndex].forms = {};
        mapping[mapIndex].map_index = mapIndex;
        mapping[mapIndex].is_default = window.EC5.mapping.getDefaultMapIndex() === mapIndex;

        tables.each(function (index, table) {

            var currentMapping = mapping[mapIndex];
            var formRef = $(table).attr('data-form-ref');
            var rows = $(table).find('tbody tr');
            var rowIndex = 0;

            //keep track of duplicates
            formMapTos[formRef] = [];

            currentMapping.forms[formRef] = {};

            while (rowIndex < rows.length) {

                var currentForm = currentMapping.forms[formRef];
                var inputRef = $(rows[rowIndex]).attr('data-input-ref');
                var mapToInput = $(rows[rowIndex]).find('.mapping-data__map-to input');
                var mapTo = mapToInput.val();
                var hide = $(rows[rowIndex]).find('.mapping-data__hide-checkbox input').is(':checked');

                mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                //is it a question or possible answers?
                if (inputRef) {

                    //it is a top level question
                    currentForm[inputRef] = {};
                    currentForm[inputRef].possible_answers = {};
                    currentForm[inputRef].branch = {};
                    currentForm[inputRef].group = {};
                    currentForm[inputRef].map_to = mapTo;
                    currentForm[inputRef].hide = hide;

                    //cache mapTo value
                    if (mapTo !== undefined) {
                        formMapTos[formRef].push(mapTo);
                    }

                    //if the mapping value is invalid, show input error
                    if (!mapTo.match(mappingToRegex)) {
                        isMappingValid = false;
                        $(rows[rowIndex]).find('.mapping-data__map-to input').parent().addClass('has-error');
                    }

                    switch ($(rows[rowIndex]).attr('data-input-type')) {

                        case 'branch':
                            //get the next elements with [data-is-branch-input] to get all the branch inputs
                            var branchInputs = $(rows[rowIndex]).nextUntil('[data-top-level-input]', '[data-is-branch-input]');

                            currentForm[inputRef].branch = {};

                            //loop all branch inputs
                            $(branchInputs).each(function (branchIndex, branchInput) {

                                var branchInputRef = $(branchInput).attr('data-input-ref');
                                var currentBranch;
                                var mapToInput = $(branchInput).find('.mapping-data__map-to input');
                                var mapTo = mapToInput.val();
                                var hide = $(branchInput).find('.mapping-data__hide-checkbox input').is(':checked');

                                //trim any mapTo value (if found)
                                mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                //is it a question or possible answers?
                                if (branchInputRef) {
                                    //this row is a branch question
                                    currentForm[inputRef].branch[branchInputRef] = {};
                                    currentBranch = currentForm[inputRef].branch[branchInputRef];
                                    currentBranch.possible_answers = {};
                                    currentBranch.branch = {};
                                    currentBranch.group = {};
                                    currentBranch.map_to = mapTo;
                                    currentBranch.hide = hide;

                                    //cache mapTo value
                                    if (mapTo !== undefined) {
                                        formMapTos[formRef].push(mapTo);
                                    }

                                    if (!mapTo.match(mappingToRegex)) {
                                        isMappingValid = false;
                                        $(branchInput).find('.mapping-data__map-to input').parent().addClass('has-error');
                                    }

                                    //do we have a nested group?
                                    if ($(branchInput).attr('data-input-type') === 'group') {

                                        //grab all nested group inputs
                                        /**
                                         * Careful here. Stop when:
                                         * - we get a "data-is-branch-input" i.e the user added another branch input after the nedted group input
                                         * - we get "data-top-level-input" i.e the nested group is the last input of the branch and the next element is a top level input
                                         *
                                         */

                                        var nestedGroupInputs = $(branchInput).nextUntil('[data-is-branch-input], [data-top-level-input]', '[data-is-group-input]');

                                        currentBranch.group = {};

                                        $(nestedGroupInputs).each(function (nestedGroupIndex, nestedGroupInput) {

                                            var nestedGroupInputRef = $(nestedGroupInput).attr('data-input-ref');
                                            var mapToInput = $(nestedGroupInput).find('.mapping-data__map-to input');
                                            var mapTo = mapToInput.val();
                                            var hide = $(nestedGroupInput).find('.mapping-data__hide-checkbox input').is(':checked');
                                            //trim any mapTo value (if found)
                                            mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                            //is it a question or possible answers?
                                            if (nestedGroupInputRef) {

                                                currentBranch.group[nestedGroupInputRef] = {};
                                                currentBranch.group[nestedGroupInputRef].possible_answers = {};
                                                currentBranch.group[nestedGroupInputRef].branch = {};
                                                currentBranch.group[nestedGroupInputRef].group = {};
                                                currentBranch.group[nestedGroupInputRef].map_to = mapTo;
                                                currentBranch.group[nestedGroupInputRef].hide = hide;

                                                //cache mapTo value
                                                if (mapTo !== undefined) {
                                                    formMapTos[formRef].push(mapTo);
                                                }

                                                if (!mapTo.match(mappingToRegex)) {
                                                    isMappingValid = false;
                                                    $(nestedGroupInput).find('.mapping-data__map-to input').parent().addClass('has-error');
                                                }
                                            }
                                            else {
                                                //this row is the possible answers for the current nested group input question
                                                $(nestedGroupInput).find('.mapping-data__possible_answer__map-to input').each(function (index, inputItem) {

                                                    //get previous row nested group input ref
                                                    var nestedGroupInputRef = $(nestedGroupInput).prev().attr('data-input-ref');
                                                    var prevNestedGroupInput = currentForm[inputRef].branch[branchInputRef].group[nestedGroupInputRef];
                                                    var answerRef = $(inputItem).attr('data-answer-ref');
                                                    var mapTo = $(inputItem).val();

                                                    //trim any mapTo value (if found)
                                                    mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                                    if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                                                        isMappingValid = false;
                                                        $(inputItem).parent().addClass('has-error');
                                                    }

                                                    prevNestedGroupInput.possible_answers[answerRef] = {};
                                                    prevNestedGroupInput.possible_answers[answerRef].map_to = mapTo;
                                                });
                                            }
                                        });

                                        //skip by nestedGroupInputs.length to skip to the next branch input
                                        rowIndex += nestedGroupInputs.length;
                                    }
                                }
                                else {
                                    //this row is the possible answers for the current branch question
                                    $(branchInput)
                                        .find('.mapping-data__possible_answer__map-to input')
                                        .each(function (index, inputItem) {

                                            //get previous row input ref
                                            var branchInputRef = $(branchInput).prev().attr('data-input-ref');
                                            var prevBranchInput = currentForm[inputRef].branch[branchInputRef];
                                            var answerRef = $(inputItem).attr('data-answer-ref');
                                            var mapTo = $(inputItem).val();

                                            //trim any mapTo value (if found)
                                            mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                            if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                                                isMappingValid = false;
                                                $(inputItem).parent().addClass('has-error');
                                            }

                                            prevBranchInput.possible_answers[answerRef] = {};
                                            prevBranchInput.possible_answers[answerRef].map_to = mapTo;
                                        });
                                }
                            });

                            //skip by branchInputs.length to skip to the next top level input
                            rowIndex += branchInputs.length;
                            break;

                        case 'group':

                            var groupInputs = $(rows[rowIndex]).nextUntil('[data-top-level-input]', '[data-is-group-input]');

                            $(groupInputs).each(function (groupIndex, groupInput) {

                                var groupInputRef = $(groupInput).attr('data-input-ref');
                                var currentGroup;
                                var mapToInput = $(groupInput).find('.mapping-data__map-to input');
                                var mapTo = mapToInput.val();

                                //trim any mapTo value (if found)
                                mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                var hide = $(groupInput).find('.mapping-data__hide-checkbox input').is(':checked');

                                //is it a question or possible answers?
                                if (groupInputRef) {
                                    //this row is a branch question
                                    currentForm[inputRef].group[groupInputRef] = {};
                                    currentGroup = currentForm[inputRef].group[groupInputRef];
                                    currentGroup.possible_answers = {};
                                    currentGroup.branch = {};
                                    currentGroup.group = {};
                                    currentGroup.map_to = mapTo;
                                    currentGroup.hide = hide;

                                    //cache mapTo value
                                    if (mapTo !== undefined) {
                                        formMapTos[formRef].push(mapTo);
                                    }

                                    if (!mapTo.match(mappingToRegex)) {
                                        isMappingValid = false;
                                        $(groupInput).find('.mapping-data__map-to input').parent().addClass('has-error');
                                    }
                                }
                                else {
                                    //this row is the possible answers for the current group question
                                    $(groupInput).find('.mapping-data__possible_answer__map-to input').each(function (index, inputItem) {

                                        //get previous row input ref
                                        var groupInputRef = $(groupInput).prev().attr('data-input-ref');
                                        var prevGroupInput = currentForm[inputRef].group[groupInputRef];
                                        var answerRef = $(inputItem).attr('data-answer-ref');
                                        var mapTo = $(inputItem).val();
                                        //trim any mapTo value (if found)
                                        mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                        if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                                            isMappingValid = false;
                                            $(inputItem).parent().addClass('has-error');
                                        }

                                        prevGroupInput.possible_answers[answerRef] = {};
                                        prevGroupInput.possible_answers[answerRef].map_to = mapTo;
                                    });
                                }
                            });

                            //skip by branchInputs.length to skip to the next top level input
                            rowIndex += groupInputs.length;
                            break;
                    }
                    rowIndex++;
                }
                else {
                    //get possible answers for current input
                    $(rows[rowIndex]).find('.mapping-data__possible_answer__map-to input').each(function (index, inputItem) {

                        var inputRef = $(rows[rowIndex - 1]).attr('data-input-ref');
                        var answerRef = $(inputItem).attr('data-answer-ref');
                        var mapTo = $(inputItem).val();
                        //trim any mapTo value (if found)
                        mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                        if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                            isMappingValid = false;
                            $(inputItem).parent().addClass('has-error');
                        }

                        currentForm[inputRef].possible_answers[answerRef] = {};
                        currentForm[inputRef].possible_answers[answerRef].map_to = mapTo;

                    });
                    rowIndex++;
                }
            }

            console.log(JSON.stringify(formMapTos));

            $.each(formMapTos, function (formIndex, form) {

                //find duplicated identfier if any
                var duplicates = [];

                $.each(form, function (itemIndex, item) {

                    //do we have a duplicate?
                    if ($.inArray(item.trim(), duplicates) === -1) {
                        //no, add it
                        duplicates.push(item.trim());
                    }
                    else {
                        //we have a duplicate, bail out
                        hasDuplicateIdentifier = { key: item, formRef: formIndex };
                        isMappingValid = false;
                        console.log(item);
                        return false;
                    }
                });
            });
        });

        console.log(JSON.stringify(mapping[mapIndex]));

        if (isMappingValid) {
            //post mapping
            window.EC5.projectUtils.postRequest(postURL + '/update', {
                action: action,
                map_index: mapIndex,
                mapping: mapping[mapIndex]
            }).done(function (response) {
                // console.log(JSON.stringify(response));
                window.EC5.overlay.fadeOut();
                window.EC5.toast.showSuccess(mapName + ' updated');
            }).fail(function (error) {
                window.EC5.overlay.fadeOut();
                window.EC5.projectUtils.showErrors(error);
            });
        }
        else {
            if (hasDuplicateIdentifier) {
                //highlight duplicate identifiers in the dom
                tables.each(function (index, table) {

                    if ($(table).data('form-ref') === hasDuplicateIdentifier.formRef) {
                        var rows = $(table).find('tbody tr');

                        rows.each(function (rowIndex, row) {

                            if ($(row).find('.mapping-data__map-to input').val() === hasDuplicateIdentifier.key) {
                                $(row).find('.mapping-data__map-to').addClass('has-error');
                            }
                        });
                    }
                });

                window.EC5.toast.showError(mapName + ' has got duplicate identifier: ' + hasDuplicateIdentifier.key);
            }
            else {
                window.EC5.toast.showError(mapName + ' has got invalid identifier(s)');
            }
            window.EC5.overlay.fadeOut();
        }
    };

}(window.EC5.mapping));
