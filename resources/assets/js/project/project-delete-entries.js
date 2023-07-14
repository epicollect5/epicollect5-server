$(document).ready(function () {
    //enable only on page-entries-deletion
    if ($('.page-entries-deletion').length > 0) {

        var projectName = $('.page-entries-deletion').find('.project-name').text();
        var wrapper = $('.delete-entries');
        var modal = $('#entriesDeletion');

        wrapper.submit(function (e) {
            // Don't allow user to submit if the project
            // name they've typed is incorrect
            if (projectName.trim() !== $('#project-name').val().trim()) {
                e.preventDefault();
            }
            modal.modal({ backdrop: 'static', keyboard: false }, 'show');
        });

        wrapper.on('keyup', '#project-name', function (e) {
            e.preventDefault();
            // If the project name is correct, enable submit button
            $('.submit-delete-entries')
                .attr('disabled', !(projectName.trim() === $(this).val().trim()));
        });
    }
});
