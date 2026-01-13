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
    
    public $timestamps = true;
    
    /**
     * Usar IDs numéricos auto-incrementados ao invés de strings/slugs
     */
    public $incrementing = true;
    protected $keyType = 'int';
    
    /**
     * Sobrescrever boot para garantir que não há geração automática de UUID
     */
    protected static function boot()
    {
        parent::boot();
        
        // Garantir que o ID não seja gerado automaticamente (deixar o banco fazer isso)
        static::creating(function ($tenant) {
            // Se o ID já foi definido, manter; caso contrário, deixar o banco gerar
            if (isset($tenant->attributes['id']) && !is_numeric($tenant->attributes['id'])) {
                unset($tenant->attributes['id']);
            }
        });
    }
    
    /**
     * Timestamps customizados em português
     */
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    
    /**
     * Sobrescrever métodos do BaseTenant para usar timestamps customizados
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }
    
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

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
        // 🔥 MELHORIA: UTM Tracking (contexto de marketing)
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'fingerprint',
        // 🔥 CACHE: Esses campos são apenas cache/espelho da assinatura do usuário
        // A fonte da verdade é a assinatura do usuário (user_id na tabela assinaturas)
        'plano_atual_id', // Cache do plano_atual_id da assinatura do usuário
        'assinatura_atual_id', // Cache do assinatura_atual_id da assinatura do usuário
        // ❌ REMOVIDO: limite_processos e limite_usuarios - vêm do plano, não do tenant
    ];

    /**
     * Colunas que devem ser visíveis na serialização JSON
     * Garante que todos os campos customizados sejam retornados
     */
    protected $visible = [
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
        // 🔥 CACHE: Esses campos são apenas cache/espelho da assinatura do usuário
        'plano_atual_id', // Cache do plano_atual_id da assinatura do usuário
        'assinatura_atual_id', // Cache do assinatura_atual_id da assinatura do usuário
        'criado_em',
        'atualizado_em',
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
            // 🔥 CACHE: Esses campos são apenas cache/espelho da assinatura do usuário
            'plano_atual_id', // Cache do plano_atual_id da assinatura do usuário
            'assinatura_atual_id', // Cache do assinatura_atual_id da assinatura do usuário
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
        
        // Obter todos os atributos diretamente do modelo (incluindo os que podem não estar no parent::toArray())
        $attributes = $this->getAttributes();
        
        // Garantir que todas as colunas customizadas estejam no array
        $customColumns = self::getCustomColumns();
        foreach ($customColumns as $column) {
            // Se a coluna existe nos atributos, usar o valor (mesmo que seja null)
            if (array_key_exists($column, $attributes)) {
                $array[$column] = $this->getAttribute($column);
            } elseif (!isset($array[$column])) {
                // Se não existe nos atributos nem no array, tentar obter via getAttribute
                $value = $this->getAttribute($column);
                if ($value !== null) {
                    $array[$column] = $value;
                }
            }
        }
        
        return $array;
    }

    /**
     * Relacionamento com plano atual
     * 
     * 🔥 IMPORTANTE: Este é apenas um CACHE/ESPELHO
     * A fonte da verdade é a assinatura do usuário (user_id na tabela assinaturas)
     * Este relacionamento é atualizado automaticamente quando necessário
     */
    public function planoAtual()
    {
        return $this->belongsTo(Plano::class, 'plano_atual_id');
    }

    /**
     * Relacionamento com assinatura atual
     * 
     * 🔥 IMPORTANTE: Este é apenas um CACHE/ESPELHO
     * A fonte da verdade é a assinatura do usuário (user_id na tabela assinaturas)
     * Este relacionamento é atualizado automaticamente quando necessário
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
     * 
     * 🔥 IMPORTANTE: Assinatura pertence ao usuário, não ao tenant
     * Busca a assinatura do usuário autenticado e atualiza o tenant se necessário
     */
    public function temAssinaturaAtiva(): bool
    {
        // Buscar usuário autenticado (priorizar ApplicationContext, fallback para auth())
        $user = null;
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            $context = app(\App\Contracts\ApplicationContextContract::class);
            if ($context->isInitialized()) {
                $user = $context->getUser();
            }
        }
        
        if (!$user) {
            $user = auth()->user();
        }
        
        if (!$user) {
            \Log::warning('Tenant::temAssinaturaAtiva() - Usuário não autenticado', [
                'tenant_id' => $this->id,
            ]);
            return false;
        }

        // Buscar assinatura ativa do usuário (não do tenant)
        $assinaturaRepository = app(\App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface::class);
        $assinaturaDomain = $assinaturaRepository->buscarAssinaturaAtualPorUsuario($user->id);

        if (!$assinaturaDomain) {
            \Log::debug('Tenant::temAssinaturaAtiva() - Assinatura não encontrada para o usuário', [
                'tenant_id' => $this->id,
                'user_id' => $user->id,
                'assinatura_atual_id' => $this->assinatura_atual_id,
            ]);
            return false;
        }

        // Buscar modelo da assinatura para verificar status
        $assinaturaModel = $assinaturaRepository->buscarModeloPorId($assinaturaDomain->id);
        if (!$assinaturaModel) {
            \Log::warning('Tenant::temAssinaturaAtiva() - Modelo de assinatura não encontrado', [
                'tenant_id' => $this->id,
                'user_id' => $user->id,
                'assinatura_domain_id' => $assinaturaDomain->id,
            ]);
            return false;
        }

        $isAtiva = $assinaturaModel->isAtiva();

        // Se a assinatura está ativa e o tenant não tem assinatura_atual_id ou tem uma diferente, atualizar
        if ($isAtiva && ($this->assinatura_atual_id !== $assinaturaModel->id || !$this->plano_atual_id)) {
            $this->update([
                'assinatura_atual_id' => $assinaturaModel->id,
                'plano_atual_id' => $assinaturaModel->plano_id,
            ]);
            $this->refresh();
            
            \Log::info('Tenant::temAssinaturaAtiva() - Tenant atualizado com assinatura do usuário', [
                'tenant_id' => $this->id,
                'user_id' => $user->id,
                'assinatura_id' => $assinaturaModel->id,
                'plano_id' => $assinaturaModel->plano_id,
            ]);
        }

        \Log::debug('Tenant::temAssinaturaAtiva() - Verificação', [
            'tenant_id' => $this->id,
            'user_id' => $user->id,
            'assinatura_id' => $assinaturaModel->id,
            'is_ativa' => $isAtiva,
            'status' => $assinaturaModel->status ?? 'N/A',
            'data_fim' => $assinaturaModel->data_fim ?? 'N/A',
        ]);

        return $isAtiva;
    }

    /**
     * Verifica se pode criar processo (dentro do limite mensal e diário)
     * 
     * 🔥 IMPORTANTE: Busca o plano da assinatura do usuário autenticado
     */
    public function podeCriarProcesso(): bool
    {
        \Log::debug('Tenant::podeCriarProcesso() - Iniciando verificação', [
            'tenant_id' => $this->id,
            'assinatura_atual_id' => $this->assinatura_atual_id,
            'plano_atual_id' => $this->plano_atual_id,
        ]);

        if (!$this->temAssinaturaAtiva()) {
            \Log::warning('Tenant::podeCriarProcesso() - Assinatura não está ativa', [
                'tenant_id' => $this->id,
            ]);
            return false;
        }

        // Buscar usuário autenticado (priorizar ApplicationContext, fallback para auth())
        $user = null;
        if (app()->bound(\App\Contracts\ApplicationContextContract::class)) {
            $context = app(\App\Contracts\ApplicationContextContract::class);
            if ($context->isInitialized()) {
                $user = $context->getUser();
            }
        }
        
        if (!$user) {
            $user = auth()->user();
        }
        
        if (!$user) {
            \Log::warning('Tenant::podeCriarProcesso() - Usuário não autenticado', [
                'tenant_id' => $this->id,
            ]);
            return false;
        }

        // Buscar assinatura ativa do usuário para obter o plano
        $assinaturaRepository = app(\App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface::class);
        $assinaturaDomain = $assinaturaRepository->buscarAssinaturaAtualPorUsuario($user->id);
        
        if (!$assinaturaDomain) {
            \Log::warning('Tenant::podeCriarProcesso() - Assinatura não encontrada para o usuário', [
                'tenant_id' => $this->id,
                'user_id' => $user->id,
            ]);
            return false;
        }

        // Buscar modelo da assinatura para acessar o plano
        $assinaturaModel = $assinaturaRepository->buscarModeloPorId($assinaturaDomain->id);
        if (!$assinaturaModel || !$assinaturaModel->plano) {
            \Log::warning('Tenant::podeCriarProcesso() - Plano não encontrado', [
                'tenant_id' => $this->id,
                'user_id' => $user->id,
                'assinatura_id' => $assinaturaDomain->id,
            ]);
            return false;
        }

        $plano = $assinaturaModel->plano;

        // Se não tem limite, pode criar (mas ainda precisa verificar restrição diária)
        $temProcessosIlimitados = $plano->temProcessosIlimitados();
        
        // Verificar restrição diária (1 processo por dia)
        if ($plano->temRestricaoDiaria()) {
            try {
                $jaInicializado = tenancy()->initialized;
                if (!$jaInicializado) {
                    tenancy()->initialize($this);
                }
                
                // Verificar se já existe processo criado hoje
                $hoje = now()->startOfDay();
                $amanha = now()->copy()->addDay()->startOfDay();
                
                $processosHoje = \App\Modules\Processo\Models\Processo::whereBetween('created_at', [$hoje, $amanha])->count();
                
                if (!$jaInicializado) {
                    tenancy()->end();
                }
                
                // Se já tem processo criado hoje, não pode criar outro
                if ($processosHoje > 0) {
                    return false;
                }
            } catch (\Exception $e) {
                // Se houver erro, assumir que pode criar (fail open)
                \Log::warning('Erro ao verificar restrição diária de processos', [
                    'tenant_id' => $this->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Se tem processos ilimitados e passou pela restrição diária (ou não tem), pode criar
        if ($temProcessosIlimitados) {
            return true;
        }

        // Verificar limite mensal de processos
        try {
            $jaInicializado = tenancy()->initialized;
            if (!$jaInicializado) {
                tenancy()->initialize($this);
            }
            
            // Contar processos criados no mês atual
            $inicioMes = now()->startOfMonth();
            $fimMes = now()->copy()->endOfMonth();
            
            $processosMes = \App\Modules\Processo\Models\Processo::whereBetween('created_at', [$inicioMes, $fimMes])->count();
            
            if (!$jaInicializado) {
                tenancy()->end();
            }
            
            return $processosMes < $plano->limite_processos;
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

    /**
     * Verifica se o plano tem acesso a um recurso específico
     */
    public function temRecurso(string $recurso): bool
    {
        if (!$this->temAssinaturaAtiva()) {
            return false;
        }

        $plano = $this->planoAtual;
        
        if (!$plano) {
            return false;
        }

        $recursosDisponiveis = $plano->recursos_disponiveis ?? [];
        
        return in_array($recurso, $recursosDisponiveis);
    }

    /**
     * Verifica se o plano tem acesso a calendários
     */
    public function temAcessoCalendario(): bool
    {
        return $this->temRecurso('calendarios');
    }

    /**
     * Verifica se o plano tem acesso a relatórios
     * Relatórios básicos (orçamentos) estão disponíveis para todos os planos com assinatura ativa
     * Relatórios avançados (financeiros) requerem plano Profissional ou superior
     */
    public function temAcessoRelatorios(): bool
    {
        // Se tem assinatura ativa, permite acesso básico a relatórios
        // Relatórios avançados podem ter verificações adicionais nos controllers
        if (!$this->temAssinaturaAtiva()) {
            return false;
        }

        // Se tem o recurso 'relatorios', tem acesso completo
        if ($this->temRecurso('relatorios')) {
            return true;
        }

        // Planos Essenciais e superiores têm acesso básico a relatórios de orçamentos
        // Mesmo sem o recurso 'relatorios' explícito, se tem assinatura ativa, permite
        return true;
    }

    /**
     * Verifica se o plano tem acesso a dashboard
     */
    public function temAcessoDashboard(): bool
    {
        // Se não tem assinatura ativa, não tem acesso
        if (!$this->temAssinaturaAtiva()) {
            return false;
        }

        $plano = $this->planoAtual;
        
        if (!$plano) {
            return false;
        }

        // Planos ilimitados (sem limite de processos ou usuários) têm acesso total ao dashboard
        // Verificar se é plano ilimitado verificando os limites
        $temLimiteProcessos = $plano->limite_processos !== null;
        $temLimiteUsuarios = $plano->limite_usuarios !== null;
        
        // Se não tem limites, é plano ilimitado - permite acesso ao dashboard
        if (!$temLimiteProcessos && !$temLimiteUsuarios) {
            return true;
        }

        // Para outros planos, verificar recursos disponíveis
        // Dashboard está disponível para planos com 'relatorios' ou 'dashboard_analytics'
        return $this->temRecurso('relatorios') || $this->temRecurso('dashboard_analytics');
    }
}
