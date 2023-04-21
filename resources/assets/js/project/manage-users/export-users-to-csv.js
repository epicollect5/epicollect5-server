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
