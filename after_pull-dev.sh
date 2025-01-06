#!/bin/bash

# Run on the server after pulling to clear Laravel cache.
# Give it executable permission with chmod +x and run with  ./after_pull.sh

# ANSI escape codes for text colors
YELLOW='\033[1;33m'  # Yellow
RESET='\033[0m'      # Reset to default
GREEN='\033[0;32m'   # Green
RED='\033[0;31m'     # Red
BLUE='\033[0;34m'    # Blue

# Function to print messages with color
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${RESET}"
}

# Function to list files
list_files() {
    find "$1" -type f
}

# Log start time
print_message "$GREEN" "Starting cache clearing process at $(date)"

# Log the status of each command and file operations

print_message "$YELLOW" "Running composer dump-autoload"
composer dump-autoload

print_message "$YELLOW" "Running php artisan clear-compiled..."
php artisan clear-compiled

print_message "$YELLOW" "Running php artisan config:cache..."
php artisan config:cache

print_message "$YELLOW" "Running php artisan route:cache..."
php artisan route:cache

print_message "$YELLOW" "Running php artisan cache:clear..."
php artisan cache:clear

print_message "$YELLOW" "Running php artisan view:clear..."
php artisan view:clear

print_message "$YELLOW" "Running php artisan config:clear..."
php artisan config:clear

php artisan optimize:clear

print_message "$YELLOW" "Listing files in bootstrap/cache before deletion..."
# List files before deletion
before_files=$(list_files bootstrap/cache)
echo "$before_files"

# Remove files
rm -f bootstrap/cache/*.php

# List files after deletion
after_files=$(list_files bootstrap/cache)
print_message "$YELLOW" "Files after deletion:"
echo "$after_files"

# Determine deleted files
deleted_files=$(comm -23 <(echo "$before_files" | sort) <(echo "$after_files" | sort))

print_message "$BLUE" "Deleted files:"
echo "$deleted_files"


# Log end time
print_message "$GREEN" "Cache clearing process completed at $(date)"
