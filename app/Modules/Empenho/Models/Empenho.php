<?php

namespace App\Modules\Empenho\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseModel;
use App\Models\Traits\HasSoftDeletesWithEmpresa;
use App\Models\Traits\BelongsToEmpresaTrait;
use App\Modules\Processo\Models\Processo;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Modules\NotaFiscal\Models\NotaFiscal;

class Empenho extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;

    protected $fillable = [
        'empresa_id',
        'processo_id',
        'contrato_id',
        'autorizacao_fornecimento_id',
        'numero',
        'data',
        'data_recebimento',
        'prazo_entrega_calculado',
        'valor',
        'concluido',
        'situacao',
        'data_entrega',
        'observacoes',
        'numero_cte',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'data_recebimento' => 'date',
            'prazo_entrega_calculado' => 'date',
            'data_entrega' => 'date',
            'valor' => 'decimal:2',
            'concluido' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(Processo::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function autorizacaoFornecimento(): BelongsTo
    {
        return $this->belongsTo(AutorizacaoFornecimento::class);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function concluir(): void
    {
        $this->concluido = true;
        $this->situacao = 'concluido';
        $this->data_entrega = now();
        $this->save();

        if ($this->contrato_id) {
            $this->contrato->atualizarSaldo();
        }

        if ($this->autorizacao_fornecimento_id) {
            $this->autorizacaoFornecimento->atualizarSaldo();
        }
    }

    /**
     * Atualiza a situação do empenho baseado em prazos
     */
    public function atualizarSituacao(): void
    {
        if ($this->concluido) {
            $this->situacao = 'concluido';
            $this->save();
            return;
        }

        if (!$this->data_recebimento || !$this->prazo_entrega_calculado) {
            $this->situacao = 'aguardando_entrega';
            $this->save();
            return;
        }

        $hoje = now();
        $prazo = \Carbon\Carbon::parse($this->prazo_entrega_calculado);

        if ($hoje->isAfter($prazo) && !$this->data_entrega) {
            $this->situacao = 'atrasado';
        } elseif ($this->data_entrega) {
            $this->situacao = 'atendido';
        } else {
            $this->situacao = 'em_atendimento';
        }

        $this->save();
    }

    /**
     * Atualiza saldo do empenho baseado nas notas fiscais vinculadas
     */
    public function atualizarSaldo(): void
    {
        // Atualizar situação baseado em prazos e notas fiscais
        $this->atualizarSituacao();
        
        // Se houver notas fiscais de saída pagas, considerar como atendido
        $notasPagas = $this->notasFiscais()
            ->where('tipo', 'saida')
            ->where('situacao', 'paga')
            ->sum('valor') ?? 0;
        
        // Se o valor pago for igual ou maior ao valor do empenho, marcar como concluído
        if ($notasPagas >= $this->valor && !$this->concluido) {
            $this->concluido = true;
            $this->situacao = 'concluido';
            if (!$this->data_entrega) {
                $this->data_entrega = now();
            }
            $this->save();
        }
    }
}


