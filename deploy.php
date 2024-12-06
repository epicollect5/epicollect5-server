<?php

/*
*************************************
// Migrated to Deployer 7.x for Laravel 11
*************************************
*/

namespace Deployer;

use Illuminate\Support\Facades\Log;
use Throwable;

require 'recipe/laravel.php';

// Configuration
set('repository', 'https://github.com/epicollect5/epicollect5-server.git');
set('ssh_multiplexing', true);
set('keep_releases', 3);

add('shared_files', ['public/.htaccess']);
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/projects',
    'storage/app/temp',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

//MYSQL user for the epicollect5 app
define('DB_USERNAME', 'epicollect5_server');
define('DB_NAME', 'epicollect5_prod');

task('setup:check_clean_install', function () {
    $deployPath = get('deploy_path');
    // Define the release path (usually the current release is a symlink to the most recent release)
    $currentReleasePath = $deployPath . '/current';
    // Check if the current release path is a symlink
    $isSymlink = run("test -L $currentReleasePath && echo 'true' || echo 'false'");
    if ($isSymlink === 'true') {
        writeln('<error>A release already exists. Skipping install.</error>');
        // Abort the deployment
        exit(1);
    }
});

task('setup:symlink_deploy_file', function () {
    // Path to the current release's deploy.php file
    $currentDeployFile = 'current/deploy.php';
    // Path where the symlink will be created, adjust it as needed
    $deploySymlinkPath = '{{deploy_path}}/deploy.php';
    // Create a symlink pointing to the latest deploy.php
    run("ln -sf $currentDeployFile $deploySymlinkPath");

    writeln('Symlink to the latest deploy.php has been created.');
});

task('setup:symlink_laravel_storage_folders_file', function () {
    // Path to the current release's deploy.php file
    $currentDeployFile = 'current/laravel_storage_folders.sh';
    // Path where the symlink will be created, adjust it as needed
    $deploySymlinkPath = '{{deploy_path}}/laravel_storage_folders.sh';
    // Create a symlink pointing to the latest deploy.php
    run("ln -sf $currentDeployFile $deploySymlinkPath");

    // Ensure the file is executable
    run("sudo chmod +x $deploySymlinkPath");

    writeln('Symlink to the latest laravel_storage_folders.sh has been created and made executable.');
});

task('setup:storage', function () {
    $writableDirs = get('writable_dirs');
    // Get the Apache or Nginx user dynamically
    $httpUser = run('ps aux | egrep "(apache|nginx)" | grep -v root | head -n 1 | awk \'{print $1}\'');
    $httpUser = trim($httpUser); // Clean up any extra whitespace
    // Apply ownership and permissions recursively to the entire storage directory
    run("sudo chown -R {$httpUser}:{$httpUser} {{release_path}}/storage");
    run("sudo chmod -R 775 {{release_path}}/storage");
    run("sudo chown -R {$httpUser}:{$httpUser} {{release_path}}/bootstrap/cache");
    run("sudo chmod -R 775 {{release_path}}/bootstrap/cache");
    // Change ownership and group of each writable directory
    foreach ($writableDirs as $dir) {
        run("sudo chown -R {$httpUser}:{$httpUser} {{release_path}}/$dir");
        run("sudo chmod -R 775 {{release_path}}/$dir");
    }
});

task('setup:database', function () {

    // Constants for username and database name
    $dbUsername = DB_USERNAME;
    $dbName = DB_NAME;

    // Generate a random password with at least one uppercase, lowercase, and number
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $allChars = $lowercase . $uppercase . $numbers;

    // Ensure at least one of each required character
    $dbPassword = $lowercase[random_int(0, strlen($lowercase) - 1)] .
        $uppercase[random_int(0, strlen($uppercase) - 1)] .
        $numbers[random_int(0, strlen($numbers) - 1)];

    // Fill the rest of the password length with random characters
    for ($i = 0; $i < 9; $i++) {
        $dbPassword .= $allChars[random_int(0, strlen($allChars) - 1)];
    }

    // Shuffle the password to ensure randomness
    $dbPassword = str_shuffle($dbPassword);
    // Save the password for use in the next task
    set('dbPassword', $dbPassword);

    writeln("Generated password for '$dbUsername', saving to .env");

    // Write SQL commands to a file
    $sqlFile = "/tmp/db_setup.sql";
    run("echo \"CREATE USER IF NOT EXISTS '$dbUsername'@'localhost' IDENTIFIED BY '$dbPassword';\" > $sqlFile");
    // Additional command to update the password if the user already exists
    run("echo \"ALTER USER '$dbUsername'@'localhost' IDENTIFIED BY '$dbPassword';\" >> $sqlFile");
    run("echo \"GRANT USAGE ON *.* TO '$dbUsername'@'localhost';\" >> $sqlFile");
    run("echo \"CREATE DATABASE IF NOT EXISTS $dbName;\" >> $sqlFile");
    run("echo \"GRANT ALL PRIVILEGES ON $dbName.* TO '$dbUsername'@'localhost';\" >> $sqlFile");
    run("echo \"FLUSH PRIVILEGES;\" >> $sqlFile");

    // Run the SQL commands from the file
    run("sudo mysql < $sqlFile");

    // Clean up the SQL file
    run("rm $sqlFile");

    writeln('MySQL user and database created successfully.');
});

// Task to update the .env file
task('setup:env', function () {

    // Save the password for use in the next task
    $dbPassword = get('dbPassword');

    // Constants for username and database name
    $dbUsername = DB_USERNAME;
    $dbName = DB_NAME;

    // Resolve the deploy path and current path
    $sharedEnvFile = get('deploy_path') . '/shared/.env';
    $sharedHTAccessFile = get('deploy_path') . '/shared/public/.htaccess';
    $currentEnvExample = get('release_path') . '/.env.example';
    $currentHTAccessExample = get('release_path') . '/public/.htaccess-example';

    run("cp $currentEnvExample $sharedEnvFile");
    writeln('.env file copied from .env.example.');

    run("cp $currentHTAccessExample $sharedHTAccessFile");
    writeln('.htaccess file copied from public/.htaccess-example.');

    // Update the .env file
    $envContent = file_get_contents($sharedEnvFile);
    $envContent = preg_replace(
        '/^DB_DATABASE=.*/m',
        "DB_DATABASE=$dbName",
        $envContent
    );
    $envContent = preg_replace(
        '/^DB_USERNAME=.*/m',
        "DB_USERNAME=$dbUsername",
        $envContent
    );
    $envContent = preg_replace(
        '/^DB_PASSWORD=.*/m',
        "DB_PASSWORD=$dbPassword",
        $envContent
    );

    //replace help text
    $envContent = preg_replace(
        '/^#key below is an example to make php artisan key:generate works.*/m',
        '',
        $envContent
    );

    file_put_contents($sharedEnvFile, $envContent);

    writeln('.env file updated successfully.');
});

task('setup:alerts', function () {
    while (true) {
        // Prompt for alert email
        $email = ask('Enter system email for alerts:');

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            writeln('<error>Invalid email format. Please try again.</error>');
            continue; // Restart the task if email is invalid
        }

        // Proceed with the task if inputs are valid
        writeln('<info>Email is valid, saving to .env</info>');

        // Update the .env file
        $sharedEnvFile = get('deploy_path') . '/shared/.env';
        $envContent = file_get_contents($sharedEnvFile);

        $envContent = preg_replace(
            '/^SYSTEM_EMAIL=.*/m',
            "SYSTEM_EMAIL=$email",
            $envContent
        );

        file_put_contents($sharedEnvFile, $envContent);

        writeln('<info>.env file updated successfully.</info>');
        break; // Exit the loop once the task is successfully completed
    }
});


task('setup:superadmin', function () {

    while (true) {
        // Prompt for superadmin email
        $email = ask('Enter superadmin email:');
        // Prompt for superadmin name
        $name = ask('Enter superadmin name:');
        // Prompt for superadmin surname
        $surname = ask('Enter superadmin surname:');

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            writeln('<error>Invalid email format. Please try again.</error>');
            continue; // Restart the task if email is invalid
        }

        // Prompt for superadmin password twice
        $password = askHiddenResponse('Enter superadmin password:');
        $confirmPassword = askHiddenResponse('Confirm superadmin password:');

        // Check if passwords match
        if ($password !== $confirmPassword) {
            writeln('<error>Passwords do not match. Please try again.</error>');
            continue; // Restart the task if passwords don't match
        }

        // Show entered details and ask for confirmation
        writeln("<info>Details entered:</info>");
        writeln("<info>Email:</info> $email");
        writeln("<info>Name:</info> $name");
        writeln("<info>Surname:</info> $surname");

        $confirmation = ask('Do you want to proceed with these details? (yes/no)', true);
        if (strtolower($confirmation) !== 'yes') {
            writeln('<error>Operation aborted by user. Please try again.</error>');
            continue; // Restart the task if user aborts
        }

        // Proceed with the task if inputs are valid and confirmed
        writeln('<info>Superadmin credentials are valid, saving to .env</info>');

        // Update the .env file
        $sharedEnvFile = get('deploy_path') . '/shared/.env';
        $envContent = file_get_contents($sharedEnvFile);
        $envContent = preg_replace(
            '/^SUPER_ADMIN_FIRST_NAME=.*/m',
            "SUPER_ADMIN_FIRST_NAME=$name",
            $envContent
        );
        $envContent = preg_replace(
            '/^SUPER_ADMIN_LAST_NAME=.*/m',
            "SUPER_ADMIN_LAST_NAME=$surname",
            $envContent
        );

        $envContent = preg_replace(
            '/^SUPER_ADMIN_EMAIL=.*/m',
            "SUPER_ADMIN_EMAIL=$email",
            $envContent
        );

        $envContent = preg_replace(
            '/^SUPER_ADMIN_PASSWORD=.*/m',
            "SUPER_ADMIN_PASSWORD=$password",
            $envContent
        );

        file_put_contents($sharedEnvFile, $envContent);

        writeln('<info>.env file updated successfully.</info>');
        break; // Exit the loop once the task is successfully completed
    }
});


task('setup:passport:keys', function () {
    // Run artisan passport:keys to generate the keys
    run('cd {{deploy_path}}/current && {{bin/php}} artisan passport:keys');

    // Path to the keys
    $keysPath = '{{deploy_path}}/shared/storage';

    // Get the Apache or Nginx user dynamically
    $httpUser = run('ps aux | grep -E "(apache|nginx)" | grep -v root | head -n 1 | awk \'{print $1}\'');
    $httpUser = trim($httpUser); // Clean up any extra whitespace

    if (empty($httpUser)) {
        writeln('Unable to determine the HTTP server user.');
        exit(1);
    }

    // Change ownership of the keys to the HTTP server user
    run("sudo chown $httpUser:$httpUser $keysPath/oauth-private.key");
    run("sudo chown $httpUser:$httpUser $keysPath/oauth-public.key");

    // Set appropriate permissions for the keys
    run("sudo chmod 600 $keysPath/oauth-private.key");
    run("sudo chmod 644 $keysPath/oauth-public.key");

    writeln('Passport keys generated and permissions updated.');
});

task('setup:storage:link', function () {
    // Run artisan passport:keys to generate the keys
    run('cd {{deploy_path}}/current && {{bin/php}} artisan storage:link');
    writeln('artisan storage:link executed.');
});

task('setup:key:generate', function () {
    // Run artisan passport:keys to generate the keys
    run('cd {{deploy_path}}/current && {{bin/php}} artisan key:generate');
    writeln('<info>artisan key:generate executed.</info>');
});

task('setup:stats', function () {
    // Run artisan passport:keys to generate the keys
    run('cd {{deploy_path}}/current && {{bin/php}} artisan system:stats --deployer');
    writeln('<info>Initial system stats executed.</info>');
});

// Production server
localhost('production')
    ->set('deploy_path', '/var/www/html_prod')
    ->set('branch', 'master');

// Tasks
desc('Execute artisan migrate');
task('artisan:migrate', function () {
    $output =  run('{{bin/php}} {{release_path}}/artisan migrate --force', [
        'timeout' => 2000, // increasing timeout for long migrations,
        'real_time_output' => false
    ]);
    writeln("<info>$output</info>");

})->once();

desc('Execute artisan migrate:rollback');
task('artisan:migrate:rollback', function () {
    $output = run('{{bin/php}} {{release_path}}/artisan migrate:rollback --force', [
        'timeout' => 2000, // increasing timeout for long migrations
        'real_time_output' => false
    ]);
    writeln("<info>$output</info>");
})->once();

desc('Execute artisan migrate:status');
task('artisan:migrate:status', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate:status');
})->once();

desc('Execute artisan down with secret');
task('artisan:down_with_secret', function () {
    $output =   run('cd {{deploy_path}}/current && {{bin/php}} artisan down --with-secret', [
        'real_time_output' => true
        ]);
    writeln("<info>$output</info>");
});

task('artisan:about', function () {
    $output = run('cd {{deploy_path}}/current && {{bin/php}} artisan about', [
        'real_time_output' => true
    ]);
});

task('composer:dump-autoload', function () {
    $output = run('cd {{deploy_path}}/current && {{bin/composer}} dump-autoload -o', [
        'real_time_output' => true
    ]);
});


// Main task
desc('Deploy (update) your project');
task('deploy', [
    'artisan:down_with_secret',
    'deploy:prepare',
    'deploy:vendors',
    'artisan:migrate',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'deploy:publish',
    'setup:symlink_deploy_file',
    'setup:symlink_laravel_storage_folders_file',
    'composer:dump-autoload',
    'artisan:about'
    // 'artisan:up', // go back online manually after checking all works
]);

desc('Install Epicollect5 release from scratch');
try {
    task('install', [
        'setup:check_clean_install',
        'deploy:prepare',
        'deploy:vendors',
        'setup:storage',
        'deploy:publish',
        'setup:database',
        'setup:env',
        'setup:key:generate',
        'setup:storage:link',
        'setup:passport:keys',
        'setup:superadmin',
        'setup:alerts',
        'artisan:view:clear',
        'artisan:config:cache',
        'artisan:migrate',
        'setup:symlink_deploy_file',
        'setup:stats'
    ]);

} catch (Throwable $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Custom task to display a reminder message
try {
    task('reminder:update_release', function () {
        writeln('<info>App is currently in maintenance mode.</info>');
        writeln('<info>Remember to update the release in .env before running artisan up!</info>');
    });
} catch (Throwable $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Hook the custom task to run after the deployment
after('deploy', 'reminder:update_release');
// If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
//show message if success
after('deploy', 'deploy:success');
