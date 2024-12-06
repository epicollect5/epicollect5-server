#!/bin/bash
# A bash script to create storage folders with subfolders all in one go.
DIR="/var/www/html_prod/shared/storage"
PERMISSIONS=775

# Prompt for sudo at the beginning
sudo -v

# Check if storage is a symlink
if [ -L "$DIR" ]; then
    echo -e "\033[1;31mError: $DIR is a symlink. Aborting to prevent unintended modifications.\033[0m"
    exit 1
fi


# Create directories
sudo mkdir -p ${DIR}/{app,debugbar,framework,logs}

sudo mkdir -p ${DIR}/app/{entries,orphans,projects,public,temp}

sudo mkdir -p ${DIR}/app/projects/{project_mobile_logo,project_thumb}

sudo mkdir -p ${DIR}/app/entries/{audio,photo,video}

sudo mkdir -p ${DIR}/framework/{cache,sessions,views}

sudo mkdir -p ${DIR}/framework/cache/data

# Set permissions
sudo chmod -R ${PERMISSIONS} ${DIR}

# Set apache ownership
sudo chown -R www-data:www-data ${DIR}

# After creating the directories, remove setgid (if set)
sudo chmod -R g-s ${DIR}

# Success message
echo -e "\033[0;32mStorage folders created successfully with ${PERMISSIONS} permissions and apache ownership.\033[0m"

# Reminder about Passport OAuth keys
echo -e "\033[1;33mReminder: Add Passport OAuth keys (private.key and public.key) for API authentication.\033[0m"


