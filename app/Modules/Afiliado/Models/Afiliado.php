<?php

namespace App\Modules\Afiliado\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Model de Afiliado (Tabela Central)
 * 
 * Representa um afiliado/parceiro que pode indicar clientes
 * através de um código de desconto único.
 */
class Afiliado extends Model
{
    use SoftDeletes;

    protected $table = 'afiliados';

    protected $fillable = [
        // Dados Cadastrais
        'nome',
        'documento',
        'tipo_documento',
        'email',
        'telefone',
        'whatsapp',
        
        // Endereço
        'endereco',
        'cidade',
        'estado',
        'cep',
        
        // Código/Token
        'codigo',
        
        // Comissão/Desconto
        'percentual_desconto',
        'percentual_comissao',
        
        // Dados Bancários
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
        'contas_bancarias', // Array de contas bancárias (JSON)
        
        // Status
        'ativo',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'percentual_desconto' => 'decimal:2',
            'percentual_comissao' => 'decimal:2',
            'ativo' => 'boolean',
            'contas_bancarias' => 'array', // Cast para array (JSON)
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot do model
     */
    protected static function boot()
    {
        parent::boot();

        // Gerar código único automaticamente se não fornecido
        static::creating(function ($afiliado) {
            if (empty($afiliado->codigo)) {
                $afiliado->codigo = self::gerarCodigoUnico();
            }
        });
    }

    /**
     * Gera um código único para o afiliado
     */
    public static function gerarCodigoUnico(): string
    {
        do {
            // Gera código no formato: AFF-XXXXXX (6 caracteres alfanuméricos)
            $codigo = 'AFF-' . strtoupper(Str::random(6));
        } while (self::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Relacionamento: Indicações feitas por este afiliado
     */
    public function indicacoes(): HasMany
    {
        return $this->hasMany(AfiliadoIndicacao::class, 'afiliado_id');
    }

    /**
     * Indicações ativas (empresas em dia)
     */
    public function indicacoesAtivas(): HasMany
    {
        return $this->indicacoes()->where('status', 'ativa');
    }

    /**
     * Indicações inadimplentes
     */
    public function indicacoesInadimplentes(): HasMany
    {
        return $this->indicacoes()->where('status', 'inadimplente');
    }

    /**
     * Indicações canceladas
     */
    public function indicacoesCanceladas(): HasMany
    {
        return $this->indicacoes()->where('status', 'cancelada');
    }

    /**
     * Total de indicações
     */
    public function getTotalIndicacoesAttribute(): int
    {
        return $this->indicacoes()->count();
    }

    /**
     * Total de indicações ativas
     */
    public function getTotalIndicacoesAtivasAttribute(): int
    {
        return $this->indicacoesAtivas()->count();
    }

    /**
     * Total de comissões pagas
     */
    public function getTotalComissoesPagasAttribute(): float
    {
        return $this->indicacoes()
            ->where('comissao_paga', true)
            ->sum('valor_comissao') ?? 0;
    }

    /**
     * Total de comissões pendentes
     */
    public function getTotalComissoesPendentesAttribute(): float
    {
        return $this->indicacoes()
            ->where('comissao_paga', false)
            ->whereNotNull('primeira_assinatura_em')
            ->sum('valor_comissao') ?? 0;
    }

    /**
     * Formata o documento (CPF/CNPJ)
     */
    public function getDocumentoFormatadoAttribute(): string
    {
        $doc = preg_replace('/\D/', '', $this->documento);
        
        if ($this->tipo_documento === 'cpf') {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }
        
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
    }

    /**
     * Verifica se o afiliado está ativo
     */
    public function isAtivo(): bool
    {
        return $this->ativo === true;
    }

    /**
     * Scope: Apenas ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope: Busca por termo
     */
    public function scopeBuscar($query, ?string $termo)
    {
        if (empty($termo)) {
            return $query;
        }

        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'ilike', "%{$termo}%")
              ->orWhere('email', 'ilike', "%{$termo}%")
              ->orWhere('documento', 'like', "%{$termo}%")
              ->orWhere('codigo', 'ilike', "%{$termo}%");
        });
    }

}



