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

task('deploy:check_clean_install', function () {
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


task('deploy:change_storage_owner_group', function () {
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

task('mysql:create_user_and_db', function () {
    $dbUsername = ask('Enter the MySQL user you want to create (e.g. epicollect5):');

    // Use askHidden to hide the password input
    $dbPassword = askHiddenResponse('Enter the password for this user:');
    $dbPasswordConfirm = askHiddenResponse('Confirm the password for this user:');

    if ($dbPassword !== $dbPasswordConfirm) {
        writeln('Passwords do not match. Please try again.');
        return false;
    }

    $dbName = ask('Enter the name of the database to create (e.g. epicollect5_prod):');

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
        'deploy:check_clean_install',
        'deploy:prepare',
        'deploy:vendors',
        'artisan:storage:link',
        'artisan:view:cache',
        'artisan:config:cache',
        'deploy:change_storage_owner_group',
        'deploy:publish',
        'mysql:create_user_and_db'
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
