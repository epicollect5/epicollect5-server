'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.delete = function (postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn, isDefault, tabContentWrapper) {

        modal.find('.map-data__modal__mapping-name').hide();
        modal.find('.map-data__modal-delete-warning').removeClass('hidden');
        modal.find('map-data__modal__name-rules').addClass('hidden');

        confirm_btn.off().on('click', function () {
            window.EC5.toast.clear();
            window.EC5.projectUtils.postRequest(postURL + '/delete', {
                map_index: mapIndex
            }).done(function (data) {

                var tabs = $('.map-data__tabs');

                window.EC5.toast.showSuccess(mapName + ' deleted');

                //remove active tab
                activeTab.remove();

                //remove mapping markup
                tabContentWrapper.find('#map-data-tabcontent-' + mapIndex).remove();

                //set the EC5 Auto as the selected tab
                tabs.find('a[href="#map-data-tabcontent-0"]').tab('show');

                //if we deleted the default map, set EC5 Auto as default (the first tab)
                if (isDefault) {
                    tabs.find('.map-data__default-pin').first().removeClass('invisible');
                }

                //show "Add Mapping" tab button
                $('.map-data__tabs li.map-data__tabs__add-form').removeClass('hidden');

                modal.modal('hide');

            }).fail(function (error) {
                console.log(error);
                window.EC5.projectUtils.showErrors(error);
            });
        });
    };

}(window.EC5.mapping));
