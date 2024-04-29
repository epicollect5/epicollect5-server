$(document).ready(function () {
    //enable only on page-entries-deletion
    if ($('.page-entries-deletion').length > 0) {

        var projectName = $('.page-entries-deletion').find('.project-name').text();
        var wrapper = $('.delete-entries-wrapper');
        var counterWrapper = $('.counter-wrapper');
        var modal = $('#modal-deletion');
        var backURL = $('.btn-cancel-deletion').attr('href');
        var chunkSize = 10000;//to be adjusted as needed

        wrapper.on('click', '.btn-delete-entries', function (e) {
            // Don't allow user to submit if the project
            // name they've typed is incorrect
            if (projectName.trim() !== $('#project-name').val().trim()) {
                e.preventDefault();
            }

            var projectSlug = window.EC5.projectUtils.slugify(projectName.trim());
            var endpoint = window.EC5.SITE_URL + '/api/internal/deletion/' + projectSlug;

            var payload = {
                data: {
                    'project-name': projectName.trim(),
                }
            }

            modal.modal({backdrop: 'static', keyboard: false}, 'show');
            // Add the event listener to the beforeunload event
            window.addEventListener('beforeunload', handleBeforeUnload);

            // Call the recursive function to start the deletion process
            _deleteEntriesRecursively(endpoint, payload, projectSlug);
        });

        wrapper.on('keyup', '#project-name', function (e) {
            e.preventDefault();
            // If the project name is correct, enable submit button
            $('.btn-delete-entries').attr('disabled', !(projectName.trim() === $(this).val().trim()));
        });

        // Define a function that handles the beforeunload event
        function handleBeforeUnload(event) {
            event.returnValue = 'ciao'; // Triggers the confirmation prompt
        }

        var deleted = 0;
        var total = parseInt(wrapper.data('total-entries'));
        var remaining = total;
        counterWrapper.find('.counter-total').text(remaining);

        function _deleteEntriesRecursively(endpoint, payload, projectSlug) {
            // Make the POST request to delete entries
            $.when(window.EC5.projectUtils.postRequest(endpoint, payload))
                .done(function (response) {
                    if (remaining > chunkSize) {
                        deleted += chunkSize;
                    } else {
                        deleted += remaining;
                    }

                    updateProgressBar(deleted, remaining, total);

                    // Check if there are more entries to delete
                    $.get(window.EC5.SITE_URL + '/api/internal/counters/entries/' + projectSlug, function (response) {
                        try {
                            if (response.data.counters.total > 0) {
                                // If there are more entries to delete, call the function recursively
                                remaining = response.data.counters.total;
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

        // Function to update the progress bar
        function updateProgressBar(deleted, remaining, total) {
            //no entries? bail out
            if (remaining === 0) {
                counterWrapper.find('.counter-percentage').text('100%');
                counterWrapper.find('.counter-deleted').text(total);
                return;
            }
            // Calculate the percentage of progress
            var percentage = ((deleted / total) * 100).toFixed(2);
            var percentageReverse = ((1 - (deleted / total)) * 100).toFixed(2);
            // Get the progress bar element
            var progressBar = $('.progress-bar__modal-deletion');

            // Update the aria-valuenow attribute and the style width
            progressBar.attr('aria-valuenow', percentageReverse);
            progressBar.css('width', percentageReverse + '%');

            // Update the text inside the progress bar
            counterWrapper.find('.counter-percentage').text(percentage + '%');
            counterWrapper.find('.counter-deleted').text(deleted);
            counterWrapper.find('.counter-total').text(total);
        }
    }
});
