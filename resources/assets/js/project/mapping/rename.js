'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.rename = function (postURL, action, mapIndex, mapName, activeTab, modal, confirm_btn) {

        modal.find('.map-data__modal__mapping-name').show();
        modal.find('.map-data__modal-delete-warning').addClass('hidden');
        modal.find('map-data__modal__name-rules').removeClass('hidden');
        //set existing name in input field
        modal.find('.map-data__modal__mapping-name').val(mapName);

        confirm_btn.off().on('click', function () {

            window.EC5.toast.clear();

            // Validate mapping name
            var name = modal.find('.map-data__modal__mapping-name').val();

            window.EC5.projectUtils.postRequest(postURL + '/update', {
                action: action,
                map_index: mapIndex,
                name: name
            }).done(function (data) {

                activeTab.attr('data-map-name', name);
                activeTab.find('.map-data__map-name').text(name);

                //dismiss modal on success
                modal.modal('hide');

                window.EC5.toast.showSuccess(name + ' renamed');
            }).fail(function (error) {
                console.log(error);
                window.EC5.projectUtils.showErrors(error);
            });
        });
    };

}(window.EC5.mapping));
