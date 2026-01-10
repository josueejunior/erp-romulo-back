<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use App\Modules\Afiliado\Models\AfiliadoIndicacao;
use App\Modules\Assinatura\Models\Plano;
use App\Domain\Afiliado\Events\ComissaoGerada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Criar Indicação de Afiliado
 * 
 * Cria registro em afiliado_indicacoes quando empresa contrata com cupom de afiliado
 */
final class CriarIndicacaoAfiliadoUseCase
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Cria indicação de afiliado
     * 
     * @param int $afiliadoId ID do afiliado
     * @param int $tenantId ID do tenant
     * @param int $empresaId ID da empresa
     * @param string $codigoUsado Código do afiliado usado
     * @param float $descontoAplicado Percentual de desconto aplicado
     * @param int $planoId ID do plano contratado
     * @param float $valorPlanoOriginal Valor original do plano
     * @param float $valorPlanoComDesconto Valor após desconto
     * @param string|null $empresaNome Nome da empresa (razão social) para exibição
     * @return AfiliadoIndicacao
     */
    public function executar(
        int $afiliadoId,
        int $tenantId,
        int $empresaId,
        string $codigoUsado,
        float $descontoAplicado,
        int $planoId,
        float $valorPlanoOriginal,
        float $valorPlanoComDesconto,
        ?string $empresaNome = null
    ): AfiliadoIndicacao {
        return DB::transaction(function () use (
            $afiliadoId,
            $tenantId,
            $empresaId,
            $codigoUsado,
            $descontoAplicado,
            $planoId,
            $valorPlanoOriginal,
            $valorPlanoComDesconto,
            $empresaNome
        ) {
            // Buscar afiliado para obter percentual de comissão
            $afiliado = Afiliado::find($afiliadoId);
            if (!$afiliado) {
                throw new \DomainException('Afiliado não encontrado.');
            }

            // Buscar plano para obter nome e percentual de comissão
            $plano = Plano::find($planoId);
            if (!$plano) {
                throw new \DomainException('Plano não encontrado.');
            }

            // 🔥 NOVA LÓGICA DE CÁLCULO DE COMISSÃO:
            // Base fixa: 30%
            // Percentual do plano (peso): vem do campo percentual_comissao_afiliado (40%, 60%, 100%)
            // Comissão real = (30% × percentual_do_plano) / 100
            // Valor da comissão = (valor_do_plano × comissão_real) / 100
            // 
            // Exemplos:
            // - Plano Básico (40%): Comissão real = 30% × 40% = 12%
            // - Plano Intermediário (60%): Comissão real = 30% × 60% = 18%
            // - Plano Avançado/Premium (100%): Comissão real = 30% × 100% = 30%
            $baseComissao = 30.0; // Base fixa de 30%
            $percentualPlano = (float) ($plano->percentual_comissao_afiliado ?? 100.0); // Padrão: 100% (Premium)
            $comissaoReal = ($baseComissao * $percentualPlano) / 100; // Comissão real: 12%, 18% ou 30%
            $valorComissao = ($valorPlanoComDesconto * $comissaoReal) / 100; // Valor final da comissão

            // Manter percentual original do afiliado para histórico (não usado no cálculo)
            $comissaoPercentual = $afiliado->percentual_comissao ?? 0;

            Log::info('CriarIndicacaoAfiliadoUseCase - Criando indicação com nova lógica de comissão', [
                'afiliado_id' => $afiliadoId,
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'plano_id' => $planoId,
                'plano_nome' => $plano->nome,
                'valor_original' => $valorPlanoOriginal,
                'valor_com_desconto' => $valorPlanoComDesconto,
                'base_comissao' => $baseComissao, // 30%
                'percentual_plano' => $percentualPlano, // 40%, 60% ou 100%
                'comissao_real' => round($comissaoReal, 2), // 12%, 18% ou 30%
                'comissao_percentual_historico' => $comissaoPercentual, // Percentual do afiliado (histórico)
                'valor_comissao' => round($valorComissao, 2),
            ]);

            // Verificar se já existe indicação para esta empresa/afiliado
            $indicacaoExistente = AfiliadoIndicacao::where('afiliado_id', $afiliadoId)
                ->where('tenant_id', $tenantId)
                ->where('empresa_id', $empresaId)
                ->first();

            if ($indicacaoExistente) {
                Log::warning('CriarIndicacaoAfiliadoUseCase - Indicação já existe, atualizando', [
                    'indicacao_id' => $indicacaoExistente->id,
                ]);

                // Atualizar indicação existente
                $indicacaoExistente->update([
                    'plano_id' => $planoId,
                    'plano_nome' => $plano->nome,
                    'valor_plano_original' => $valorPlanoOriginal,
                    'valor_plano_com_desconto' => $valorPlanoComDesconto,
                    'comissao_percentual' => round($comissaoReal, 2), // Atualizar comissão REAL calculada
                    'valor_comissao' => round($valorComissao, 2),
                    'status' => 'ativa',
                    'primeira_assinatura_em' => now(),
                ]);

                return $indicacaoExistente->fresh();
            }

            // Criar nova indicação
            $indicacao = AfiliadoIndicacao::create([
                'afiliado_id' => $afiliadoId,
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'empresa_nome' => $empresaNome, // 🔥 Salvar nome da empresa para exibição na UI
                'codigo_usado' => strtoupper(trim($codigoUsado)),
                'desconto_aplicado' => $descontoAplicado,
                'comissao_percentual' => round($comissaoReal, 2), // Salvar comissão REAL calculada (12%, 18% ou 30%)
                'plano_id' => $planoId,
                'plano_nome' => $plano->nome,
                'valor_plano_original' => $valorPlanoOriginal,
                'valor_plano_com_desconto' => $valorPlanoComDesconto,
                'valor_comissao' => $valorComissao,
                'status' => 'ativa',
                'indicado_em' => now(),
                'primeira_assinatura_em' => now(),
                'comissao_paga' => false,
            ]);

            Log::info('CriarIndicacaoAfiliadoUseCase - Indicação criada com sucesso', [
                'indicacao_id' => $indicacao->id,
                'valor_comissao' => $valorComissao,
            ]);

            // 🔥 DDD: Disparar Domain Event se houver comissão inicial (quando valor_comissao > 0)
            if ($valorComissao > 0) {
                $event = new ComissaoGerada(
                    comissaoId: $indicacao->id, // Usar ID da indicação como identificador
                    afiliadoId: $afiliadoId,
                    tenantId: $tenantId,
                    assinaturaId: null, // Será preenchido quando assinatura for criada
                    valor: $valorComissao,
                    tipo: 'inicial',
                    status: 'pendente',
                );
                $this->eventDispatcher->dispatch($event);
            }

            return $indicacao;
        });
    }
}


