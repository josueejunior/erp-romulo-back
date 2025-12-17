<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plano;

class PlanosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $planos = [
            [
                'nome' => 'Básico',
                'descricao' => 'Ideal para pequenas empresas que estão começando',
                'preco_mensal' => 99.00,
                'preco_anual' => 990.00, // 2 meses grátis
                'limite_processos' => 10,
                'limite_usuarios' => 3,
                'limite_armazenamento_mb' => 1024, // 1GB
                'recursos_disponiveis' => [
                    'processos_limitados',
                    'usuarios_limitados',
                    'suporte_email',
                ],
                'ativo' => true,
                'ordem' => 1,
            ],
            [
                'nome' => 'Profissional',
                'descricao' => 'Para empresas em crescimento que precisam de mais recursos',
                'preco_mensal' => 299.00,
                'preco_anual' => 2990.00, // 2 meses grátis
                'limite_processos' => 50,
                'limite_usuarios' => 10,
                'limite_armazenamento_mb' => 10240, // 10GB
                'recursos_disponiveis' => [
                    'processos_limitados',
                    'usuarios_limitados',
                    'relatorios_avancados',
                    'suporte_prioritario',
                    'exportacao_pdf',
                ],
                'ativo' => true,
                'ordem' => 2,
            ],
            [
                'nome' => 'Enterprise',
                'descricao' => 'Solução completa para grandes empresas',
                'preco_mensal' => 799.00,
                'preco_anual' => 7990.00, // 2 meses grátis
                'limite_processos' => null, // Ilimitado
                'limite_usuarios' => null, // Ilimitado
                'limite_armazenamento_mb' => null, // Ilimitado
                'recursos_disponiveis' => [
                    'processos_ilimitados',
                    'usuarios_ilimitados',
                    'armazenamento_ilimitado',
                    'relatorios_avancados',
                    'api_access',
                    'suporte_prioritario_24h',
                    'exportacao_pdf_excel',
                    'integracao_personalizada',
                ],
                'ativo' => true,
                'ordem' => 3,
            ],
        ];

        foreach ($planos as $planoData) {
            Plano::updateOrCreate(
                ['nome' => $planoData['nome']],
                $planoData
            );
        }

        $this->command->info('Planos criados com sucesso!');
    }
}
