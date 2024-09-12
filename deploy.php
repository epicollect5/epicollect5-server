<?php
/*
*************************************
// Migrated to Deployer 7.x for Laravel
*************************************
*/

namespace Deployer;

use Illuminate\Support\Facades\Log;

require 'recipe/laravel.php';

// Configuration
try {
    set('repository', 'https://github.com/epicollect5/epicollect5-server.git');
} catch (Exception\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}
try {
    set('ssh_multiplexing', true);
} catch (Exception\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

try {
    set('shared_files', ['public/.htaccess']);
} catch (Exception\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}
try {
    set('shared_dirs', []);
} catch (Exception\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}
try {
    set('writable_dirs', []);
} catch (Exception\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Production server
host('production')
    ->set('deploy_path', '/var/www/html_prod')
    ->set('branch', 'master');

// Development server
host('development')
    ->set('deploy_path', '/var/www/html_prod')
    ->set('branch', 'development');

// Apple server (custom branch)
host('apple')
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
task('deploy', [
    'artisan:down',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'artisan:view:clear',
    'artisan:cache:clear',
    'artisan:config:cache',
    'deploy:symlink',
    'artisan:migrate',
    'deploy:unlock',
    'cleanup'
    // 'artisan:up', // go back online manually
]);

desc('Install Epicollect5 release');
try {
    task('install', [
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'deploy:shared',
        'deploy:vendors',
        'deploy:writable',
        'deploy:symlink',
        'deploy:unlock',
        'cleanup'
    ]);
} catch (\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Custom task to display a reminder message
try {
    task('reminder:update_release', function () {
        writeln('<info>Remember to update the release in .env before running artisan up!</info>');
    });
} catch (\Exception $e) {
    Log::error(__METHOD__ . ' failed.', ['exception' => $e->getMessage()]);
}

// Hook the custom task to run after the deployment
after('deploy', 'reminder:update_release');

after('deploy', 'success');
