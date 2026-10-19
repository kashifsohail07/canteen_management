<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'canteen:create-admin
                            {--name=Admin : Display name}
                            {--email= : Login email}
                            {--password= : Plain password (min 8 chars)}';

    protected $description = 'Create or update an admin user for Canteen Hisab';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name', 'Admin'));
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8)'));

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:190'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $action = $user->wasRecentlyCreated ? 'created' : 'updated';
        $this->info("Admin {$action}: {$user->email} (id {$user->id})");
        $this->line('Login: '.$user->email);

        return self::SUCCESS;
    }
}
