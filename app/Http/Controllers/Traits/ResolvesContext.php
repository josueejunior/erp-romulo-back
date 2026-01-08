<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoItem\Repositories\ProcessoItemRepositoryInterface;
use App\Domain\Orcamento\Repositories\OrcamentoRepositoryInterface;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

/**
 * Trait para resolver contexto de entidades relacionadas
 * 
 * ✅ DDD: Elimina repetição de código de resolução de contexto
 * Centraliza validação de existência e vínculos
 */
trait ResolvesContext
{
    /**
     * Resolver contexto completo (processo, item, orçamento)
     * 
     * @throws NotFoundException se alguma entidade não for encontrada
     */
    protected function resolveContext(Request $request): array
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');
        $orcamentoId = $request->route()->parameter('orcamento');

        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }

        $item = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$item) {
            throw new NotFoundException('Item', $itemId);
        }

        $orcamento = $this->orcamentoRepository->buscarModeloPorId($orcamentoId);
        if (!$orcamento) {
            throw new NotFoundException('Orçamento', $orcamentoId);
        }

        return [$processo, $item, $orcamento];
    }

    /**
     * Resolver apenas processo e item
     * 
     * @throws NotFoundException se alguma entidade não for encontrada
     */
    protected function resolveProcessoItem(Request $request): array
    {
        $processoId = $request->route()->parameter('processo');
        $itemId = $request->route()->parameter('item');

        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }

        $item = $this->processoItemRepository->buscarModeloPorId($itemId);
        if (!$item) {
            throw new NotFoundException('Item', $itemId);
        }

        return [$processo, $item];
    }

    /**
     * Resolver processo e contrato
     * 
     * @throws NotFoundException se alguma entidade não for encontrada
     */
    protected function resolveProcessoContrato(Request $request): array
    {
        $processoId = $request->route()->parameter('processo');
        $contratoId = $request->route()->parameter('contrato');

        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }

        $contrato = $this->contratoRepository->buscarModeloPorId($contratoId);
        if (!$contrato) {
            throw new NotFoundException('Contrato', $contratoId);
        }

        return [$processo, $contrato];
    }

    /**
     * Resolver apenas processo
     * 
     * @throws NotFoundException se processo não for encontrado
     */
    protected function resolveProcesso(Request $request): \App\Modules\Processo\Models\Processo
    {
        $processoId = $request->route()->parameter('processo');

        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }

        return $processo;
    }

    /**
     * Resolver processo e autorização de fornecimento
     * 
     * @throws NotFoundException se alguma entidade não for encontrada
     */
    protected function resolveProcessoAutorizacao(Request $request): array
    {
        $processoId = $request->route()->parameter('processo');
        $autorizacaoId = $request->route()->parameter('autorizacaoFornecimento');

        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo) {
            throw new NotFoundException('Processo', $processoId);
        }

        $autorizacao = $this->autorizacaoFornecimentoRepository->buscarModeloPorId($autorizacaoId);
        if (!$autorizacao) {
            throw new NotFoundException('Autorização de Fornecimento', $autorizacaoId);
        }

        return [$processo, $autorizacao];
    }
}

