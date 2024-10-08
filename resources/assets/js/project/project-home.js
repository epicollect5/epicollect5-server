'use strict';

$(document).ready(function () {
    //generate QR Code on project home page only
    if ($('.page-project-home').length > 0) {
        var qrCodeWrapper = $('#qrcode');
        var qrcode = new window.QRCode('qrcode', {
            text: qrCodeWrapper.data('url'),
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});


