<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Rules\DbTypeRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class DisputaService
{
    protected ProcessoStatusService $statusService;

    public function __construct(ProcessoStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Validar dados de disputa
     */
    public function validateDisputaData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'itens' => [DbTypeRule::required(), 'array'],
            'itens.*.id' => [DbTypeRule::required(), 'exists:processo_itens,id'],
            'itens.*.valor_final_sessao' => [DbTypeRule::nullable(), 'numeric', 'min:0'],
            'itens.*.classificacao' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1'],
        ]);
    }

    /**
     * Validar processo pertence à empresa
     */
    public function validarProcessoEmpresa(Processo $processo, int $empresaId): void
    {
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar processo pode ser editado
     */
    public function validarProcessoPodeEditar(Processo $processo): void
    {
        if ($processo->isEmExecucao()) {
            throw new \Exception('Não é possível editar disputa de processos em execução.');
        }
    }

    /**
     * Registra resultados da disputa (valores finais e classificações)
     */
    public function registrarResultados(Processo $processo, array $resultadosItens): Processo
    {
        // Validar processo pode ser editado
        $this->validarProcessoPodeEditar($processo);
        // 🔥 NOVO: Validar status do processo permite disputa
        $this->validarProcessoPermiteDisputa($processo);

        // Garantir que o processo pertence a uma empresa (isolamento)
        if (!$processo->empresa_id) {
            throw new \Exception('Processo não possui empresa_id definido. Não é possível registrar resultados.');
        }

        // Validar dados
        $validator = $this->validateDisputaData(['itens' => $resultadosItens]);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        
        foreach ($resultadosItens as $resultado) {
            // Buscar item pelo id ou item_id (compatibilidade)
            $itemId = $resultado['id'] ?? $resultado['item_id'] ?? null;
            if (!$itemId) {
                continue;
            }
            
            // Buscar item através do relacionamento do processo para garantir isolamento
            $item = $processo->itens()->where('id', $itemId)->first();
            
            if (!$item) {
                continue;
            }

            // Atualizar valores
            if (isset($resultado['valor_final_sessao'])) {
                $item->valor_final_sessao = $resultado['valor_final_sessao'];
            }

            if (isset($resultado['valor_arrematado'])) {
                $item->valor_arrematado = $resultado['valor_arrematado'];
            } elseif (isset($resultado['valor_final_sessao']) && !$item->valor_arrematado) {
                // Se não foi informado valor_arrematado mas tem valor_final_sessao, usar como fallback
                $item->valor_arrematado = $resultado['valor_final_sessao'];
            }

            if (isset($resultado['classificacao'])) {
                $item->classificacao = $resultado['classificacao'];
            }

            if (isset($resultado['observacoes'])) {
                $item->observacoes = $resultado['observacoes'];
            }

            $item->save();
        }

        // Se passou da data da sessão, sugerir mudança para julgamento
        if ($this->statusService->deveSugerirJulgamento($processo)) {
            // Não muda automaticamente, apenas sugere
            // A mudança deve ser confirmada manualmente
        }

        return $processo->fresh();
    }

    /**
     * Registra informações de julgamento e habilitação por item
     */
    public function registrarJulgamento(
        ProcessoItem $item,
        string $statusItem,
        ?int $classificacao = null,
        ?string $chanceArremate = null,
        ?int $chancePercentual = null,
        ?bool $temChance = null,
        ?float $valorNegociado = null,
        ?string $lembretes = null,
        ?string $observacoes = null
    ): ProcessoItem {
        // Validar status do item
        $statusPermitidos = ['aceito', 'aceito_habilitado', 'desclassificado', 'inabilitado'];
        if (!in_array($statusItem, $statusPermitidos)) {
            throw new \InvalidArgumentException("Status de item inválido: {$statusItem}");
        }

        $item->status_item = $statusItem;

        if ($classificacao !== null) {
            $item->classificacao = $classificacao;
        }

        if ($chanceArremate !== null) {
            $item->chance_arremate = $chanceArremate;
        }

        if ($chancePercentual !== null) {
            $item->chance_percentual = $chancePercentual;
        }

        if ($temChance !== null) {
            $item->tem_chance = $temChance;
        }

        // Valor negociado não apaga o valor anterior, apenas adiciona
        if ($valorNegociado !== null) {
            $item->valor_negociado = $valorNegociado;
        }

        if ($lembretes !== null) {
            $item->lembretes = $lembretes;
        }

        if ($observacoes !== null) {
            $item->observacoes = $observacoes;
        }

        $item->save();

        // Verificar se processo deve mudar de status
        $processo = $item->processo;
        
        // Se todos os itens estão perdidos, sugerir perdido
        if ($this->statusService->deveSugerirPerdido($processo)) {
            // Sugestão será feita pela interface
        }

        return $item->fresh();
    }
}




