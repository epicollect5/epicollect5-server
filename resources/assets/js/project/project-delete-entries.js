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
        var chunkSizeEntries = parseInt(page.data('chunk-size-entries'), 10);
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

            // Cap deleted at total to prevent percentage > 100%
            var actualDeleted = Math.min(deleted, total);
            var percentage = ((actualDeleted / total) * 50).toFixed(1);
            var percentageReverse = ((1 - (actualDeleted / total)) * 50).toFixed(1);

            progressBarEntries.attr('aria-valuenow', percentageReverse);
            progressBarEntries.css('width', percentageReverse + '%');

            counterWrapperEntries.find('.counter-percentage').text(percentage * 2 + '%');
            counterWrapperEntries.find('.counter-deleted').text(actualDeleted);
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

            // Cap deleted at total to prevent percentage > 100%
            var actualDeleted = Math.min(deleted, total);
            var percentage = ((actualDeleted / total) * 50).toFixed(1);
            var percentageReverse = ((1 - (actualDeleted / total)) * 50).toFixed(1);

            progressBarMedia.attr('aria-valuenow', percentageReverse);
            progressBarMedia.css('width', percentageReverse + '%');

            counterWrapperMedia.find('.counter-percentage').text(percentage * 2 + '%');
            counterWrapperMedia.find('.counter-deleted').text(actualDeleted);
            counterWrapperMedia.find('.counter-total').text(total);
        }
    }
});
