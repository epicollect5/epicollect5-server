'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //get default map index, the one with the visible pin on the tabs buttons
    module.addMapping = function (postURL, modal, confirm_btn, maxAmountOfMaps, tabContentWrapper) {

        modal.find('.map-data__modal__mapping-name').show();
        modal.find('.map-data__modal-delete-warning').addClass('hidden');
        modal.find('map-data__modal__name-rules').removeClass('hidden');

        //set empty name in input field
        modal.find('.map-data__modal__mapping-name').val('');

        confirm_btn.off().on('click', function () {

            window.EC5.toast.clear();

            // Validate mapping name
            var name = modal.find('.map-data__modal__mapping-name').val();

            window.EC5.projectUtils.postRequest(postURL, {
                name: name,
                is_default: false
            }).done(function (response) {
                console.log(response);
                window.EC5.toast.showSuccess(name + ' mapping added');

                var newMapIndex = response.data.map_index;

                //refresh ui adding the new mapping tab
                var html = '<li role="presentation" data-map-index="' + newMapIndex + '" data-map-name="' + name + '">';
                html += ' <a href="#map-data-tabcontent-' + newMapIndex + '" role="tab" data-toggle="tab">';
                html += '<span class="map-data__map-name">' + name + '&nbsp;</span>';
                html += '<i class="fa fa-thumb-tack invisible" aria-hidden="true"></i>';
                html += '</a>';
                html += '</li>';

                $('.map-data__tabs li').not('.map-data__tabs__add-form').last().after(html);
                //remove add mapping tab if we reached the max number of maps

                //mapping object can be object or array as we do not keep the indexes in sequence
                //and the json parsing  will either generate an array or an object dependeing on the numeric keys
                if ($.isArray(response.data.mapping)) {
                    if (response.data.mapping.length === maxAmountOfMaps) {
                        $('.map-data__tabs li.map-data__tabs__add-form').addClass('hidden');
                    }
                }
                else {
                    if (Object.keys(response.data.mapping).length === maxAmountOfMaps) {
                        $('.map-data__tabs li.map-data__tabs__add-form').addClass('hidden');
                    }
                }
                //add mapping markup, cloning the default EC5_AUTO from the dom. (with events)
                var defaultMappingMarkup = tabContentWrapper.find('#map-data-tabcontent-0').clone(true, true);

                //amend id
                defaultMappingMarkup.attr('id', 'map-data-tabcontent-' + newMapIndex);

                //amend data attributes
                defaultMappingMarkup.attr('data-map-index', newMapIndex);
                defaultMappingMarkup.attr('data-map-name', name);

                //update map index in dom
                defaultMappingMarkup.find('[data-map-index]').each(function (index, element) {
                    $(element).attr('data-map-index', newMapIndex);
                });

                //activate all buttons
                defaultMappingMarkup.find('.map-data--actions__btns .btn').each(function (index, element) {
                    $(element).prop('disabled', false);
                });

                //activate all inputs per each row
                defaultMappingMarkup.find('.table-responsive').each(function (index, element) {
                        var inputElement = $(element).find('tr td input');
                        inputElement.prop('disabled', false).prop('readonly', false);
                    }
                );

                //add markup
                tabContentWrapper.append(defaultMappingMarkup);

                //switch to newly created mapping:

                //hide active tab
                $('.map-data__tabs li.active').removeClass('active');
                $('.page-mapping-data .tab-content .tab-pane[data-map-index="0"]').removeClass('active in');

                //show new tab
                $('.map-data__tabs li[data-map-index="' + newMapIndex + '"]').addClass('active');
                $('.map-data__tabs li[data-map-index="' + newMapIndex + '"] a').tab('show');

                window.setTimeout(function () {
                    modal.modal('hide');
                }, 250);

            }).fail(function (error) {
                console.log(error);
                window.EC5.projectUtils.showErrors(error);
            });
        });
    };

}(window.EC5.mapping));
