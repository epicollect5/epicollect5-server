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
