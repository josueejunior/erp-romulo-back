<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar admin padrão se não existir
        $admin = AdminUser::where('email', 'admin@sistema.com')->first();

        if (!$admin) {
            AdminUser::create([
                'name' => 'Administrador',
                'email' => 'admin@sistema.com',
                'password' => Hash::make('admin123'),
            ]);

            $this->command->info('Admin criado: admin@sistema.com / admin123');
        } else {
            $this->command->info('Admin já existe: admin@sistema.com');
        }
    }
}
