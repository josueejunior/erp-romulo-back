<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Assinatura\Models\Plano;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para criar planos de assinatura
 * 
 * Este seeder cria planos padrão para o sistema:
 * - Gratuito: R$ 0,00/mês - Trial de 3 dias para novos usuários
 * - Essencial: R$ 277,14/mês - Operação completa, mas sem visão estratégica
 * - Profissional: R$ 342,86/mês - Visão estratégica e previsibilidade
 * - Master: R$ 457,14/mês - Controle total e escalabilidade
 * - Ilimitado: R$ 854,28/mês - Sem limites, máxima escalabilidade
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
                'nome' => 'Gratuito',
                'descricao' => 'Plano gratuito de teste de 3 dias. Ideal para conhecer o sistema antes de contratar um plano pago.',
                'preco_mensal' => 0,
                'preco_anual' => null,
                'limite_processos' => 3,
                'restricao_diaria' => true, // 1 processo por dia
                'limite_usuarios' => 1, // Apenas o administrador
                'limite_armazenamento_mb' => 100, // 100MB de armazenamento
                'recursos_disponiveis' => [
                    'cadastros_completos', // órgãos/setores, fornecedores/transportadoras
                    'processos_todas_etapas', // participação, disputa, julgamento e habilitação, execução
                    'itens_processo', // cadastro completo, orçamentos, escolha de fornecedor
                    'formacao_precos', // calculadora, preço mínimo
                    'execucao', // contratos, AFs, empenhos, NF-e de entrada e saída
                    'controle_operacional', // status dos itens, saldo por processo
                    'calendarios', // disputas e julgamentos agendados
                ],
                'ativo' => true,
                'ordem' => 0,
            ],
            [
                'nome' => 'Essencial',
                'descricao' => 'Operação completa, mas sem visão estratégica. Ideal para empresas que estão começando.',
                'preco_mensal' => 277.14,
                'preco_anual' => null,
                'limite_processos' => 5,
                'restricao_diaria' => true, // 1 processo por dia
                'limite_usuarios' => 2, // 1 Administrador + 1 Operacional
                'limite_armazenamento_mb' => null,
                'recursos_disponiveis' => [
                    'cadastros_completos', // órgãos/setores, fornecedores/transportadoras
                    'processos_todas_etapas', // participação, disputa, julgamento e habilitação, execução
                    'itens_processo', // cadastro completo, orçamentos, escolha de fornecedor
                    'formacao_precos', // calculadora, preço mínimo
                    'execucao', // contratos, AFs, empenhos, NF-e de entrada e saída
                    'controle_operacional', // status dos itens, saldo por processo
                ],
                'ativo' => true,
                'ordem' => 1,
            ],
            [
                'nome' => 'Profissional',
                'descricao' => 'Visão estratégica e previsibilidade. Inclui todos os recursos do Essencial, além de calendários, relatórios e funcionalidades avançadas.',
                'preco_mensal' => 342.86,
                'preco_anual' => null,
                'limite_processos' => 20,
                'restricao_diaria' => true, // 1 processo por dia
                'limite_usuarios' => 5,
                'limite_armazenamento_mb' => null,
                'recursos_disponiveis' => [
                    'cadastros_completos',
                    'processos_todas_etapas',
                    'itens_processo',
                    'formacao_precos',
                    'execucao',
                    'controle_operacional',
                    'calendarios', // disputas e julgamentos
                    'relatorios', // desempenho, taxa de aproveitamento, processos por período
                    'formacao_precos_avancada', // preço mínimo e recomendado
                    'julgamento_avancado', // chance de arremate, lembretes, valores negociados
                    'exportacoes', // proposta comercial, ficha técnica
                ],
                'ativo' => true,
                'ordem' => 2,
            ],
            [
                'nome' => 'Master',
                'descricao' => 'Controle total e escalabilidade. Inclui todas as funcionalidades do Profissional, além de gestão financeira completa e histórico imutável.',
                'preco_mensal' => 457.14,
                'preco_anual' => null,
                'limite_processos' => 30,
                'restricao_diaria' => false, // Sem restrição diária
                'limite_usuarios' => null, // Ilimitado
                'limite_armazenamento_mb' => null,
                'recursos_disponiveis' => [
                    'cadastros_completos',
                    'processos_todas_etapas',
                    'itens_processo',
                    'formacao_precos',
                    'execucao',
                    'controle_operacional',
                    'calendarios',
                    'relatorios',
                    'formacao_precos_avancada',
                    'julgamento_avancado',
                    'exportacoes',
                    'gestao_financeira_completa', // custos diretos e indiretos, lucro real, margem, saldo a receber
                    'historico_imutavel',
                ],
                'ativo' => true,
                'ordem' => 3,
            ],
            [
                'nome' => 'Ilimitado',
                'descricao' => 'Sem limites, máxima escalabilidade. Inclui todas as funcionalidades do Master, com processos e usuários ilimitados.',
                'preco_mensal' => 854.28,
                'preco_anual' => null,
                'limite_processos' => null, // Ilimitado
                'restricao_diaria' => false, // Sem restrição diária
                'limite_usuarios' => null, // Ilimitado
                'limite_armazenamento_mb' => null,
                'recursos_disponiveis' => [
                    'cadastros_completos',
                    'processos_todas_etapas',
                    'itens_processo',
                    'formacao_precos',
                    'execucao',
                    'controle_operacional',
                    'calendarios',
                    'relatorios',
                    'formacao_precos_avancada',
                    'julgamento_avancado',
                    'exportacoes',
                    'gestao_financeira_completa',
                    'historico_imutavel',
                ],
                'ativo' => true,
                'ordem' => 4,
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
                $precoMensal = $planoData['preco_mensal'] > 0 
                    ? 'R$ ' . number_format($planoData['preco_mensal'], 2, ',', '.') . '/mês'
                    : 'Gratuito (Trial 3 dias)';
                $limiteProcessos = $planoData['limite_processos'] ?? 'Ilimitado';
                $limiteUsuarios = $planoData['limite_usuarios'] ?? 'Ilimitado';
                $restricaoDiaria = $planoData['restricao_diaria'] ? '1 por dia' : 'Sem restrição';
                $this->command->info("  • {$planoData['nome']}: {$precoMensal} - {$limiteProcessos} processos/mês - {$limiteUsuarios} usuários - {$restricaoDiaria}");
            }
            $this->command->info('═══════════════════════════════════════════════════════');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Erro ao criar planos: ' . $e->getMessage());
            throw $e;
        }
    }
}
