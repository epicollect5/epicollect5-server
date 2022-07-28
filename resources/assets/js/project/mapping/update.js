'use strict';
window.EC5 = window.EC5 || {};
window.EC5.mapping = window.EC5.mapping || {};

(function mapping(module) {

    //build mapping object only for active mapping tab
    module.update = function (postURL, action, mapIndex, mapName) {

        window.EC5.toast.clear();
        window.EC5.overlay.fadeIn();

        var mapping = {};
        var tab_panel = $('.page-mapping-data .tab-content .tab-pane[data-map-index="' + mapIndex + '"]');
        var isMappingValid = true;
        var hasDuplicateIdentifier = null;
        var formMapTos = {};

        //alphanumeric with underscore only, 1 to 20 length
        //imp: we do not allow '-' because of the json export
        var mappingToRegex = /^[a-zA-Z0-9_]{1,20}$/;
        var mappingPossibleAnswerToRegex = /^((?![<>]).){1,150}$/;

        var tables = tab_panel.find('.panel-body .table-responsive');

        tab_panel.find('input').parent().removeClass('has-error');

        mapping[mapIndex] = {};
        mapping[mapIndex].name = mapName;
        mapping[mapIndex].forms = {};
        mapping[mapIndex].map_index = mapIndex;
        mapping[mapIndex].is_default = window.EC5.mapping.getDefaultMapIndex() === mapIndex;

        tables.each(function (index, table) {

            var currentMapping = mapping[mapIndex];
            var formRef = $(table).attr('data-form-ref');
            var rows = $(table).find('tbody tr');
            var rowIndex = 0;

            //keep track of duplicates
            formMapTos[formRef] = [];

            currentMapping.forms[formRef] = {};

            while (rowIndex < rows.length) {

                var currentForm = currentMapping.forms[formRef];
                var inputRef = $(rows[rowIndex]).attr('data-input-ref');
                var mapToInput = $(rows[rowIndex]).find('.mapping-data__map-to input');
                var mapTo = mapToInput.val();
                var hide = $(rows[rowIndex]).find('.mapping-data__hide-checkbox input').is(':checked');

                mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                //is it a question or possible answers?
                if (inputRef) {

                    //it is a top level question
                    currentForm[inputRef] = {};
                    currentForm[inputRef].possible_answers = {};
                    currentForm[inputRef].branch = {};
                    currentForm[inputRef].group = {};
                    currentForm[inputRef].map_to = mapTo;
                    currentForm[inputRef].hide = hide;

                    //cache mapTo value
                    if (mapTo !== undefined) {
                        formMapTos[formRef].push(mapTo);
                    }

                    //if the mapping value is invalid, show input error
                    if (!mapTo.match(mappingToRegex)) {
                        isMappingValid = false;
                        $(rows[rowIndex]).find('.mapping-data__map-to input').parent().addClass('has-error');
                    }

                    switch ($(rows[rowIndex]).attr('data-input-type')) {

                        case 'branch':
                            //get the next elements with [data-is-branch-input] to get all the branch inputs
                            var branchInputs = $(rows[rowIndex]).nextUntil('[data-top-level-input]', '[data-is-branch-input]');

                            currentForm[inputRef].branch = {};

                            //loop all branch inputs
                            $(branchInputs).each(function (branchIndex, branchInput) {

                                var branchInputRef = $(branchInput).attr('data-input-ref');
                                var currentBranch;
                                var mapToInput = $(branchInput).find('.mapping-data__map-to input');
                                var mapTo = mapToInput.val();
                                var hide = $(branchInput).find('.mapping-data__hide-checkbox input').is(':checked');

                                //trim any mapTo value (if found)
                                mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                //is it a question or possible answers?
                                if (branchInputRef) {
                                    //this row is a branch question
                                    currentForm[inputRef].branch[branchInputRef] = {};
                                    currentBranch = currentForm[inputRef].branch[branchInputRef];
                                    currentBranch.possible_answers = {};
                                    currentBranch.branch = {};
                                    currentBranch.group = {};
                                    currentBranch.map_to = mapTo;
                                    currentBranch.hide = hide;

                                    //cache mapTo value
                                    if (mapTo !== undefined) {
                                        formMapTos[formRef].push(mapTo);
                                    }

                                    if (!mapTo.match(mappingToRegex)) {
                                        isMappingValid = false;
                                        $(branchInput).find('.mapping-data__map-to input').parent().addClass('has-error');
                                    }

                                    //do we have a nested group?
                                    if ($(branchInput).attr('data-input-type') === 'group') {

                                        //grab all nested group inputs
                                        /**
                                         * Careful here. Stop when:
                                         * - we get a "data-is-branch-input" i.e the user added another branch input after the nedted group input
                                         * - we get "data-top-level-input" i.e the nested group is the last input of the branch and the next element is a top level input
                                         *
                                         */

                                        var nestedGroupInputs = $(branchInput).nextUntil('[data-is-branch-input], [data-top-level-input]', '[data-is-group-input]');

                                        currentBranch.group = {};

                                        $(nestedGroupInputs).each(function (nestedGroupIndex, nestedGroupInput) {

                                            var nestedGroupInputRef = $(nestedGroupInput).attr('data-input-ref');
                                            var mapToInput = $(nestedGroupInput).find('.mapping-data__map-to input');
                                            var mapTo = mapToInput.val();
                                            var hide = $(nestedGroupInput).find('.mapping-data__hide-checkbox input').is(':checked');
                                            //trim any mapTo value (if found)
                                            mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                            //is it a question or possible answers?
                                            if (nestedGroupInputRef) {

                                                currentBranch.group[nestedGroupInputRef] = {};
                                                currentBranch.group[nestedGroupInputRef].possible_answers = {};
                                                currentBranch.group[nestedGroupInputRef].branch = {};
                                                currentBranch.group[nestedGroupInputRef].group = {};
                                                currentBranch.group[nestedGroupInputRef].map_to = mapTo;
                                                currentBranch.group[nestedGroupInputRef].hide = hide;

                                                //cache mapTo value
                                                if (mapTo !== undefined) {
                                                    formMapTos[formRef].push(mapTo);
                                                }

                                                if (!mapTo.match(mappingToRegex)) {
                                                    isMappingValid = false;
                                                    $(nestedGroupInput).find('.mapping-data__map-to input').parent().addClass('has-error');
                                                }
                                            }
                                            else {
                                                //this row is the possible answers for the current nested group input question
                                                $(nestedGroupInput).find('.mapping-data__possible_answer__map-to input').each(function (index, inputItem) {

                                                    //get previous row nested group input ref
                                                    var nestedGroupInputRef = $(nestedGroupInput).prev().attr('data-input-ref');
                                                    var prevNestedGroupInput = currentForm[inputRef].branch[branchInputRef].group[nestedGroupInputRef];
                                                    var answerRef = $(inputItem).attr('data-answer-ref');
                                                    var mapTo = $(inputItem).val();

                                                    //trim any mapTo value (if found)
                                                    mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                                    if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                                                        isMappingValid = false;
                                                        $(inputItem).parent().addClass('has-error');
                                                    }

                                                    prevNestedGroupInput.possible_answers[answerRef] = {};
                                                    prevNestedGroupInput.possible_answers[answerRef].map_to = mapTo;
                                                });
                                            }
                                        });

                                        //skip by nestedGroupInputs.length to skip to the next branch input
                                        rowIndex += nestedGroupInputs.length;
                                    }
                                }
                                else {
                                    //this row is the possible answers for the current branch question
                                    $(branchInput)
                                        .find('.mapping-data__possible_answer__map-to input')
                                        .each(function (index, inputItem) {

                                            //get previous row input ref
                                            var branchInputRef = $(branchInput).prev().attr('data-input-ref');
                                            var prevBranchInput = currentForm[inputRef].branch[branchInputRef];
                                            var answerRef = $(inputItem).attr('data-answer-ref');
                                            var mapTo = $(inputItem).val();

                                            //trim any mapTo value (if found)
                                            mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                            if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                                                isMappingValid = false;
                                                $(inputItem).parent().addClass('has-error');
                                            }

                                            prevBranchInput.possible_answers[answerRef] = {};
                                            prevBranchInput.possible_answers[answerRef].map_to = mapTo;
                                        });
                                }
                            });

                            //skip by branchInputs.length to skip to the next top level input
                            rowIndex += branchInputs.length;
                            break;

                        case 'group':

                            var groupInputs = $(rows[rowIndex]).nextUntil('[data-top-level-input]', '[data-is-group-input]');

                            $(groupInputs).each(function (groupIndex, groupInput) {

                                var groupInputRef = $(groupInput).attr('data-input-ref');
                                var currentGroup;
                                var mapToInput = $(groupInput).find('.mapping-data__map-to input');
                                var mapTo = mapToInput.val();

                                //trim any mapTo value (if found)
                                mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                var hide = $(groupInput).find('.mapping-data__hide-checkbox input').is(':checked');

                                //is it a question or possible answers?
                                if (groupInputRef) {
                                    //this row is a branch question
                                    currentForm[inputRef].group[groupInputRef] = {};
                                    currentGroup = currentForm[inputRef].group[groupInputRef];
                                    currentGroup.possible_answers = {};
                                    currentGroup.branch = {};
                                    currentGroup.group = {};
                                    currentGroup.map_to = mapTo;
                                    currentGroup.hide = hide;

                                    //cache mapTo value
                                    if (mapTo !== undefined) {
                                        formMapTos[formRef].push(mapTo);
                                    }

                                    if (!mapTo.match(mappingToRegex)) {
                                        isMappingValid = false;
                                        $(groupInput).find('.mapping-data__map-to input').parent().addClass('has-error');
                                    }
                                }
                                else {
                                    //this row is the possible answers for the current group question
                                    $(groupInput).find('.mapping-data__possible_answer__map-to input').each(function (index, inputItem) {

                                        //get previous row input ref
                                        var groupInputRef = $(groupInput).prev().attr('data-input-ref');
                                        var prevGroupInput = currentForm[inputRef].group[groupInputRef];
                                        var answerRef = $(inputItem).attr('data-answer-ref');
                                        var mapTo = $(inputItem).val();
                                        //trim any mapTo value (if found)
                                        mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                                        if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                                            isMappingValid = false;
                                            $(inputItem).parent().addClass('has-error');
                                        }

                                        prevGroupInput.possible_answers[answerRef] = {};
                                        prevGroupInput.possible_answers[answerRef].map_to = mapTo;
                                    });
                                }
                            });

                            //skip by branchInputs.length to skip to the next top level input
                            rowIndex += groupInputs.length;
                            break;
                    }
                    rowIndex++;
                }
                else {
                    //get possible answers for current input
                    $(rows[rowIndex]).find('.mapping-data__possible_answer__map-to input').each(function (index, inputItem) {

                        var inputRef = $(rows[rowIndex - 1]).attr('data-input-ref');
                        var answerRef = $(inputItem).attr('data-answer-ref');
                        var mapTo = $(inputItem).val();
                        //trim any mapTo value (if found)
                        mapTo = (mapTo === undefined) ? undefined : mapTo.trim();

                        if (!mapTo.match(mappingPossibleAnswerToRegex)) {
                            isMappingValid = false;
                            $(inputItem).parent().addClass('has-error');
                        }

                        currentForm[inputRef].possible_answers[answerRef] = {};
                        currentForm[inputRef].possible_answers[answerRef].map_to = mapTo;

                    });
                    rowIndex++;
                }
            }

            console.log(JSON.stringify(formMapTos));

            $.each(formMapTos, function (formIndex, form) {

                //find duplicated identfier if any
                var duplicates = [];

                $.each(form, function (itemIndex, item) {

                    //do we have a duplicate?
                    if ($.inArray(item.trim(), duplicates) === -1) {
                        //no, add it
                        duplicates.push(item.trim());
                    }
                    else {
                        //we have a duplicate, bail out
                        hasDuplicateIdentifier = { key: item, formRef: formIndex };
                        isMappingValid = false;
                        console.log(item);
                        return false;
                    }
                });
            });
        });

        console.log(JSON.stringify(mapping[mapIndex]));

        if (isMappingValid) {
            //post mapping
            window.EC5.projectUtils.postRequest(postURL + '/update', {
                action: action,
                map_index: mapIndex,
                mapping: mapping[mapIndex]
            }).done(function (response) {
                // console.log(JSON.stringify(response));
                window.EC5.overlay.fadeOut();
                window.EC5.toast.showSuccess(mapName + ' updated');
            }).fail(function (error) {
                window.EC5.overlay.fadeOut();
                window.EC5.projectUtils.showErrors(error);
            });
        }
        else {
            if (hasDuplicateIdentifier) {
                //highlight duplicate identifiers in the dom
                tables.each(function (index, table) {

                    if ($(table).data('form-ref') === hasDuplicateIdentifier.formRef) {
                        var rows = $(table).find('tbody tr');

                        rows.each(function (rowIndex, row) {

                            if ($(row).find('.mapping-data__map-to input').val() === hasDuplicateIdentifier.key) {
                                $(row).find('.mapping-data__map-to').addClass('has-error');
                            }
                        });
                    }
                });

                window.EC5.toast.showError(mapName + ' has got duplicate identifier: ' + hasDuplicateIdentifier.key);
            }
            else {
                window.EC5.toast.showError(mapName + ' has got invalid identifier(s)');
            }
            window.EC5.overlay.fadeOut();
        }
    };

}(window.EC5.mapping));
