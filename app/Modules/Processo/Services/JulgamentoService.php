<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Modules\Processo\Services\ProcessoStatusService;
use Illuminate\Support\Facades\Validator;

/**
 * Service para gerenciar julgamento de itens de processo
 */
class JulgamentoService
{
    protected ProcessoStatusService $statusService;

    public function __construct(ProcessoStatusService $statusService)
    {
        $this->statusService = $statusService;
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
     * Validar dados de julgamento
     */
    public function validateJulgamentoData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:processo_itens,id',
            'itens.*.status_item' => 'required|in:pendente,aceito,aceito_habilitado,desclassificado,inabilitado',
            'itens.*.valor_negociado' => 'nullable|numeric|min:0',
            'itens.*.chance_arremate' => 'nullable|in:baixa,media,alta',
            'itens.*.chance_percentual' => 'nullable|integer|min:0|max:100',
            'itens.*.lembretes' => 'nullable|string',
        ]);
    }

    /**
     * Validar processo pode ser editado
     */
    public function validarProcessoPodeEditar(Processo $processo): void
    {
        if ($processo->isEmExecucao()) {
            throw new \Exception('Não é possível editar julgamento de processos em execução.');
        }
    }

    /**
     * Registrar julgamento de itens
     */
    public function registrarJulgamento(Processo $processo, array $itensData): Processo
    {
        // Validar processo pode ser editado
        $this->validarProcessoPodeEditar($processo);

        // Validar dados
        $validator = $this->validateJulgamentoData(['itens' => $itensData]);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $todosDesclassificadosOuInabilitados = true;
        $temAceito = false;

        foreach ($itensData as $itemData) {
            $item = ProcessoItem::find($itemData['id']);
            if (!$item || $item->processo_id !== $processo->id) {
                continue;
            }

            $item->update([
                'status_item' => $itemData['status_item'],
                'valor_negociado' => $itemData['valor_negociado'] ?? null,
                'chance_arremate' => $itemData['chance_arremate'] ?? null,
                'chance_percentual' => $itemData['chance_percentual'] ?? null,
                'lembretes' => $itemData['lembretes'] ?? null,
            ]);

            if (in_array($itemData['status_item'], ['aceito', 'aceito_habilitado'])) {
                $temAceito = true;
                $todosDesclassificadosOuInabilitados = false;
            } elseif (!in_array($itemData['status_item'], ['desclassificado', 'inabilitado'])) {
                $todosDesclassificadosOuInabilitados = false;
            }
        }

        // Verificar se processo deve mudar de status
        // A lógica de sugestão de status é feita pelo ProcessoStatusService
        if ($todosDesclassificadosOuInabilitados && $processo->status === 'julgamento_habilitacao') {
            // Sistema sugere PERDIDO, mas não muda automaticamente
        } elseif ($temAceito && $processo->status === 'participacao') {
            // Se houver item aceito e ainda está em participação, sugerir mudança para julgamento
            if ($processo->data_hora_sessao_publica && $processo->data_hora_sessao_publica->isPast()) {
                // Sugerir mudança manual
            }
        }

        return $processo->fresh();
    }
}

