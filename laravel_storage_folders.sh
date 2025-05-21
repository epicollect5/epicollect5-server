#!/bin/bash
# This script creates the full Laravel storage folder structure under current/storage,
# setting correct permissions and ownership for local debugging after cloning a droplet.
# It should NOT be run in production because it aborts if storage is a symlink (common in prod).
# Use this only after removing symlinks to get all storage subfolders physically present.

set -e  # exit on any error
set -x  # print commands as they run

# A bash script to create storage folders with subfolders all in one go.
DIR="/var/www/html_prod/current/storage"
# Define users and directories
REMOTE_USER=$(whoami)    # this will be the current SSH user (e.g., dev)
HTTP_USER="www-data"     # Apache user
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

# Set ACL permissions
sudo setfacl -R -m "u:$HTTP_USER:rwX" -m "u:$REMOTE_USER:rwX" ${DIR}
sudo setfacl -d -R -m "u:$HTTP_USER:rwX" -m "u:$REMOTE_USER:rwX" ${DIR}

# Set apache ownership
sudo chown -R www-data:www-data ${DIR}

# After creating the directories, remove setgid (if set)
sudo chmod -R g-s ${DIR}

# Set standard directory permissions (775)
sudo chmod -R $PERMISSIONS ${DIR}


# Success message
echo -e "\033[0;32mStorage folders created successfully with ACL permissions and apache ownership.\033[0m"

# Reminder about Passport OAuth keys
echo -e "\033[1;33mReminder: Add Passport OAuth keys (private.key and public.key) for API authentication.\033[0m"


