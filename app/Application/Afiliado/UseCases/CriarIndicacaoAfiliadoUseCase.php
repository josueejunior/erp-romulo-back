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
        float $valorPlanoComDesconto
    ): AfiliadoIndicacao {
        return DB::transaction(function () use (
            $afiliadoId,
            $tenantId,
            $empresaId,
            $codigoUsado,
            $descontoAplicado,
            $planoId,
            $valorPlanoOriginal,
            $valorPlanoComDesconto
        ) {
            // Buscar afiliado para obter percentual de comissão
            $afiliado = Afiliado::find($afiliadoId);
            if (!$afiliado) {
                throw new \DomainException('Afiliado não encontrado.');
            }

            // Buscar plano para obter nome
            $plano = Plano::find($planoId);
            if (!$plano) {
                throw new \DomainException('Plano não encontrado.');
            }

            // Calcular comissão baseada no valor EFETIVAMENTE PAGO (com desconto)
            $comissaoPercentual = $afiliado->percentual_comissao ?? 0;
            $valorComissao = ($valorPlanoComDesconto * $comissaoPercentual) / 100;

            Log::info('CriarIndicacaoAfiliadoUseCase - Criando indicação', [
                'afiliado_id' => $afiliadoId,
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'plano_id' => $planoId,
                'valor_original' => $valorPlanoOriginal,
                'valor_com_desconto' => $valorPlanoComDesconto,
                'comissao_percentual' => $comissaoPercentual,
                'valor_comissao' => $valorComissao,
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
                    'valor_comissao' => $valorComissao,
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
                'codigo_usado' => strtoupper(trim($codigoUsado)),
                'desconto_aplicado' => $descontoAplicado,
                'comissao_percentual' => $comissaoPercentual,
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


