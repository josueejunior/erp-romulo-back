<?php

namespace App\Modules\Processo\Services;

use App\Services\BaseService;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Services\ProcessoStatusService;
use App\Rules\DbTypeRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use DomainException;

/**
 * Service para gerenciar processos
 * Estende BaseService que aplica filtro automático por empresa_id
 * O filtro é aplicado AUTOMATICAMENTE em todas as queries
 */
class ProcessoService extends BaseService
{
    /**
     * Model class name
     */
    protected static string $model = Processo::class;

    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {
        // BaseService não tem construtor, não precisa chamar parent::__construct()
    }

    /**
     * Criar parâmetros para busca por ID
     */
    public function createFindByIdParamBag(array $values): array
    {
        return [
            'with' => $values['with'] ?? [
                'orgao', 
                'setor', 
                'itens', 
                'itens.formacoesPreco', // Para calcular valores
                'documentos',
                'documentos.documentoHabilitacao', // Para calcular alertas de documentos vencidos
                'empenhos', // Para calcular alertas de empenhos
            ],
        ];
    }

    /**
     * Buscar registro por ID
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE pelo BaseService
     */
    public function findById(int|string $id, array $params = []): ?Model
    {
        $builder = $this->createQueryBuilder();
        
        // Carregar relacionamentos
        if (isset($params['with']) && is_array($params['with'])) {
            $builder->with($params['with']);
        }
        
        return $builder->find($id);
    }

    /**
     * Criar parâmetros para listagem
     */
    public function createListParamBag(array $values): array
    {
        return [
            'status' => $values['status'] ?? null,
            'modalidade' => $values['modalidade'] ?? null,
            'orgao_id' => $values['orgao_id'] ?? null,
            'search' => $values['search'] ?? null,
            'somente_com_orcamento' => $values['somente_com_orcamento'] ?? null,
            'page' => $values['page'] ?? 1,
            'per_page' => $values['per_page'] ?? 15,
            'with' => $values['with'] ?? [
                'orgao', 
                'setor',
                'itens', // Para calcular valores
                'itens.formacoesPreco', // Para calcular valores mínimos
                'documentos', // Para calcular alertas
                'documentos.documentoHabilitacao', // Para calcular alertas de documentos vencidos
                'empenhos', // Para calcular alertas de empenhos
            ],
        ];
    }

    /**
     * Listar registros com paginação
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE pelo BaseService
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $builder = $this->createQueryBuilder();

        // Filtros opcionais usando when()
        $builder->when(isset($params['status']), function ($query) use ($params) {
            return $query->where('status', $params['status']);
        })
        ->when(isset($params['modalidade']), function ($query) use ($params) {
            return $query->where('modalidade', $params['modalidade']);
        })
        ->when(isset($params['orgao_id']), function ($query) use ($params) {
            return $query->where('orgao_id', $params['orgao_id']);
        })
        ->when(isset($params['search']) && $params['search'], function ($query) use ($params) {
            $search = $params['search'];
            return $query->where(function($q) use ($search) {
                $q->where('numero_modalidade', 'like', "%{$search}%")
                  ->orWhere('numero_processo_administrativo', 'like', "%{$search}%")
                  ->orWhere('objeto_resumido', 'like', "%{$search}%");
            });
        })
        ->when(
            isset($params['somente_com_orcamento']) && 
            ($params['somente_com_orcamento'] === true || $params['somente_com_orcamento'] === 'true' || $params['somente_com_orcamento'] === '1'),
            function ($query) use ($params) {
                \Log::debug('ProcessoService::list() - Aplicando filtro somente_com_orcamento', [
                    'param_value' => $params['somente_com_orcamento'],
                ]);
                // Usar whereHas com orcamento_itens que é a tabela intermediária atual
                return $query->whereHas('itens.orcamentoItens', function($q) {
                    // Não precisa de condição adicional, apenas verificar existência
                });
            }
        );

        // Carregar relacionamentos padrão necessários para ProcessoListResource
        $defaultWith = ['orgao', 'setor'];
        
        // Se solicitou filtro de orçamento, carregar também os itens com orçamentos
        $empresaId = $this->getEmpresaIdFromContext();
        if (isset($params['somente_com_orcamento']) && ($params['somente_com_orcamento'] === true || $params['somente_com_orcamento'] === 'true' || $params['somente_com_orcamento'] === '1')) {
            // IMPORTANTE: Carregar 'itens' primeiro para garantir que os itens sejam carregados
            // Depois carregar 'itens.orcamentos' com fornecedor usando closure para garantir filtro de empresa
            $defaultWith[] = 'itens'; // Garantir que itens sejam carregados
            // Usar array associativo para eager loading com constraints
            $defaultWith['itens.orcamentos'] = function ($query) use ($empresaId) {
                // Remover Global Scope e aplicar filtro de empresa explicitamente
                $query->withoutGlobalScope('empresa')
                      ->where('orcamentos.empresa_id', $empresaId)
                      ->whereNotNull('orcamentos.empresa_id')
                      ->orderBy('orcamentos.criado_em', 'desc');
            };
            $defaultWith[] = 'itens.orcamentos.fornecedor';
            \Log::debug('ProcessoService::list() - Carregando relacionamento de orçamentos', [
                'tenant_id' => tenancy()->tenant?->id,
                'empresa_id' => $empresaId,
                'with' => $defaultWith,
            ]);
        } else {
            // Sempre carregar itens para ProcessoListResource funcionar
            $defaultWith[] = 'itens';
        }
        
        $with = array_merge($defaultWith, $params['with'] ?? []);
        // Filtrar valores duplicados mantendo chaves associativas
        $withFinal = [];
        foreach ($with as $key => $value) {
            if (is_numeric($key)) {
                // Valor simples (string)
                if (!in_array($value, $withFinal)) {
                    $withFinal[] = $value;
                }
            } else {
                // Chave associativa (closure para constraint)
                $withFinal[$key] = $value;
            }
        }
        $builder->with($withFinal);
        
        \Log::debug('ProcessoService::list() - Relacionamentos carregados', [
            'tenant_id' => tenancy()->tenant?->id,
            'empresa_id' => $this->getEmpresaIdFromContext(),
            'with_final' => array_unique($with),
            'somente_com_orcamento' => $params['somente_com_orcamento'] ?? null,
        ]);

        // Ordenação - usar constante do modelo para timestamps customizados
        $modelClass = static::$model;
        $createdAtColumn = defined("$modelClass::CREATED_AT") 
            ? $modelClass::CREATED_AT 
            : 'criado_em';
        $builder->orderBy($createdAtColumn, 'desc');

        // Paginação
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Validar dados para criação
     * Usa DbTypeRule para manter consistência com migrations
     */
    public function validateStoreData(array $data): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'orgao_id' => [DbTypeRule::required(), 'exists:orgaos,id'],
            'setor_id' => [DbTypeRule::nullable(), 'exists:setors,id'],
            'modalidade' => [DbTypeRule::required(), ...DbTypeRule::enum(['dispensa', 'pregao'])],
            'numero_modalidade' => [DbTypeRule::required(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'numero_processo_administrativo' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'link_edital' => [DbTypeRule::nullable(), ...DbTypeRule::url(500)],
            'portal' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'numero_edital' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'srp' => [...DbTypeRule::boolean()],
            'objeto_resumido' => [DbTypeRule::required(), ...DbTypeRule::text()],
            'data_hora_sessao_publica' => [DbTypeRule::required(), ...DbTypeRule::datetime()],
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
        ]);
    }

    /**
     * Criar novo registro
     * O empresa_id é adicionado AUTOMATICAMENTE pelo BaseService::store()
     */
    public function store(array $data): Model
    {
        // Verificar se o tenant pode criar processo (limites de plano)
        $tenant = tenancy()->tenant;
        
        if (!$tenant) {
            \Log::warning('ProcessoService::store() - Tenant não encontrado', [
                'empresa_id' => $this->getEmpresaId(),
            ]);
            throw new DomainException('Tenant não encontrado. Verifique sua assinatura.');
        }

        // Verificar assinatura ativa primeiro
        if (!$tenant->temAssinaturaAtiva()) {
            \Log::warning('ProcessoService::store() - Assinatura não está ativa', [
                'tenant_id' => $tenant->id,
                'assinatura_atual_id' => $tenant->assinatura_atual_id,
            ]);
            throw new DomainException('Você não tem uma assinatura ativa. Ative sua assinatura para criar processos.');
        }

        $plano = $tenant->planoAtual;
        if (!$plano) {
            \Log::warning('ProcessoService::store() - Plano não encontrado', [
                'tenant_id' => $tenant->id,
                'plano_atual_id' => $tenant->plano_atual_id,
            ]);
            throw new DomainException('Plano não encontrado. Verifique sua assinatura.');
        }

        // Verificar se pode criar processo
        if (!$tenant->podeCriarProcesso()) {
            \Log::info('ProcessoService::store() - Bloqueado por limite do plano', [
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
                'plano_nome' => $plano->nome,
                'limite_processos' => $plano->limite_processos,
                'restricao_diaria' => $plano->temRestricaoDiaria(),
            ]);
            
            // Verificar se é restrição diária ou limite mensal
            if ($plano->temRestricaoDiaria()) {
                throw new DomainException('Você já criou um processo hoje. Planos Essencial e Profissional permitem apenas 1 processo por dia.');
            }
            
            // Verificar limite mensal
            if (!$plano->temProcessosIlimitados()) {
                $limite = $plano->limite_processos;
                throw new DomainException("Você atingiu o limite de {$limite} processos do seu plano. Faça upgrade para continuar criando processos.");
            }
            
            throw new DomainException('Você não pode criar processos no momento. Verifique sua assinatura.');
        }

        // Status padrão
        if (!isset($data['status'])) {
            $data['status'] = 'participacao';
        }

        // Processar tipo_selecao_fornecedor e tipo_disputa se vierem como objetos
        if (isset($data['tipo_selecao_fornecedor']) && is_array($data['tipo_selecao_fornecedor'])) {
            // Se for um array/objeto, extrair o valor (assumindo que tem 'value' ou é o próprio valor)
            $data['tipo_selecao_fornecedor'] = $data['tipo_selecao_fornecedor']['value'] ?? $data['tipo_selecao_fornecedor']['id'] ?? (is_string($data['tipo_selecao_fornecedor']) ? $data['tipo_selecao_fornecedor'] : null);
        }

        if (isset($data['tipo_disputa']) && is_array($data['tipo_disputa'])) {
            // Se for um array/objeto, extrair o valor (assumindo que tem 'value' ou é o próprio valor)
            $data['tipo_disputa'] = $data['tipo_disputa']['value'] ?? $data['tipo_disputa']['id'] ?? (is_string($data['tipo_disputa']) ? $data['tipo_disputa'] : null);
        }

        // Chamar método do BaseService que já adiciona empresa_id automaticamente
        return parent::store($data);
    }

    /**
     * Validar dados para atualização
     * Usa DbTypeRule para manter consistência com migrations
     */
    public function validateUpdateData(array $data, int|string $id): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, [
            'orgao_id' => ['sometimes', 'exists:orgaos,id'],
            'setor_id' => [DbTypeRule::nullable(), 'exists:setors,id'],
            'modalidade' => ['sometimes', ...DbTypeRule::enum(['dispensa', 'pregao'])],
            'numero_modalidade' => ['sometimes', ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
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
            'observacoes' => [DbTypeRule::nullable(), ...DbTypeRule::observacao()],
        ]);
    }

    /**
     * Atualizar registro
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE pelo BaseService
     * O empresa_id não pode ser alterado (protegido pelo BaseService)
     */
    public function update(int|string $id, array $data): Model
    {
        // Processar tipo_selecao_fornecedor e tipo_disputa se vierem como objetos
        if (isset($data['tipo_selecao_fornecedor']) && is_array($data['tipo_selecao_fornecedor'])) {
            // Se for um array/objeto, extrair o valor (assumindo que tem 'value' ou é o próprio valor)
            $data['tipo_selecao_fornecedor'] = $data['tipo_selecao_fornecedor']['value'] ?? $data['tipo_selecao_fornecedor']['id'] ?? (is_string($data['tipo_selecao_fornecedor']) ? $data['tipo_selecao_fornecedor'] : null);
        }

        if (isset($data['tipo_disputa']) && is_array($data['tipo_disputa'])) {
            // Se for um array/objeto, extrair o valor (assumindo que tem 'value' ou é o próprio valor)
            $data['tipo_disputa'] = $data['tipo_disputa']['value'] ?? $data['tipo_disputa']['id'] ?? (is_string($data['tipo_disputa']) ? $data['tipo_disputa'] : null);
        }

        // BaseService já aplica filtro e protege empresa_id
        return parent::update($id, $data);
    }

    /**
     * Excluir registro por ID
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE pelo BaseService
     */
    public function deleteById(int|string $id): bool
    {
        // BaseService já aplica filtro
        return parent::deleteById($id);
    }

    /**
     * Excluir múltiplos registros
     * O filtro por empresa_id é aplicado AUTOMATICAMENTE pelo BaseService
     */
    public function deleteByIds(array $ids): int
    {
        // BaseService já aplica filtro
        return parent::deleteByIds($ids);
    }

    /**
     * Obter resumo dos processos
     * Retorna stats para o dashboard
     */
    public function obterResumo(array $filtros = []): array
    {
        $empresaId = $this->getEmpresaId();
        
        if (!$empresaId) {
            return [
                'total' => 0,
                'participacao' => 0,
                'julgamento' => 0,
                'execucao' => 0,
                'pagamento' => 0,
                'encerramento' => 0,
                'com_alerta' => 0,
                'valor_total_execucao' => 0,
                'lucro_estimado' => 0,
                'por_status' => [],
                'por_modalidade' => [],
            ];
        }

        // Usar ProcessoRepository para obter resumo básico
        $resumo = $this->processoRepository->obterResumo([
            'empresa_id' => $empresaId,
        ]);
        
        // Para cálculos complexos, usar modelos Eloquent diretamente (necessário para relacionamentos)
        // Mas garantir isolamento por empresa_id
        $baseQuery = Processo::withoutGlobalScope('empresa')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id');

        // Aplicar filtros (mas não para os cálculos de stats) usando when()
        $queryComFiltros = clone $baseQuery;
        $queryComFiltros->when(isset($filtros['status']), function ($query) use ($filtros) {
            return $query->where('status', $filtros['status']);
        })
        ->when(isset($filtros['modalidade']), function ($query) use ($filtros) {
            return $query->where('modalidade', $filtros['modalidade']);
        })
        ->when(isset($filtros['orgao_id']), function ($query) use ($filtros) {
            return $query->where('orgao_id', $filtros['orgao_id']);
        })
        ->when(isset($filtros['search']) && $filtros['search'], function ($query) use ($filtros) {
            $search = $filtros['search'];
            return $query->where(function($q) use ($search) {
                $q->where('numero_modalidade', 'ilike', "%{$search}%")
                  ->orWhere('numero_processo_administrativo', 'ilike', "%{$search}%")
                  ->orWhere('objeto_resumido', 'ilike', "%{$search}%");
            });
        });

        // Contar por status usando repository quando possível
        $filtrosParticipacao = array_merge($filtros, ['empresa_id' => $empresaId, 'status' => 'participacao', 'per_page' => 1]);
        $participacao = $this->processoRepository->buscarComFiltros($filtrosParticipacao)->total();
        
        $filtrosJulgamento = array_merge($filtros, ['empresa_id' => $empresaId, 'status' => 'julgamento_habilitacao', 'per_page' => 1]);
        $julgamento = $this->processoRepository->buscarComFiltros($filtrosJulgamento)->total();
        
        $filtrosExecucao = array_merge($filtros, ['empresa_id' => $empresaId, 'status' => 'execucao', 'per_page' => 1]);
        $execucao = $this->processoRepository->buscarComFiltros($filtrosExecucao)->total();
        
        $filtrosPagamento = array_merge($filtros, ['empresa_id' => $empresaId, 'status' => 'pagamento', 'per_page' => 1]);
        $pagamento = $this->processoRepository->buscarComFiltros($filtrosPagamento)->total();
        
        $filtrosEncerramento = array_merge($filtros, ['empresa_id' => $empresaId, 'status' => 'encerramento', 'per_page' => 1]);
        $encerramento = $this->processoRepository->buscarComFiltros($filtrosEncerramento)->total();

        // Contar processos com alertas (precisa carregar processos e verificar alertas)
        // Por enquanto, retornar 0 - pode ser implementado depois se necessário
        $comAlerta = 0;

        // Calcular valor total em execução
        $processosExecucao = (clone $queryComFiltros)
            ->where('status', 'execucao')
            ->with(['itens' => function($q) {
                $q->whereIn('status_item', ['aceito', 'aceito_habilitado']);
            }])
            ->get();
        
        $valorTotalExecucao = $processosExecucao->sum(function($processo) {
            return $processo->itens->sum(function($item) {
                return $item->valor_negociado ?? $item->valor_final_sessao ?? $item->valor_estimado ?? 0;
            });
        });

        // Lucro estimado (por enquanto retornar 0 - pode ser calculado depois)
        $lucroEstimado = 0;

        return [
            'total' => $queryComFiltros->count(),
            'participacao' => $participacao,
            'julgamento' => $julgamento,
            'execucao' => $execucao,
            'pagamento' => $pagamento,
            'encerramento' => $encerramento,
            'com_alerta' => $comAlerta,
            'valor_total_execucao' => round($valorTotalExecucao, 2),
            'lucro_estimado' => round($lucroEstimado, 2),
            'por_status' => $queryComFiltros->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
            'por_modalidade' => $queryComFiltros->selectRaw('modalidade, count(*) as total')
                ->groupBy('modalidade')
                ->pluck('total', 'modalidade')
                ->toArray(),
        ];
    }

    /**
     * Mover processo para status de julgamento
     */
    public function moverParaJulgamento(Processo $processo, ProcessoStatusService $statusService): Processo
    {
        $empresaId = $this->getEmpresaId();
        
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado');
        }

        if ($processo->status !== 'participacao') {
            throw new \Exception('Apenas processos em participação podem ser movidos para julgamento');
        }

        if (!$statusService->deveSugerirJulgamento($processo)) {
            throw new \Exception('A data/hora da sessão pública ainda não foi atingida');
        }

        $result = $statusService->alterarStatus($processo, 'julgamento_habilitacao');
        
        if (!$result['pode']) {
            throw new \Exception($result['motivo']);
        }

        return $processo->fresh();
    }

    /**
     * Marcar processo como vencido
     */
    public function marcarVencido(Processo $processo, ProcessoStatusService $statusService): Processo
    {
        $empresaId = $this->getEmpresaId();
        
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado');
        }

        $result = $statusService->alterarStatus($processo, 'execucao');
        
        if (!$result['pode']) {
            throw new \Exception($result['motivo']);
        }

        return $processo->fresh();
    }

    /**
     * Marcar processo como perdido
     */
    public function marcarPerdido(Processo $processo, ProcessoStatusService $statusService): Processo
    {
        $empresaId = $this->getEmpresaId();
        
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado');
        }

        $result = $statusService->alterarStatus($processo, 'perdido');
        
        if (!$result['pode']) {
            throw new \Exception($result['motivo']);
        }

        return $processo->fresh();
    }

    /**
     * Sugerir status baseado nas regras de negócio
     */
    public function sugerirStatus(Processo $processo, ProcessoStatusService $statusService): array
    {
        $empresaId = $this->getEmpresaId();
        
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado');
        }

        $sugestoes = [];

        if ($statusService->deveSugerirJulgamento($processo)) {
            $sugestoes[] = [
                'status' => 'julgamento_habilitacao',
                'motivo' => 'Data/hora da sessão pública foi atingida',
                'prioridade' => 'alta'
            ];
        }

        if ($statusService->deveSugerirPerdido($processo)) {
            $sugestoes[] = [
                'status' => 'perdido',
                'motivo' => 'Todos os itens foram desclassificados ou inabilitados',
                'prioridade' => 'media'
            ];
        }

        // Retornar formato compatível com frontend
        $deveSugerirJulgamento = $statusService->deveSugerirJulgamento($processo);
        $deveSugerirPerdido = $statusService->deveSugerirPerdido($processo);
        
        return [
            'processo_id' => $processo->id,
            'status_atual' => $processo->status,
            'deve_sugerir_julgamento' => $deveSugerirJulgamento,
            'deve_sugerir_perdido' => $deveSugerirPerdido,
            'sugestoes' => $sugestoes
        ];
    }

    /**
     * Confirma pagamento do processo e atualiza saldos
     */
    public function confirmarPagamento(Processo $processo, ?string $dataRecebimento = null): Processo
    {
        $empresaId = $this->getEmpresaId();
        
        if ($processo->empresa_id !== $empresaId) {
            throw new \Exception('Processo não encontrado ou não pertence à empresa ativa.');
        }

        if ($processo->status !== 'execucao') {
            throw new \Exception('Apenas processos em execução podem ter pagamento confirmado.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($processo, $dataRecebimento) {
            // Atualizar data de recebimento
            $processo->data_recebimento_pagamento = $dataRecebimento ? \Carbon\Carbon::parse($dataRecebimento) : now();
            
            // Atualizar valores financeiros de todos os itens
            foreach ($processo->itens as $item) {
                $item->atualizarValoresFinanceiros();
            }
            
            // Verificar se todos os itens foram pagos
            $todosItensPagos = $processo->itens()
                ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
                ->get()
                ->every(function ($item) {
                    return $item->saldo_aberto <= 0;
                });

            // Se todos os itens foram pagos, mudar status para encerramento
            if ($todosItensPagos && $processo->itens()->whereIn('status_item', ['aceito', 'aceito_habilitado'])->count() > 0) {
                $processo->status = 'encerramento';
            }

            $processo->save();

            return $processo->load(['orgao', 'setor', 'itens']);
        });
    }
}

