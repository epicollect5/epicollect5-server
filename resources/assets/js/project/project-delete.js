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
