<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create
        {--name= : Nome do super admin}
        {--email= : E-mail do super admin}
        {--password= : Senha do super admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the initial global super admin user.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $email = trim((string) $this->option('email'));
        $password = (string) $this->option('password');

        if ($name === '' || $email === '' || $password === '') {
            $this->components->error('Informe --name=, --email= e --password= para criar o super admin.');

            return self::FAILURE;
        }

        /** @var User $user */
        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->fill([
            'name' => $name,
            'password' => Hash::make($password),
            'global_role' => 'super_admin',
            'role' => 'admin',
            'active' => true,
        ]);

        $user->save();

        $this->components->info("Super admin configurado com sucesso para {$user->email}.");

        return self::SUCCESS;
    }
}
