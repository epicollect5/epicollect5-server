<?php

namespace ec5\Console\Commands;

use ec5\Models\User\User;
use ec5\Models\User\UserProvider;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

class SeedSuperadminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:superadmin {--email=} {--name=} {--surname=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add superadmin to database';

    /**
     * Execute the console command.
     * @throws FileNotFoundException
     */
    public function handle(): int
    {
        do {
            $email = $this->option('email') ?? $this->ask('Enter superadmin email:');
            $name = $this->option('name') ?? $this->ask('Enter superadmin name:');
            $surname = $this->option('surname') ?? $this->ask('Enter superadmin surname:');
            $password = $this->option('password') ?? $this->secret('Enter superadmin password:');
            $confirmPassword = $this->option('password') ?? $this->secret('Confirm superadmin password:');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Invalid email format.');
                continue; // Restart the loop
            }

            if ($password !== $confirmPassword) {
                $this->error('Passwords do not match.');
                continue; // Restart the loop
            }

            $this->info('Please confirm the details:');
            $this->line("Email: $email");
            $this->line("Name: $name");
            $this->line("Surname: $surname");

            $confirmation = $this->confirm('Are these details correct? (yes/no)', true);

            if (!$confirmation) {
                $this->info('Please re-enter the details.');
                continue; // Restart the loop
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'surname' => $surname,
                    'password' => bcrypt($password, ['rounds' => config('auth.bcrypt_rounds')]),
                    'state' => config('epicollect.strings.user_state.active'),
                    'server_role' => config('epicollect.strings.server_roles.superadmin'),
                ]
            );

            UserProvider::updateOrCreate([
                'user_id' => $user->id,
                'email' => $email,
                'provider' => config('epicollect.strings.providers.local'),
            ], [
            ]);
            $this->info('Superadmin user created or updated successfully.');

            $envFile = base_path('.env');
            $envContent = File::get($envFile);
            // Update the .env file
            $envContent = preg_replace('/^SUPER_ADMIN_FIRST_NAME=.*/m', "SUPER_ADMIN_FIRST_NAME=$name", $envContent);
            $envContent = preg_replace('/^SUPER_ADMIN_LAST_NAME=.*/m', "SUPER_ADMIN_LAST_NAME=$surname", $envContent);
            $envContent = preg_replace('/^SUPER_ADMIN_EMAIL=.*/m', "SUPER_ADMIN_EMAIL=$email", $envContent);
            $envContent = preg_replace('/^SUPER_ADMIN_PASSWORD=.*/m', "SUPER_ADMIN_PASSWORD=$password", $envContent);

            File::put($envFile, $envContent);

            $this->info('.env file updated successfully.');
            return 0; // Exit without error

        } while (true);
    }
}
