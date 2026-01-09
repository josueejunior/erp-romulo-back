<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Application\Afiliado\DTOs\CriarPagamentoComissaoDTO;
use App\Models\AfiliadoComissaoRecorrente;
use App\Models\AfiliadoPagamentoComissao;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Use Case: Criar Pagamento de Comissões
 * 
 * Agrupa múltiplas comissões em um pagamento único
 */
final class CriarPagamentoComissaoUseCase
{
    /**
     * Executa o use case
     */
    public function executar(CriarPagamentoComissaoDTO $dto, ?int $pagoPor = null): AfiliadoPagamentoComissao
    {
        return DB::transaction(function () use ($dto, $pagoPor) {
            // Buscar comissões
            $comissoes = AfiliadoComissaoRecorrente::whereIn('id', $dto->comissaoIds)
                ->where('afiliado_id', $dto->afiliadoId)
                ->where('status', 'pendente')
                ->get();

            if ($comissoes->isEmpty()) {
                throw new DomainException('Nenhuma comissão pendente encontrada para os IDs fornecidos.');
            }

            // Calcular valor total
            $valorTotal = $comissoes->sum('valor_comissao');

            // Criar pagamento
            $pagamento = AfiliadoPagamentoComissao::create([
                'afiliado_id' => $dto->afiliadoId,
                'periodo_competencia' => $dto->periodoCompetencia,
                'data_pagamento' => $dto->dataPagamento ? Carbon::parse($dto->dataPagamento) : Carbon::now(),
                'valor_total' => $valorTotal,
                'quantidade_comissoes' => $comissoes->count(),
                'status' => 'pago',
                'metodo_pagamento' => $dto->metodoPagamento,
                'comprovante' => $dto->comprovante,
                'observacoes' => $dto->observacoes,
                'pago_por' => $pagoPor,
                'pago_em' => Carbon::now(),
            ]);

            // Marcar comissões como pagas
            foreach ($comissoes as $comissao) {
                $comissao->update([
                    'status' => 'paga',
                    'data_pagamento_afiliado' => $pagamento->data_pagamento,
                ]);
            }

            Log::info('CriarPagamentoComissaoUseCase - Pagamento criado', [
                'pagamento_id' => $pagamento->id,
                'afiliado_id' => $dto->afiliadoId,
                'valor_total' => $valorTotal,
                'quantidade_comissoes' => $comissoes->count(),
            ]);

            return $pagamento->load('afiliado');
        });
    }
}

