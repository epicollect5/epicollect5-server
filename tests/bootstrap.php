<?php

passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../artisan').' system:check-schemas');
require_once __DIR__.'/../bootstrap/autoload.php';
