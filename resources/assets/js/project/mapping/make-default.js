'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.makeDefault = function (postURL, action, mapIndex, mapName, activeTab, inactiveTabs) {

        window.EC5.toast.clear();

        window.EC5.projectUtils.postRequest(postURL + '/update', {
            action: action,
            map_index: mapIndex
        }).done(function (response) {
            console.log(JSON.stringify(response));
            window.EC5.toast.showSuccess(mapName + ' set as default');

            //update tabs button markup to "pin' the default one only, which is the active tab
            activeTab.find('.map-data__default-pin').removeClass('invisible');
            //hide any other pin
            inactiveTabs.each(function (index, tab) {
                $(tab).find('.map-data__default-pin').addClass('invisible');
            });
        }).fail(function (error) {
            window.EC5.projectUtils.showErrors(error);
        });
    };

}(window.EC5.mapping));
