<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Assinatura\Models\Plano;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para criar planos de assinatura
 * 
 * Este seeder cria planos padrão para o sistema:
 * - Básico: Plano inicial com recursos limitados
 * - Profissional: Plano intermediário com mais recursos
 * - Enterprise: Plano completo com recursos ilimitados
 */
class PlanosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Criando planos de assinatura...');

        // Verificar se já existem planos
        if (Plano::count() > 0) {
            $this->command->warn('Planos já existem. Use --force para recriar.');
            return;
        }

        $planos = [
            [
                'nome' => 'Básico',
                'descricao' => 'Plano ideal para pequenas empresas que estão começando. Inclui recursos essenciais para gerenciar processos licitatórios.',
                'preco_mensal' => 99.90,
                'preco_anual' => 999.00, // ~17% de desconto
                'limite_processos' => 10,
                'limite_usuarios' => 3,
                'limite_armazenamento_mb' => 1024, // 1 GB
                'recursos_disponiveis' => [
                    'processos_licitatorios',
                    'orcamentos',
                    'contratos',
                    'relatorios_basicos',
                    'suporte_email',
                ],
                'ativo' => true,
                'ordem' => 1,
            ],
            [
                'nome' => 'Profissional',
                'descricao' => 'Plano completo para empresas em crescimento. Inclui todos os recursos do plano Básico, além de funcionalidades avançadas e mais capacidade.',
                'preco_mensal' => 299.90,
                'preco_anual' => 2999.00, // ~17% de desconto
                'limite_processos' => 50,
                'limite_usuarios' => 10,
                'limite_armazenamento_mb' => 5120, // 5 GB
                'recursos_disponiveis' => [
                    'processos_licitatorios',
                    'orcamentos',
                    'contratos',
                    'empenhos',
                    'notas_fiscais',
                    'relatorios_avancados',
                    'dashboard_analytics',
                    'exportacao_dados',
                    'suporte_prioritario',
                    'integracao_api',
                ],
                'ativo' => true,
                'ordem' => 2,
            ],
            [
                'nome' => 'Enterprise',
                'descricao' => 'Plano completo com recursos ilimitados. Ideal para grandes empresas que precisam de máxima capacidade e suporte dedicado.',
                'preco_mensal' => 999.90,
                'preco_anual' => 9999.00, // ~17% de desconto
                'limite_processos' => null, // Ilimitado
                'limite_usuarios' => null, // Ilimitado
                'limite_armazenamento_mb' => null, // Ilimitado
                'recursos_disponiveis' => [
                    'processos_licitatorios',
                    'orcamentos',
                    'contratos',
                    'empenhos',
                    'notas_fiscais',
                    'autorizacoes_fornecimento',
                    'custos_indiretos',
                    'relatorios_avancados',
                    'dashboard_analytics',
                    'exportacao_dados',
                    'api_ilimitada',
                    'webhooks',
                    'suporte_dedicado',
                    'treinamento_equipe',
                    'customizacoes',
                    'backup_automatico',
                    'sla_garantido',
                ],
                'ativo' => true,
                'ordem' => 3,
            ],
            [
                'nome' => 'Trial',
                'descricao' => 'Plano de teste gratuito por 14 dias. Ideal para conhecer o sistema antes de assinar um plano pago.',
                'preco_mensal' => 0.00,
                'preco_anual' => 0.00,
                'limite_processos' => 3,
                'limite_usuarios' => 1,
                'limite_armazenamento_mb' => 100, // 100 MB
                'recursos_disponiveis' => [
                    'processos_licitatorios',
                    'orcamentos',
                    'relatorios_basicos',
                ],
                'ativo' => true,
                'ordem' => 0, // Primeiro na listagem
            ],
        ];

        DB::beginTransaction();

        try {
            foreach ($planos as $planoData) {
                $plano = Plano::create($planoData);
                $this->command->info("✓ Plano '{$plano->nome}' criado (ID: {$plano->id})");
            }

            DB::commit();
            
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info('Planos criados com sucesso!');
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info('');
            $this->command->info('Planos disponíveis:');
            foreach ($planos as $planoData) {
                $precoMensal = number_format($planoData['preco_mensal'], 2, ',', '.');
                $precoAnual = number_format($planoData['preco_anual'], 2, ',', '.');
                $this->command->info("  • {$planoData['nome']}: R$ {$precoMensal}/mês ou R$ {$precoAnual}/ano");
            }
            $this->command->info('═══════════════════════════════════════════════════════');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Erro ao criar planos: ' . $e->getMessage());
            throw $e;
        }
    }
}
