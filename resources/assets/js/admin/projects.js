'use strict';
window.EC5 = window.EC5 || {};
window.EC5.admin = window.EC5.admin || {};
window.EC5.admin.projects = window.EC5.admin.projects || {};

/**
 * Projects module
 */
(function projects(module) {

    /**
     * Update the user's project role
     *
     * @param role
     * @param projectId
     */
    module.updateRole = function (role, projectId) {

        // Make ajax request to load users
        $.ajax({
            url: window.EC5.SITE_URL + '/admin/update-user-project-role',
            type: 'POST',
            dataType: 'json',
            data: {role: role, project_id: projectId}
        }).done(function (data) {
            window.EC5.toast.showSuccess(data.data.title);
        }).fail(window.EC5.showError);
    };

    /**
     * Function for asynchronously retrieving the
     * list of projects, based on any search/filter
     * criteria, with pagination
     */
    module.getProjects = function (params) {

        var deferred = new $.Deferred();

        console.log(params);

        // Make ajax request to load projects
        $.ajax({
            url: params.url,
            type: 'GET',
            dataType: 'json',
            data: params
        }).done(function (response) {
            deferred.resolve(response);
        }).fail(function (error) {
            deferred.reject(error);
        });

        return deferred.promise();
    };

})(window.EC5.admin.projects);

$(document).ready(function () {

    var endpointUrl = $('.url').data('js');

    var capitalize = function (string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    };

    var throttle = (function () {
        var timer = 0;
        return function (callback, ms) {
            clearTimeout(timer);
            timer = setTimeout(callback, ms);
        };
    })();

    var params = {
        url: endpointUrl,
        page: 1,
        name: '',
        access: '',
        visibility: ''
    };

    var searchBar = $('.page-admin .projects-list__filter-controls .projects-list__project-search');
    var filterControls = $(' .page-admin .projects-list__filter-controls_dropdowns');
    var accessDropdownToggle = filterControls.find('.filter-controls__access .dropdown-toggle');
    var accessDropdownMenu = filterControls.find('.filter-controls__access .dropdown-menu');
    var visibilityDropdownToggle = filterControls.find('.filter-controls__visibility .dropdown-toggle');
    var visibilityDropdownMenu = filterControls.find('.filter-controls__visibility .dropdown-menu');
    var projectsList = $(' .page-admin .projects-list');
    var loader = $('.page-admin .projects-loader');

    _getStorageStats();

    // Loop through each table row that has a project reference
    function _getStorageStats() {
        $('table td[data-project-slug]').each(function () {
            var $row = $(this);
            var projectSlug = $row.data('project-slug'); // from data-project-ref attribute

            // Show spinner while loading
            $row.find('.spinner').removeClass('hidden').fadeIn();

            $.get(window.EC5.SITE_URL + '/api/internal/counters/media/' + projectSlug, function (response) {
                var totalMedia = response.data.counters.total;


                $row.find('.spinner')
                    .addClass('hidden')
                    .fadeOut(function () {
                        $row.find('.counter-total')
                            .text(_formatBytes(totalMedia))
                            .removeClass('hidden')
                            .fadeIn();
                    });
            });
        });
    }

    projectsList.on('change', '.project-roles', function () {

        var projectId = $(this).data('projectId');
        var role = $(this).val();
        // Update role
        window.EC5.admin.projects.updateRole(role, projectId);
    });

    //filter projects based on search text
    searchBar.keyup(function () {

        params.name = $(this).val().trim();

        //get current selection for visibility and access
        var access = accessDropdownToggle.data('selected-value');
        var visibility = visibilityDropdownToggle.data('selected-value');

        //filter "any"
        params.access = (access === 'any') ? '' : access;
        params.visibility = (visibility === 'any') ? '' : visibility;

        _filterProjects(500);
    });

    //filter based on access value
    accessDropdownMenu.on('click', 'li', function () {

        var selected = $(this).data('filter-value');

        params.access = selected === 'any' ? '' : selected;

        accessDropdownToggle.data('selected-value', selected);
        accessDropdownToggle.parent().find('.dropdown-text').text(capitalize(selected));

        console.log(params);

        _filterProjects(0);

    });

    //filter based on visibility value
    visibilityDropdownMenu.on('click', 'li', function () {

        var selected = $(this).data('filter-value');

        params.visibility = selected === 'any' ? '' : selected;

        visibilityDropdownToggle.data('selected-value', selected);
        visibilityDropdownToggle.parent().find('.dropdown-text').text(capitalize(selected));

        _filterProjects(0);

    });

    //intercept click on pagination links to send ajax request
    //important: re-bind event as empty() removes it!!!!
    $('.pagination').on('click', 'a', onPaginationClick);

    function onPaginationClick(e) {
        e.preventDefault();

        var visibility = visibilityDropdownToggle.data('selected-value');
        var access = accessDropdownToggle.data('selected-value');

        params.page = $(e.target).attr('href').split('page=')[1];
        params.name = searchBar.val().trim();
        params.access = access === 'any' ? '' : access;
        params.visibility = visibility === 'any' ? '' : visibility;

        _filterProjects(0);
        _getStorageStats();
    }

    //perform project filtering
    function _filterProjects(delay) {

        loader.removeClass('hidden');
        projectsList.find('.projects__table__wrapper').empty();

        throttle(function () {
            window.EC5.admin.projects.getProjects(params).then(function (response) {
                //hide loader and show projects
                loader.addClass('hidden');
                projectsList.hide().append(response).fadeIn(500);

                //important: re-bind event as empty() removes it!!!!
                $('.pagination').on('click', 'a', onPaginationClick);
            }, function (error) {
                console.log(error);
            });
        }, delay);

        _getStorageStats();
    }

    function _formatBytes(bytes, precision) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(precision)) + ' ' + sizes[i];
    }


});
