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
     * 🔥 CORREÇÃO: Sempre usar conexão central para evitar que tenants sejam criados no banco errado
     * O modelo Tenant DEVE estar sempre no banco central, nunca no banco do tenant
     * Isso garante que mesmo quando o sistema está no contexto de um tenant,
     * os registros de Tenant são sempre salvos no banco central
     */
    protected $connection;
    
    /**
     * Usar IDs numéricos auto-incrementados ao invés de strings/slugs
     */
    public $incrementing = true;
    protected $keyType = 'int';
    
    /**
     * Sobrescrever boot para garantir que não há geração automática de UUID
     * e que sempre use a conexão central
     */
    protected static function boot()
    {
        parent::boot();
        
        // Garantir que sempre use a conexão central e que o ID seja gerado corretamente
        static::creating(function ($tenant) {
            // Forçar conexão central antes de criar
            $tenant->setConnection(
                config('tenancy.database.central_connection', config('database.default'))
            );
            
            // Se o ID já foi definido, manter; caso contrário, deixar o banco gerar
            if (isset($tenant->attributes['id']) && !is_numeric($tenant->attributes['id'])) {
                unset($tenant->attributes['id']);
            }
        });
        
        // Garantir que queries também usem conexão central
        static::retrieved(function ($tenant) {
            $tenant->setConnection(
                config('tenancy.database.central_connection', config('database.default'))
            );
        });
    }
    
    /**
     * Sobrescrever getConnectionName para sempre retornar conexão central
     */
    public function getConnectionName(): ?string
    {
        // Sempre usar conexão central, mesmo se outra conexão foi definida
        return config('tenancy.database.central_connection', config('database.default'));
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
        // Buscar usuário autenticado
        $user = auth()->user();
        
        // 🔥 CORREÇÃO: Buscar assinatura da EMPRESA/TENANT (fonte da verdade)
        // Não buscar por user_id primeiro, pois o usuário pode ter múltiplas empresas com assinaturas diferentes
        $assinaturaRepository = app(\App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface::class);
        $assinaturaDomain = $assinaturaRepository->buscarAssinaturaAtualPorEmpresa($this->id);

        // Se não encontrou pela empresa, tentar pelo tenant_id (legado)
        if (!$assinaturaDomain) {
            $assinaturaDomain = $assinaturaRepository->buscarAssinaturaAtual($this->id);
        }

        // Se ainda não encontrou, como último recurso, buscar pela assinatura do usuário 
        // mas apenas se ele estiver vinculado a este tenant/empresa
        if (!$assinaturaDomain && $user) {
            $assinaturaDomain = $assinaturaRepository->buscarAssinaturaAtualPorUsuario($user->id);
            
            // Validar se essa assinatura pertence ao tenant atual
            if ($assinaturaDomain && (int)$assinaturaDomain->tenantId !== (int)$this->id) {
                $assinaturaDomain = null;
            }
        }

        if (!$assinaturaDomain) {
            \Log::debug('Tenant::temAssinaturaAtiva() - Assinatura não encontrada', [
                'tenant_id' => $this->id,
            ]);
            return false;
        }

        // Buscar modelo da assinatura para verificar status
        $assinaturaModel = $assinaturaRepository->buscarModeloPorId($assinaturaDomain->id);
        if (!$assinaturaModel) {
            return false;
        }

        $isAtiva = $assinaturaModel->isAtiva();

        // Se a assinatura está ativa e o tenant não tem assinatura_atual_id ou tem uma diferente, atualizar cache
        if ($isAtiva && ($this->assinatura_atual_id !== $assinaturaModel->id || (int)$this->plano_atual_id !== (int)$assinaturaModel->plano_id)) {
            $this->update([
                'assinatura_atual_id' => $assinaturaModel->id,
                'plano_atual_id' => $assinaturaModel->plano_id,
            ]);
            $this->refresh();
        }

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
    /**
     * Verifica se o plano tem acesso a um recurso específico
     */
    /**
     * Verifica se o plano tem acesso a um recurso específico
     */
    public function temRecurso(string $recurso): bool
    {
        // 1. Garantir que a assinatura está ativa e atualizada
        // Isso atualiza o plano_atual_id no banco se necessário
        if (!$this->temAssinaturaAtiva()) {
            return false;
        }

        // 2. Forçar recarregamento do plano para garantir dados frescos
        // Se temAssinaturaAtiva atualizou o ID, o relacionamento cacheado estaria obsoleto
        $this->load('planoAtual');
        $plano = $this->planoAtual;
        
        if (!$plano) {
            // Última tentativa: buscar direto pelo ID se existir
            if ($this->plano_atual_id) {
                $plano = \App\Modules\Assinatura\Models\Plano::find($this->plano_atual_id);
            }
            
            if (!$plano) {
                \Log::warning('Tenant::temRecurso - Plano não encontrado mesmo com assinatura ativa', ['tenant_id' => $this->id]);
                return false;
            }
        }

        $recursosDisponiveis = $plano->recursos_disponiveis ?? [];
        
        if (in_array($recurso, $recursosDisponiveis)) {
            return true;
        }

        // 🔥 FALLBACK ROBUSTO: Planos Premium (Ilimitado, Master, Profissional) têm acesso a tudo
        $nomePlano = \Illuminate\Support\Str::lower($plano->nome ?? '');
        $isPremium = \Illuminate\Support\Str::contains($nomePlano, ['master', 'profissional', 'ilimitado', 'premium']);

        if ($isPremium) {
            // Lista de recursos garantidos para premium
            if (in_array($recurso, ['relatorios', 'calendarios', 'dashboard_analytics', 'gestao_financeira'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se o plano tem acesso a calendários
     * 
     * 🔥 CORREÇÃO: Planos ilimitados têm acesso automático a todos os recursos
     */
    public function temAcessoCalendario(): bool
    {
        if (!$this->temAssinaturaAtiva()) {
            \Log::debug('Tenant::temAcessoCalendario() - Sem assinatura ativa', [
                'tenant_id' => $this->id,
            ]);
            return false;
        }

        // Tentar carregar plano do relacionamento
        $plano = $this->planoAtual;
        
        // Se não encontrou pelo relacionamento, tentar buscar pela assinatura
        if (!$plano && $this->assinatura_atual_id) {
            $assinatura = $this->assinaturaAtual;
            if ($assinatura && $assinatura->plano_id) {
                $plano = \App\Modules\Assinatura\Models\Plano::find($assinatura->plano_id);
            }
        }
        
        if (!$plano) {
            \Log::warning('Tenant::temAcessoCalendario() - Plano não encontrado', [
                'tenant_id' => $this->id,
                'plano_atual_id' => $this->plano_atual_id,
                'assinatura_atual_id' => $this->assinatura_atual_id,
            ]);
            return false;
        }

        // Planos ilimitados (sem limite de processos ou usuários) têm acesso total ao calendário
        // Verificar se é plano ilimitado verificando os limites
        $temLimiteProcessos = $plano->limite_processos !== null;
        $temLimiteUsuarios = $plano->limite_usuarios !== null;
        
        // Se não tem limites, é plano ilimitado - permite acesso ao calendário
        if (!$temLimiteProcessos && !$temLimiteUsuarios) {
            \Log::debug('Tenant::temAcessoCalendario() - Plano ilimitado, acesso permitido', [
                'tenant_id' => $this->id,
                'plano_id' => $plano->id,
                'plano_nome' => $plano->nome,
            ]);
            return true;
        }

        // Para outros planos, verificar se o recurso 'calendarios' está disponível
        $recursosDisponiveis = $plano->recursos_disponiveis ?? [];
        $temRecurso = in_array('calendarios', $recursosDisponiveis);
        
        \Log::debug('Tenant::temAcessoCalendario() - Verificação de recurso', [
            'tenant_id' => $this->id,
            'plano_id' => $plano->id,
            'plano_nome' => $plano->nome,
            'recursos_disponiveis' => $recursosDisponiveis,
            'tem_recurso_calendarios' => $temRecurso,
        ]);
        
        return $temRecurso;
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
