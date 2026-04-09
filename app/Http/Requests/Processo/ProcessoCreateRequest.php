<?php

namespace App\Http\Requests\Processo;

use App\Rules\DbTypeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Form Request para criacao de processo.
 */
class ProcessoCreateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('modalidade')) {
            $this->merge([
                'modalidade' => $this->normalizarModalidade($this->input('modalidade')),
            ]);
        }
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

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orgao_id' => ['required', 'exists:orgaos,id'],
            'setor_id' => [DbTypeRule::nullable(), 'exists:setors,id'],
            'modalidade' => ['required', ...DbTypeRule::enum(['dispensa', 'pregao'])],
            'numero_modalidade' => ['required', ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'numero_processo_administrativo' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'link_edital' => [DbTypeRule::nullable(), ...DbTypeRule::url(500)],
            'portal' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'numero_edital' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'srp' => [...DbTypeRule::boolean()],
            'objeto_resumido' => ['required', ...DbTypeRule::text()],
            'data_hora_sessao_publica' => ['required', ...DbTypeRule::datetime()],
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
            'observacoes' => [DbTypeRule::nullable(), ...DbTypeRule::observacao()],
        ];
    }
}

