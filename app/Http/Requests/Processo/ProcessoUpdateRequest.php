<?php

namespace App\Http\Requests\Processo;

use App\Modules\Processo\Models\Processo;
use App\Rules\DbTypeRule;
use App\Rules\NumeroProcessoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Form Request para atualizacao de processo.
 */
class ProcessoUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('modalidade')) {
            $this->merge([
                'modalidade' => $this->normalizarModalidade($this->input('modalidade')),
            ]);
        }

        $this->normalizarDateTimesApi();
    }

    private function normalizarModalidade(?string $modalidade): ?string
    {
        if ($modalidade === null) {
            return null;
        }

        $normalizada = Str::of($modalidade)->trim()->lower()->ascii()->toString();

        return match ($normalizada) {
            'dispensa', 'dispensa eletronica', 'dispensa eletronico' => 'dispensa',
            'pregao', 'pregao eletronico', 'pregao eletronica' => 'pregao',
            default => trim($modalidade),
        };
    }

    /**
     * datetime-local envia "Y-m-d\TH:i"; a API valida "Y-m-d H:i:s".
     */
    private function normalizarDateTimesApi(): void
    {
        foreach (['data_hora_sessao_publica', 'data_hora_inicio_disputa'] as $field) {
            if (! $this->has($field)) {
                continue;
            }
            $v = $this->input($field);
            if (! is_string($v) || trim($v) === '') {
                continue;
            }
            $normalized = str_replace('T', ' ', trim($v));
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
                $normalized .= ':00';
            }
            if ($normalized !== trim($v)) {
                $this->merge([$field => $normalized]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = app(\App\Services\ApplicationContext::class)->getEmpresaIdOrNull();
        $routeProcesso = $this->route('processo');
        $processoId = $routeProcesso instanceof Processo
            ? (int) $routeProcesso->getKey()
            : (int) $routeProcesso;

        // Na atualização, o mesmo número/ano do próprio processo deve ser ignorado na unique.
        // Preferir ignore($model): rota tipada com {processo} entrega o Eloquent, não o id escalar.
        $uniqueNumeroModalidade = Rule::unique('processos', 'numero_modalidade')
            ->where('empresa_id', $empresaId)
            ->whereNull('excluido_em');
        if ($routeProcesso instanceof Processo) {
            $uniqueNumeroModalidade = $uniqueNumeroModalidade->ignore($routeProcesso);
        } elseif ($processoId > 0) {
            $uniqueNumeroModalidade = $uniqueNumeroModalidade->ignore($processoId);
        }

        return [
            'orgao_id' => ['sometimes', 'exists:orgaos,id'],
            'setor_id' => [DbTypeRule::nullable(), 'exists:setors,id'],
            'modalidade' => ['sometimes', ...DbTypeRule::enum(['dispensa', 'pregao'])],
            'numero_modalidade' => [
                'sometimes',
                ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT),
                new NumeroProcessoRule(),
                $uniqueNumeroModalidade,
            ],
            'numero_processo_administrativo' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'link_edital' => [DbTypeRule::nullable(), ...DbTypeRule::url(500)],
            'portal' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'numero_edital' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'srp' => [...DbTypeRule::boolean()],
            'objeto_resumido' => ['sometimes', ...DbTypeRule::text()],
            'data_hora_sessao_publica' => ['sometimes', ...DbTypeRule::datetime()],
            'horario_sessao_publica' => [DbTypeRule::nullable(), ...DbTypeRule::time()],
            'endereco_entrega' => [DbTypeRule::nullable(), ...DbTypeRule::string(500)],
            'local_entrega_detalhado' => [DbTypeRule::nullable(), ...DbTypeRule::text()],
            'forma_entrega' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'prazo_entrega' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'forma_prazo_entrega' => [DbTypeRule::nullable(), ...DbTypeRule::text()],
            'prazos_detalhados' => [DbTypeRule::nullable(), ...DbTypeRule::text()],
            'prazo_pagamento' => [DbTypeRule::nullable(), ...DbTypeRule::text()],
            'validade_proposta' => [DbTypeRule::nullable(), ...DbTypeRule::text()],
            'validade_proposta_inicio' => [DbTypeRule::nullable(), ...DbTypeRule::date()],
            'validade_proposta_fim' => [DbTypeRule::nullable(), ...DbTypeRule::date()],
            'tipo_selecao_fornecedor' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'tipo_disputa' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'status' => [DbTypeRule::nullable(), ...DbTypeRule::enum(['rascunho', 'publicado', 'participacao', 'em_disputa', 'julgamento', 'julgamento_habilitacao', 'vencido', 'perdido', 'execucao', 'pagamento', 'encerramento', 'arquivado'])],
            'status_participacao' => [DbTypeRule::nullable(), ...DbTypeRule::enum(['normal', 'adiado', 'suspenso', 'cancelado'])],
            'data_recebimento_pagamento' => [DbTypeRule::nullable(), ...DbTypeRule::date()],
            'observacoes' => [DbTypeRule::nullable(), ...DbTypeRule::observacao()],
        ];
    }

    public function attributes(): array
    {
        return [
            'orgao_id' => 'órgão',
            'setor_id' => 'setor',
            'modalidade' => 'modalidade',
            'numero_modalidade' => 'número/ano',
            'numero_processo_administrativo' => 'número do processo administrativo',
            'link_edital' => 'link do edital',
            'portal' => 'portal',
            'numero_edital' => 'número do edital',
            'srp' => 'SRP',
            'objeto_resumido' => 'objeto resumido',
            'data_hora_sessao_publica' => 'data e hora da sessão pública',
            'horario_sessao_publica' => 'horário da sessão pública',
            'endereco_entrega' => 'endereço de entrega',
            'local_entrega_detalhado' => 'local de entrega',
            'forma_entrega' => 'forma de entrega',
            'prazo_entrega' => 'prazo de entrega',
            'forma_prazo_entrega' => 'forma do prazo de entrega',
            'prazos_detalhados' => 'prazos detalhados',
            'prazo_pagamento' => 'prazo de pagamento',
            'validade_proposta' => 'validade da proposta',
            'validade_proposta_inicio' => 'início da validade da proposta',
            'validade_proposta_fim' => 'fim da validade da proposta',
            'tipo_selecao_fornecedor' => 'tipo de seleção do fornecedor',
            'tipo_disputa' => 'tipo de disputa',
            'status' => 'status',
            'status_participacao' => 'status de participação',
            'data_recebimento_pagamento' => 'data de recebimento do pagamento',
            'observacoes' => 'observações',
        ];
    }

    public function messages(): array
    {
        return [
            'orgao_id.exists' => 'O órgão informado não existe ou foi removido.',
            'setor_id.exists' => 'O setor informado não existe ou não pertence ao órgão selecionado.',
            'modalidade.in' => 'Selecione uma modalidade válida (pregão ou dispensa).',
            'numero_modalidade.unique' => 'Este número/ano já está cadastrado para sua empresa. Use outro número ou outro ano.',
            'objeto_resumido.required' => 'Informe o objeto resumido.',
            'data_hora_sessao_publica.date_format' => 'Use a data e hora no formato AAAA-MM-DD HH:MM:SS (ex.: 2026-12-15 14:00:00).',
            'link_edital.url' => 'Informe um link válido para o edital (http ou https).',
            'status.in' => 'O status informado não é válido.',
            'status_participacao.in' => 'O status de participação informado não é válido.',
        ];
    }
}

