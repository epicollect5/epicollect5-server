'use strict';
window.EC5 = window.EC5 || {};

$(document).ready(function () {

    var maxAmountOfMaps = 4;
    var pageMappingData = $('.page-mapping-data');
    var postURL = pageMappingData.data('url');

    //do not do anything if not on the mapping page
    if (pageMappingData.length === 0) {
        return;
    }

    //bind form selection dropdown
    $('.form-list-selection').on('click', 'li', function () {

        var selectedForm = $(this);
        var formRef = $(selectedForm).data('form-ref');
        var mapIndex = $('.map-data__tabs li.active').data('map-index');
        var tabContent =  $('#map-data-tabcontent-' + mapIndex + ' .panel-body');
        //set selected option text as the text of the dropdown
        selectedForm
            .parents('.form-list-selection')
            .find('.dropdown-toggle .form-list-selection__form-name')
            .text(selectedForm.text());

        //show mapping for selected form
        tabContent.find('.table-responsive').fadeOut().addClass('hidden');
        tabContent.find('[data-form-ref="' + formRef + '"]').hide().removeClass('hidden').fadeIn();
    });

    //handle update/make default
    $('.map-data--actions__btns').on('click', '[data-toggle="push"]', function () {
        var target = $(this);
        var action = target.data('action');
        var activeTab = $('.map-data__tabs li.active');
        var inactiveTabs = $('.map-data__tabs li').not('.active').not('.map-data__tabs__add-form');
        var mapIndex = activeTab.data('map-index');
        var mapName = activeTab.data('map-name');
        console.log(action);

        switch (action) {
            case 'make-default':
                window.EC5.mapping.makeDefault(postURL, action, mapIndex, mapName, activeTab, inactiveTabs);
                break;
            case 'update':
                window.EC5.mapping.update(postURL, action, mapIndex, mapName);
                break;
        }
    });

    //Handle modals
    $('#modal__mapping-data').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var action = button.data('action'); //action to perform
        var modal = $(this);
        var title = button.data('trans');
        var confirm_btn = modal.find('.map-data__modal__save-btn');
        var activeTab = $('.map-data__tabs li.active');
        var isDefault = !activeTab.find('.map-data__default-pin').hasClass('invisible');
        var mapIndex = activeTab.data('map-index');
        var mapName = activeTab.data('map-name');
        var tabContentWrapper = $('.page-mapping-data .tab-content');

        modal.find('.modal-title').text(title);

        switch (action) {
            case 'delete':
                window.EC5.mapping.delete(postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn, isDefault, tabContentWrapper);
                break;
            case 'rename':
                window.EC5.mapping.rename(postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn);
                break;
            case 'add-mapping':
                window.EC5.mapping.addMapping(postURL, modal, confirm_btn, maxAmountOfMaps, tabContentWrapper, tabContentWrapper);
                break;
        }
    });


    //get active tab and set the active tab content on page load
    //$('.map-data__tabs li.active a').tab('show');
});
