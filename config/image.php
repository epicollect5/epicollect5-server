<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    | imp: Imagick is used to keep the metadata when resizing, as GD was stripping them
    | imp: EXIF Support	must be enabled, check phpinfo()
    */

    'driver' => 'imagick'
);
