#!/bin/bash

#Run on the server after pulling to clear Laravel cache.
#Give it executable permission with chmod +x and run with sudo ./after_pull.sh

composer dump-autoload;

php artisan clear-compiled;

php artisan config:cache;

php artisan route:cache;

php artisan optimize --force;

php artisan cache:clear

php artisan view:clear

php artisan config:clear;
