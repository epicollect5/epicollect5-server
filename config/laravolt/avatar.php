<?php
/*
 * Set specific configuration variables here
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    | Avatar use Intervention Image library to process image.
    | Meanwhile, Intervention Image supports "GD Library" and "Imagick" to process images
    | internally. You may choose one of them according to your PHP
    | configuration. By default PHP's "GD Library" implementation is used.
    |
    | Supported: "gd", "imagick"
    |
    */
    'driver'    => env('INTERVENTION_IMAGE_LIBRARY'),

    // Initial generator class
    'generator' => \Laravolt\Avatar\Generator\DefaultGenerator::class,

    // Whether all characters supplied must be replaced with their closest ASCII counterparts
    'ascii'    => true,

    // Image shape: circle or square
    'shape' => 'square',

    // Image width, in pixel
    'width'    => 1024,

    // Image height, in pixel
    'height'   => 1024,

    // Number of characters used as initials. If name consists of single word, the first N character will be used
    'chars'    => 2,

    // font size
    'fontSize' => 128,

    // convert initial letter in uppercase
    'uppercase' => true,

    // Fonts used to render text.
    // If contains more than one fonts, randomly selected based on name supplied
    'fonts'    => [__DIR__.'/../fonts/Arimo-Bold.ttf'],

    // List of foreground colors to be used, randomly selected based on name supplied
    'foregrounds'   => [
        '#FFFFFF',
    ],

    // List of background colors to be used, randomly selected based on name supplied
    //https://www.schemecolor.com/
    'backgrounds'   => [
        '#F7B172',
        '#FF8500',
        '#C4A662',
        '#1D8F94',
        '#A8CE61',
        '#8C9398',
        '#875053',
        '#5DBCEB',
        '#59C2CF',
        '#AEDAF7',
        '#C23B23',
        '#8B5F78',
        '#C0424E',
        '#FD2E36',
        '#B29DD9',
        '#77DD77',
        '#EB6662',
        '#F7DC3F',
        '#F87203',
        '#EEB336',
        '#0075C2',
        '#254479',
        '#A1E23D',
        '#C64FE0',
        '#4B4B4B'
    ],

    'border'    => [
        'size'  => 0,

        // border color, available value are:
        // 'foreground' (same as foreground color)
        // 'background' (same as background color)
        // or any valid hex ('#aabbcc')
        'color' => 'foreground',
    ],
];
