#!/bin/bash

#Run on the server after pulling to clear Laravel cache.
#Give it executable permission with chmod +x and run with ./after_pull.sh

# ANSI escape code for yellow text color
YELLOW='\033[1;33m'
# ANSI escape code to reset text color to default
RESET='\033[0m'


composer dump-autoload -o;
echo -e "${YELLOW}composer dump-autoload -  check permissions in framework/cache for any folder with root instead of www-data ownership${RESET}"

php artisan clear-compiled;

php artisan route:cache;

php artisan cache:clear

php artisan view:clear

php artisan config:clear;

php artisan optimize:clear

php artisan system:clear-opcache;

php artisan config:cache;
