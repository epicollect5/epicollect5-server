<?php
/*
*************************************
// Migrated to Deployer 7.x for Laravel
*************************************
*/

namespace Deployer;

use Illuminate\Support\Facades\Log;
use Throwable;

require 'recipe/laravel.php';

// Configuration
set('repository', 'https://github.com/epicollect5/epicollect5-server.git');
set('ssh_multiplexing', true);

add('shared_files', ['public/.htaccess']);

add('writable_dirs', [
    'storage/app/projects',
    'storage/app/temp'
]);

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
    $currentDeployFile = '{{release_path}}/deploy.php';


    // Path where the symlink will be created, adjust it as needed
    $deploySymlinkPath = '{{deploy_path}}/deploy.php';

    // Create a symlink pointing to the latest deploy.php
    run("ln -sf $currentDeployFile $deploySymlinkPath");

    writeln('Symlink to the latest deploy.php has been created.');
});

task('setup:change_storage_owner_group', function () {
    $writableDirs = get('writable_dirs');
    // Get the Apache or Nginx user dynamically
    $httpUser = run('ps aux | egrep "(apache|nginx)" | grep -v root | head -n 1 | awk \'{print $1}\'');
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

task('setup:create_user_and_db', function () {
    $dbUsername = 'epicollect5-server';
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
    $dbName = 'epicollect5_prod';

    writeln("Generated password for '$dbUsername', saving to .env");

    // Write SQL commands to a file
    $sqlFile = "/tmp/db_setup.sql";
    run("echo \"CREATE USER IF NOT EXISTS '$dbUsername'@'localhost' IDENTIFIED BY '$dbPassword';\" > $sqlFile");
    run("echo \"GRANT USAGE ON *.* TO '$dbUsername'@'localhost';\" >> $sqlFile");
    run("echo \"CREATE DATABASE IF NOT EXISTS $dbName;\" >> $sqlFile");
    run("echo \"GRANT ALL PRIVILEGES ON $dbName.* TO '$dbUsername'@'localhost';\" >> $sqlFile");
    run("echo \"FLUSH PRIVILEGES;\" >> $sqlFile");

    // Run the SQL commands from the file
    run("sudo mysql < $sqlFile");

    // Clean up the SQL file
    run("rm $sqlFile");

    writeln('MySQL user and database created successfully.');

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
    file_put_contents($sharedEnvFile, $envContent);

    writeln('.env file updated successfully.');
});



// Production server
localhost('production')
    ->set('deploy_path', '/var/www/html_prod')
    ->set('branch', 'master');

// Development server
localhost('development')
    ->set('deploy_path', '/var/www/html_prod')
    ->set('branch', 'development');

// Apple server (custom branch)
localhost('apple')
    ->set('deploy_path', '/var/www/html_prod')
    ->set('branch', 'features/sign-in-with-apple');

// Tasks
desc('Execute artisan migrate');
task('artisan:migrate', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate --force', [
        'timeout' => 2000, // increasing timeout for long migrations
    ]);
})->once();

desc('Execute artisan migrate:rollback');
task('artisan:migrate:rollback', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate:rollback --force', [
        'timeout' => 2000, // increasing timeout for long migrations
    ]);
})->once();

desc('Execute artisan migrate:status');
task('artisan:migrate:status', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate:status');
})->once();

// If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

// Main task
desc('Deploy your project');
task('deploy_c', [
    'artisan:down',
    'deploy:prepare',
    'deploy:vendors',
    'deploy:shared',
    'deploy:writable',
    'artisan:key:generate',
    'artisan:storage:link',
    'artisan:view:clear',
    'artisan:cache:clear',
    'artisan:config:cache',
    'artisan:migrate',
    'deploy:publish'
    // 'artisan:up', // go back online manually
]);

desc('Install Epicollect5 release');
try {
    task('install', [
        'setup:check_clean_install',
        'deploy:prepare',
        'deploy:vendors',
        'artisan:storage:link',
        'artisan:view:cache',
        'artisan:config:cache',
        'setup:change_storage_owner_group',
        'deploy:publish',
        'setup:create_user_and_db',
        'setup:symlink_deploy_file'
    ]);

} catch (Throwable $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Custom task to display a reminder message
try {
    task('reminder:update_release', function () {
        writeln('<info>Remember to update the release in .env before running artisan up!</info>');
    });
} catch (Throwable $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Hook the custom task to run after the deployment
after('deploy', 'reminder:update_release');

after('deploy', 'deploy:success');
