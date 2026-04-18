<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $email = 'amacodesistemas@gmail.com';
    $user = DB::table('users')->where('email', $email)->first();
    
    if ($user) {
        echo "User ID: " . $user->id . "\n";
        
        $updated = DB::table('onboarding_progress')
            ->where('user_id', $user->id)
            ->update([
                'onboarding_concluido' => false,
                'etapas_concluidas' => '[]',
                'progresso_percentual' => 0,
                'concluido_em' => null,
                'updated_at' => now()
            ]);
            
        if ($updated) {
            echo "✅ Tutorial resetado via UPDATE.\n";
        } else {
            // Tentar insert se update não afetou linhas (pode não existir)
            // Mas update retornaria 0 se não existisse. Vamos verificar se existe.
            $exists = DB::table('onboarding_progress')->where('user_id', $user->id)->exists();
            
            if (!$exists) {
                DB::table('onboarding_progress')->insert([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id ?? 1,
                    'email' => $user->email,
                    'onboarding_concluido' => false,
                    'etapas_concluidas' => '[]',
                    'progresso_percentual' => 0,
                    'iniciado_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                echo "✅ Registro de onboarding CRIADO.\n";
            } else {
                echo "⚠️ Registro existe mas não precisou de atualização (já estava resetado).\n";
            }
        }
    } else {
        echo "❌ Usuário $email não encontrado.\n";
    }
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
