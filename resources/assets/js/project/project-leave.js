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
