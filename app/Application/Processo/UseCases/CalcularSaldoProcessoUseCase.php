<?php

namespace App\Application\Processo\UseCases;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Processo\Services\CalculoSaldoService;
use App\Domain\Exceptions\ProcessoNaoEncontradoException;

class CalcularSaldoProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private CalculoSaldoService $calculoSaldoService
    ) {}

    /**
     * Calcula o saldo completo de um processo
     * 
     * @param int $processoId
     * @param int $empresaId
     * @return array
     */
    public function executar(int $processoId, int $empresaId): array
    {
        // 1. Buscar Processo (Entity) via Repository
        $processo = $this->processoRepository->buscarPorId($processoId);

        if (!$processo || $processo->empresaId !== $empresaId) {
            throw new ProcessoNaoEncontradoException();
        }

        // 2. Carregar dados necessários (Itens, Contratos, Empenhos)
        // Nota: Em uma implementação pura, o repository retornaria o agregado completo.
        // Aqui, pragmáticamente, podemos buscar os relacionamentos ou delegar para o repository.
        // Para simplificar a migração, vamos assumir que o repository pode carregar o que precisamos
        // ou usamos serviços de domínio auxiliares.
        
        // Adaptação: Vamos usar o modelo eloquent temporariamente para extrair os dados
        // até que os Repositories retornem agregados completos
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId, ['itens', 'contratos', 'autorizacoesFornecimento', 'empenhos']);

        // 3. Executar cálculos usando Domain Service
        // Aqui convertemos os modelos para arrays/estruturas que o serviço de domínio entende
        // Isso isola a lógica de cálculo da infraestrutura (Eloquent)
        
        $itensData = $processoModel->itens->map(fn($i) => $i->toArray())->toArray();
        $contratosData = $processoModel->contratos->map(fn($c) => $c->toArray())->toArray();
        $afsData = $processoModel->autorizacoesFornecimento->map(fn($af) => $af->toArray())->toArray();
        $empenhosData = $processoModel->empenhos->map(fn($e) => $e->toArray())->toArray();

        // Lógica de domínio puro
        $saldoVencido = $this->calculoSaldoService->calcularPotencialFinanceiro($itensData);
        $totalVinculado = $this->calculoSaldoService->calcularSaldoVinculado($contratosData, $afsData);
        $totalEmpenhado = $this->calculoSaldoService->calcularSaldoEmpenhado($empenhosData);

        $saldoNaoVinculado = $saldoVencido - $totalVinculado;

        return [
            'saldo_vencido' => [
                'saldo_vencido' => $saldoVencido,
                'quantidade_itens' => count(array_filter($itensData, fn($i) => in_array($i['status_item'], ['aceito', 'aceito_habilitado', 'aguardando_entrega', 'execucao'])))
            ],
            'saldo_vinculado' => [
                'total_vinculado' => $totalVinculado,
                'detalhes' => ['contratos' => count($contratosData), 'afs' => count($afsData)]
            ],
            'saldo_empenhado' => [
                'valor_total_empenhado' => $totalEmpenhado
            ],
            'saldo_nao_vinculado' => round($saldoNaoVinculado, 2),
            'resumo' => [
                'total_vencido' => $saldoVencido,
                'total_vinculado' => $totalVinculado,
                'total_empenhado' => $totalEmpenhado,
                'total_pendente' => $saldoVencido - $totalEmpenhado // Exemplo simplificado
            ]
        ];
    }
}
