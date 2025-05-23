<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    | imp: Imagick must be used to keep the metadata when resizing, as GD was stripping them
    | imp: EXIF Support	must be enabled, check phpinfo()
    | see https://github.com/Intervention/image/issues/886
    */

    'driver' => 'imagick'
);
