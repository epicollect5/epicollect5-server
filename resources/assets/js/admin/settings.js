$(document).ready(function () {

    function sendUpdateRequest(key, value) {

        var deferred = new $.Deferred();

        //show overlay
        window.EC5.overlay.fadeIn();
        var url = window.EC5.SITE_URL + '/api/internal/admin/settings';
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                key: key,
                value: value
            }
        }).done(function (response) {
            console.log(response);
            window.EC5.toast.showSuccess('Settings updated successfully.');
            deferred.resolve(response);
        }).fail(function (error) {
            if (error.responseJSON.errors) {
                // Show the errors
                if (error.responseJSON.errors.length > 0) {
                    for (var i = 0; i < error.responseJSON.errors.length; i++) {
                        window.EC5.toast.showError(error.responseJSON.errors[i].title);
                    }
                }
            }
            deferred.reject(error);
        }).always(function () {
            window.EC5.overlay.fadeOut();
        });

        return deferred.promise;
    }

    $('[data-setting-type]').on('click', function () {
        // Get the value of the `data-setting-type` attribute
        var settingType = $(this).data('setting-type');

        // Perform your action here
        console.log('Setting type clicked:', settingType);
        // You can add more actions here
        switch (settingType) {
            case 'email-notification-version':

                var state = $(this).data('value');
                console.log('Setting type value clicked:', state);

                sendUpdateRequest('SEND_VERSION_NOTIFICATION_EMAIL', state === 'on').then(function () {
                    $(this).siblings().removeClass('btn-action');
                    // Add `btn-action` class to the clicked button
                    $(this).addClass('btn-action');
                }, function (error) {
                    //do not do anything
                });
                break;
        }
    });
});
