<?php

namespace App\Modules\Processo\Services;

use App\Services\BaseService;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use App\Rules\DbTypeRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Service para gerenciar itens de processo
 * Estende BaseService que aplica filtro automático por empresa_id
 */
class ProcessoItemService extends BaseService
{
    /**
     * Model class name
     */
    protected static string $model = ProcessoItem::class;

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
     * Validar item pertence à empresa
     */
    public function validarItemEmpresa(ProcessoItem $item, int $empresaId): void
    {
        if ($item->empresa_id !== $empresaId) {
            throw new \Exception('Item não encontrado ou não pertence à empresa ativa.');
        }
    }

    /**
     * Validar se processo pode ser editado
     */
    public function validarProcessoPodeEditar(Processo $processo): void
    {
        if ($processo->isEmExecucao()) {
            throw new \Exception('Não é possível editar itens de processos em execução.');
        }
    }

    /**
     * Validar se item pertence ao processo
     */
    public function validarItemPertenceProcesso(ProcessoItem $item, Processo $processo): void
    {
        if ($item->processo_id !== $processo->id) {
            throw new \Exception('Item não pertence ao processo informado.');
        }
    }

    /**
     * Calcular próximo número de item
     */
    public function calcularProximoNumeroItem(Processo $processo): int
    {
        $ultimoItem = $processo->itens()->orderBy('numero_item', 'desc')->first();
        return $ultimoItem ? $ultimoItem->numero_item + 1 : 1;
    }

    /**
     * Validar dados para criação de item
     */
    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'processo_id' => 'required|exists:processos,id',
            'numero_item' => [DbTypeRule::required(), ...DbTypeRule::integer(), 'min:1'],
            'quantidade' => [DbTypeRule::required(), 'numeric', 'min:0.01'],
            'unidade' => [DbTypeRule::required(), ...DbTypeRule::string(50)],
            'especificacao_tecnica' => [DbTypeRule::required(), ...DbTypeRule::text()],
            'marca_modelo_referencia' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'exige_atestado' => [...DbTypeRule::boolean()],
            'quantidade_minima_atestado' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1', 'required_if:exige_atestado,1'],
            'valor_estimado' => [DbTypeRule::nullable(), 'numeric', 'min:0'],
            'observacoes' => [DbTypeRule::nullable(), ...DbTypeRule::observacao()],
        ]);
    }

    /**
     * Criar novo item
     */
    public function store(Processo $processo, array $data): Model
    {
        // Validar processo pode ser editado
        $this->validarProcessoPodeEditar($processo);

        // Validar dados
        $validator = $this->validateStoreData(array_merge($data, ['processo_id' => $processo->id]));
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['processo_id'] = $processo->id;
        $validated['exige_atestado'] = isset($data['exige_atestado']) && $data['exige_atestado'];
        $validated['status_item'] = 'pendente';

        return parent::store($validated);
    }

    /**
     * Validar dados para atualização de item
     */
    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'numero_item' => ['sometimes', ...DbTypeRule::integer(), 'min:1'],
            'quantidade' => ['sometimes', 'numeric', 'min:0.01'],
            'unidade' => ['sometimes', ...DbTypeRule::string(50)],
            'especificacao_tecnica' => ['sometimes', ...DbTypeRule::text()],
            'marca_modelo_referencia' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'exige_atestado' => [...DbTypeRule::boolean()],
            'quantidade_minima_atestado' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1', 'required_if:exige_atestado,1'],
            'valor_estimado' => [DbTypeRule::nullable(), 'numeric', 'min:0'],
            'observacoes' => [DbTypeRule::nullable(), ...DbTypeRule::observacao()],
        ]);
    }

    /**
     * Atualizar item
     */
    public function update(Processo $processo, ProcessoItem $item, array $data): Model
    {
        // Validar processo pode ser editado
        $this->validarProcessoPodeEditar($processo);

        // Validar item pertence ao processo
        $this->validarItemPertenceProcesso($item, $processo);

        // Validar dados
        $validator = $this->validateUpdateData($data, $item->id);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['exige_atestado'] = isset($data['exige_atestado']) && $data['exige_atestado'];

        return parent::update($item->id, $validated);
    }

    /**
     * Excluir item
     */
    public function delete(Processo $processo, ProcessoItem $item): bool
    {
        // Validar processo pode ser editado
        $this->validarProcessoPodeEditar($processo);

        // Validar item pertence ao processo
        $this->validarItemPertenceProcesso($item, $processo);

        return $item->delete();
    }
}

