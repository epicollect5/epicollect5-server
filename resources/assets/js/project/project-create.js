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
});



