<?php
/*
*************************************
//Use Deployer 4.2.1 with Laravel 5.4
*************************************
*/

namespace Deployer;

require 'recipe/laravel.php';

// Configuration

set('ssh_type', 'native');
set('ssh_multiplexing', true);

set('repository', 'https://github.com/epicollect5/epicollect5-server.git');

add('shared_files', ['public/.htaccess']);
add('shared_dirs', []);
add('writable_dirs', []);

// Production server
localServer('production')
    ->stage('production')
    ->set('branch', 'master')
    ->set('deploy_path', '/var/www/html_prod');

// Development server
localServer('development')
    ->stage('development')
    ->set('branch', 'development')
    ->set('deploy_path', '/var/www/html_prod');

localServer('apple')
    ->stage('apple')
    ->set('branch', 'features/sign-in-with-apple')
    ->set('deploy_path', '/var/www/html_prod');

// Tasks
desc('Execute artisan migrate');
task('artisan:migrate', function () {
    $output = run('{{bin/php}} {{release_path}}/artisan migrate --force', [
        'timeout' => 2000, //increasing timeout for long migrations
    ]);
    writeln('<info>' . $output . '</info>');
});

desc('Execute artisan migrate:rollback');
task('artisan:migrate:rollback', function () {
    $output = run('{{bin/php}} {{release_path}}/artisan migrate:rollback --force', [
        'timeout' => 2000, //increasing timeout for long migrations
    ]);
    writeln('<info>' . $output . '</info>');
});

desc('Execute artisan migrate:status');
task('artisan:migrate:status', function () {
    $output = run('{{bin/php}} {{release_path}}/artisan migrate:status');
    writeln('<info>' . $output . '</info>');
});

// If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

/**
 * Main task
 */
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
    'artisan:optimize',
    'deploy:symlink',
    'artisan:migrate',
    'deploy:unlock',
    'cleanup'
    // 'artisan:up', go back online manually
]);

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
    'cleanup',
    'artisan:up',
]);

after('deploy', 'success');
