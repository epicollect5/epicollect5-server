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
