<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateProductionAdminCommand extends Command
{
    protected $signature = 'lms:admin:create {--name= : Administrator display name} {--email= : Administrator email address}';

    protected $description = 'Create the first production administrator with a privately entered password';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command requires an interactive terminal so the password can be entered securely.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Administrator email'))));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Set a unique password (minimum 14 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        if (strlen($password) < 14 || ! hash_equals($password, $confirmation)) {
            $this->error('Password must be at least 14 characters and both entries must match.');

            return self::FAILURE;
        }

        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'email_verification_required' => false,
        ]);

        $this->newLine();
        $this->info('Administrator account created successfully.');
        $this->line('Email: '.$admin->email);
        $this->warn('The password was not displayed or written to the console output. Store it in your password manager.');

        return self::SUCCESS;
    }
}
