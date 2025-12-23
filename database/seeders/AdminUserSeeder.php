<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\Traits\HasTimestampsCustomizados;

class AdminUserSeeder extends Seeder
{
    use HasTimestampsCustomizados;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar admin padrão se não existir
        $admin = AdminUser::where('email', 'admin@sistema.com')->first();

        if (!$admin) {
            AdminUser::create($this->withTimestamps([
                'name' => 'Administrador',
                'email' => 'admin@sistema.com',
                'password' => Hash::make('admin123'),
            ]));

            $this->command->info('✅ Admin criado: admin@sistema.com / admin123');
        } else {
            $this->command->info('ℹ️  Admin já existe: admin@sistema.com');
        }
    }
}
