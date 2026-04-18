<?php

namespace App\Modules\Orcamento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasEmpresaScope;
use App\Models\Traits\BelongsToEmpresaTrait;

class Notificacao extends BaseModel
{
    use HasFactory, HasEmpresaScope, BelongsToEmpresaTrait;

    protected $table = 'notificacoes';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'tipo',
        'titulo',
        'mensagem',
        'orcamento_id',
        'processo_id',
        'lido',
        'lido_em',
        'dados_adicionais'
    ];

    protected function casts(): array
    {
        return [
            'lido' => 'boolean',
            'lido_em' => 'datetime',
            'dados_adicionais' => 'array'
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
    }

    public function orcamento()
    {
        return $this->belongsTo(Orcamento::class, 'orcamento_id');
    }

    public function processo()
    {
        return $this->belongsTo(\App\Modules\Processo\Models\Processo::class, 'processo_id');
    }

    public static function criar($usuarioId, $empresaId, $tipo, $titulo, $mensagem, $dados = [])
    {
        return self::create([
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'orcamento_id' => $dados['orcamento_id'] ?? null,
            'processo_id' => $dados['processo_id'] ?? null,
            'dados_adicionais' => collect($dados)->except(['orcamento_id', 'processo_id'])->toArray(),
            'lido' => false
        ]);
    }

    public function marcarComoLida()
    {
        $this->update([
            'lido' => true,
            'lido_em' => now()
        ]);
        return $this;
    }
}
