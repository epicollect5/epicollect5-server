#!/bin/bash
# A bash script to create storage folders with subfolders all in one go.
# Adjust DIR accordingly
DIR="/var/www/html_prod/shared/storage/"

mkdir -p ${DIR}/{app,debugbar,framework,logs}

mkdir -p ${DIR}/app/{entries,orphans,projects,public,temp}

mkdir -p ${DIR}/app/projects/{project_mobile_logo,project_thumb}

mkdir -p ${DIR}/app/entries/{audio,photo,video}

mkdir -p ${DIR}/app/entries/photo/{entry_original,entry_thumb}

mkdir -p ${DIR}/framework/{cache,sessions,views}

# Permissions
chmod -R 755 ${DIR}

# Apache user on Ubuntu 18.04
chown -R www-data:www-data ${DIR}
