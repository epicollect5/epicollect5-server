'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.getDefaultMapIndex = function () {
        var defaultMapIndex = 0;
        var tabs = $('.map-data__tabs li');

        tabs.each(function (index, tab) {

            var mapIndex = $(tab).attr('data-map-index');
            if (!$(tab).find('.map-data__default-pin').hasClass('invisible')) {
                defaultMapIndex = mapIndex;
                return false;
            }
        });
        return parseInt(defaultMapIndex, 10);
    };

}(window.EC5.mapping));
