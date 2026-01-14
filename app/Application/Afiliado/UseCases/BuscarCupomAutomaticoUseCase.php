<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Models\AfiliadoReferencia;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Buscar Cupom Automático
 * 
 * Busca cupom de afiliado vinculado ao tenant para exibição na tela de planos
 */
final class BuscarCupomAutomaticoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(int $tenantId): array
    {
        Log::debug('BuscarCupomAutomaticoUseCase::executar', [
            'tenant_id' => $tenantId,
        ]);

        // Buscar referência de afiliado vinculada ao tenant
        $referencia = AfiliadoReferencia::where('tenant_id', $tenantId)
            ->where('cadastro_concluido', true)
            ->with('afiliado')
            ->orderBy('cadastro_concluido_em', 'desc')
            ->first();

        if (!$referencia || !$referencia->afiliado) {
            throw new DomainException('Nenhum cupom disponível.');
        }

        // Verificar se o afiliado está ativo
        if (!$referencia->afiliado->ativo) {
            throw new DomainException('Afiliado inativo.');
        }

        return [
            'cupom_codigo' => $referencia->afiliado->codigo,
            'afiliado_nome' => $referencia->afiliado->nome,
            'desconto_percentual' => $referencia->afiliado->percentual_desconto ?? 30,
            'cupom_aplicado' => $referencia->cupom_aplicado,
            'mensagem' => $referencia->cupom_aplicado 
                ? "Você recebeu um desconto de {$referencia->afiliado->percentual_desconto}% por indicação de {$referencia->afiliado->nome}."
                : "Você recebeu um cupom exclusivo de {$referencia->afiliado->percentual_desconto}% por indicação de {$referencia->afiliado->nome}.",
        ];
    }
}







