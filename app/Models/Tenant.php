<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use App\Modules\Assinatura\Models\Plano;
use App\Modules\Assinatura\Models\Assinatura;
use App\Models\Traits\HasTimestampsCustomizados;
use App\Database\Schema\Blueprint;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasTimestampsCustomizados;

    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    public $timestamps = true;

    /**
     * Colunas que podem ser preenchidas em massa
     */
    protected $fillable = [
        'razao_social',
        'cnpj',
        'email',
        'status',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'telefones',
        'emails_adicionais',
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
        'representante_legal_nome',
        'representante_legal_cpf',
        'representante_legal_cargo',
        'logo',
        'plano_atual_id',
        'assinatura_atual_id',
        'limite_processos',
        'limite_usuarios',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'razao_social',
            'cnpj',
            'email',
            'status',
            'endereco',
            'cidade',
            'estado',
            'cep',
            'telefones',
            'emails_adicionais',
            'banco',
            'agencia',
            'conta',
            'tipo_conta',
            'pix',
            'representante_legal_nome',
            'representante_legal_cpf',
            'representante_legal_cargo',
            'logo',
            'plano_atual_id',
            'assinatura_atual_id',
            'limite_processos',
            'limite_usuarios',
        ];
    }

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'telefones' => 'array',
            'emails_adicionais' => 'array',
            'data' => 'array', // Campo JSON usado pelo BaseTenant para dados customizados
        ]);
    }

    /**
     * Sobrescrever toArray para garantir que todas as colunas customizadas sejam retornadas
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Garantir que todas as colunas customizadas estejam no array
        $customColumns = self::getCustomColumns();
        foreach ($customColumns as $column) {
            if (!isset($array[$column])) {
                // Tentar obter o atributo diretamente
                $value = $this->getAttribute($column);
                if ($value !== null || array_key_exists($column, $this->getAttributes())) {
                    $array[$column] = $value;
                }
            }
        }
        
        return $array;
    }

    /**
     * Relacionamento com plano atual
     */
    public function planoAtual()
    {
        return $this->belongsTo(Plano::class, 'plano_atual_id');
    }

    /**
     * Relacionamento com assinatura atual
     */
    public function assinaturaAtual()
    {
        return $this->belongsTo(Assinatura::class, 'assinatura_atual_id');
    }

    /**
     * Relacionamento com todas as assinaturas
     */
    public function assinaturas()
    {
        return $this->hasMany(Assinatura::class, 'tenant_id');
    }

    /**
     * Verifica se o tenant tem assinatura ativa
     */
    public function temAssinaturaAtiva(): bool
    {
        return $this->assinaturaAtual && $this->assinaturaAtual->isAtiva();
    }

    /**
     * Verifica se pode criar processo (dentro do limite)
     */
    public function podeCriarProcesso(): bool
    {
        if (!$this->temAssinaturaAtiva()) {
            return false;
        }

        $plano = $this->planoAtual;
        
        // Se não tem limite, pode criar
        if (!$plano || $plano->temProcessosIlimitados()) {
            return true;
        }

        // Contar processos ativos no tenant
        // Nota: Este método deve ser chamado dentro do contexto do tenant
        // Para contar corretamente, precisa estar no banco do tenant
        try {
            $jaInicializado = tenancy()->initialized;
            if (!$jaInicializado) {
                tenancy()->initialize($this);
            }
            
            $processosAtivos = \App\Models\Processo::count();
            
            if (!$jaInicializado) {
                tenancy()->end();
            }
            
            return $processosAtivos < $plano->limite_processos;
        } catch (\Exception $e) {
            // Se houver erro, assumir que pode criar (fail open)
            \Log::warning('Erro ao verificar limite de processos', [
                'tenant_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    /**
     * Verifica se pode adicionar usuário (dentro do limite)
     */
    public function podeAdicionarUsuario(): bool
    {
        if (!$this->temAssinaturaAtiva()) {
            return false;
        }

        $plano = $this->planoAtual;
        
        // Se não tem limite, pode adicionar
        if (!$plano || $plano->temUsuariosIlimitados()) {
            return true;
        }

        // Contar usuários vinculados ao tenant
        $usuarios = \App\Models\User::whereHas('empresas', function($query) {
            $query->where('empresas.id', $this->id);
        })->count();

        return $usuarios < $plano->limite_usuarios;
    }
}
