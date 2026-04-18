<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Application\Afiliado\DTOs\CriarPagamentoComissaoDTO;
use App\Models\AfiliadoComissaoRecorrente;
use App\Models\AfiliadoPagamentoComissao;
use App\Application\Auditoria\UseCases\RegistrarAuditoriaUseCase;
use App\Domain\Auditoria\Enums\AuditAction;
use App\Domain\Shared\ValueObjects\RequestContext;
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
    public function __construct(
        private readonly RegistrarAuditoriaUseCase $registrarAuditoriaUseCase,
    ) {}

    /**
     * Executa o use case
     */
    public function executar(CriarPagamentoComissaoDTO $dto, ?int $pagoPor = null): AfiliadoPagamentoComissao
    {
        return DB::transaction(function () use ($dto, $pagoPor) {
            // Buscar comissões
            // 🔥 Permitir pagar comissões 'disponivel' ou 'pendente' (mas priorizar disponivel)
            $comissoes = AfiliadoComissaoRecorrente::whereIn('id', $dto->comissaoIds)
                ->where('afiliado_id', $dto->afiliadoId)
                ->whereIn('status', ['pendente', 'disponivel'])
                ->get();

            if ($comissoes->isEmpty()) {
                throw new DomainException('Nenhuma comissão disponível ou pendente encontrada para os IDs fornecidos.');
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

            // 🔥 DDD: Registrar auditoria do pagamento de comissões
            $this->registrarAuditoriaUseCase->executar(
                action: AuditAction::COMMISSION_GENERATED,
                modelType: 'App\\Models\\AfiliadoPagamentoComissao',
                modelId: $pagamento->id,
                newValues: [
                    'id' => $pagamento->id,
                    'afiliado_id' => $dto->afiliadoId,
                    'valor_total' => $valorTotal,
                    'quantidade_comissoes' => $comissoes->count(),
                    'periodo_competencia' => $dto->periodoCompetencia,
                    'status' => 'pago',
                ],
                description: "Pagamento de comissões criado: R$ " . number_format($valorTotal, 2, ',', '.') . " para {$comissoes->count()} comissão(ões)",
                context: RequestContext::fromRequest(),
            );

            return $pagamento->load('afiliado');
        });
    }
}

