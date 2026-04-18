<?php

namespace App\Http\Controllers\Api;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="ERP Licitações API",
 *     description="API RESTful para o sistema ERP de Licitações - Gestão completa de processos licitatórios",
 *     @OA\Contact(
 *         email="suporte@addireta.com",
 *         name="Suporte Técnico"
 *     ),
 *     @OA\License(
 *         name="Proprietário",
 *         url="https://addireta.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api/v1",
 *     description="API v1"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Autenticação via JWT Token. Use o endpoint /auth/login para obter o token."
 * )
 * 
 * @OA\Tag(name="Autenticação", description="Endpoints de autenticação e gerenciamento de sessão")
 * @OA\Tag(name="Processos", description="Gerenciamento de processos licitatórios")
 * @OA\Tag(name="Fornecedores", description="Cadastro e gerenciamento de fornecedores")
 * @OA\Tag(name="Contratos", description="Gerenciamento de contratos")
 * @OA\Tag(name="Assinaturas", description="Gerenciamento de planos e assinaturas")
 * @OA\Tag(name="Dashboard", description="Métricas e indicadores")
 * @OA\Tag(name="Órgãos", description="Gerenciamento de órgãos públicos")
 * @OA\Tag(name="Empenhos", description="Gerenciamento de empenhos")
 * @OA\Tag(name="Notas Fiscais", description="Gerenciamento de notas fiscais")
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="Erro de validação"),
 *     @OA\Property(property="errors", type="object", additionalProperties={"type":"array","items":{"type":"string"}})
 * )
 * 
 * @OA\Schema(
 *     schema="Pagination",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=100),
 *     @OA\Property(property="last_page", type="integer", example=7)
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="João Silva"),
 *     @OA\Property(property="email", type="string", format="email", example="joao@empresa.com"),
 *     @OA\Property(property="foto_perfil_url", type="string", nullable=true),
 *     @OA\Property(property="role", type="string", example="tenant_user")
 * )
 * 
 * @OA\Schema(
 *     schema="Processo",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="modalidade", type="string", example="pregão"),
 *     @OA\Property(property="numero_modalidade", type="string", example="001/2026"),
 *     @OA\Property(property="objeto_resumido", type="string", example="Aquisição de materiais"),
 *     @OA\Property(property="status", type="string", enum={"rascunho", "participacao", "julgamento_habilitacao", "execucao", "pagamento", "encerramento"}),
 *     @OA\Property(property="portal", type="string", example="ComprasNet"),
 *     @OA\Property(property="data_hora_sessao_publica", type="string", format="date-time"),
 *     @OA\Property(property="empresa_id", type="integer"),
 *     @OA\Property(property="orgao_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Fornecedor",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="razao_social", type="string", example="Fornecedor LTDA"),
 *     @OA\Property(property="nome_fantasia", type="string", example="Fornecedor"),
 *     @OA\Property(property="cnpj", type="string", example="12345678000190"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="telefone", type="string"),
 *     @OA\Property(property="status", type="string", enum={"ativo", "inativo", "bloqueado"})
 * )
 * 
 * @OA\Schema(
 *     schema="Dashboard",
 *     type="object",
 *     @OA\Property(property="total_processos", type="integer", example=150),
 *     @OA\Property(property="processos_participacao", type="integer", example=25),
 *     @OA\Property(property="processos_execucao", type="integer", example=45),
 *     @OA\Property(property="valor_total", type="number", format="float", example=1500000.00),
 *     @OA\Property(property="ultimos_processos", type="array", @OA\Items(ref="#/components/schemas/Processo"))
 * )
 * 
 * @OA\Schema(
 *     schema="Assinatura",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="plano_id", type="integer"),
 *     @OA\Property(property="status", type="string", enum={"ativa", "expirada", "cancelada", "pendente"}),
 *     @OA\Property(property="data_inicio", type="string", format="date"),
 *     @OA\Property(property="data_fim", type="string", format="date"),
 *     @OA\Property(property="plano", ref="#/components/schemas/Plano")
 * )
 * 
 * @OA\Schema(
 *     schema="Plano",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="nome", type="string", example="Plano Premium"),
 *     @OA\Property(property="descricao", type="string"),
 *     @OA\Property(property="valor", type="number", format="float", example=199.90),
 *     @OA\Property(property="periodo", type="string", enum={"mensal", "anual"})
 * )
 */
class ApiDocumentation
{
    /**
     * @OA\Get(
     *     path="/auth/user",
     *     summary="Obter usuário autenticado",
     *     description="Retorna os dados do usuário atualmente autenticado",
     *     operationId="getUser",
     *     tags={"Autenticação"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuário autenticado",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function docGetUser() {}

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Fazer logout",
     *     description="Invalida o token JWT atual",
     *     operationId="logout",
     *     tags={"Autenticação"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout realizado com sucesso"),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function docLogout() {}

    /**
     * @OA\Get(
     *     path="/processos",
     *     summary="Listar processos",
     *     description="Retorna lista paginada de processos licitatórios",
     *     operationId="listProcessos",
     *     tags={"Processos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Número da página", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Itens por página", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status", in="query", description="Filtrar por status", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", description="Busca por texto", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de processos",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Processo")),
     *             @OA\Property(property="meta", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function docListProcessos() {}

    /**
     * @OA\Post(
     *     path="/processos",
     *     summary="Criar processo",
     *     description="Cria um novo processo licitatório",
     *     operationId="createProcesso",
     *     tags={"Processos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"modalidade", "objeto_resumido"},
     *             @OA\Property(property="modalidade", type="string", example="pregão"),
     *             @OA\Property(property="numero_modalidade", type="string", example="001/2026"),
     *             @OA\Property(property="objeto_resumido", type="string", example="Aquisição de materiais"),
     *             @OA\Property(property="orgao_id", type="integer"),
     *             @OA\Property(property="setor_id", type="integer"),
     *             @OA\Property(property="portal", type="string"),
     *             @OA\Property(property="data_hora_sessao_publica", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Processo criado", @OA\JsonContent(ref="#/components/schemas/Processo")),
     *     @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function docCreateProcesso() {}

    /**
     * @OA\Get(
     *     path="/processos/{id}",
     *     summary="Obter processo",
     *     description="Retorna detalhes de um processo específico",
     *     operationId="getProcesso",
     *     tags={"Processos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalhes do processo", @OA\JsonContent(ref="#/components/schemas/Processo")),
     *     @OA\Response(response=404, description="Processo não encontrado")
     * )
     */
    public function docGetProcesso() {}

    /**
     * @OA\Put(
     *     path="/processos/{id}",
     *     summary="Atualizar processo",
     *     description="Atualiza um processo existente",
     *     operationId="updateProcesso",
     *     tags={"Processos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="modalidade", type="string"),
     *             @OA\Property(property="objeto_resumido", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Processo atualizado"),
     *     @OA\Response(response=404, description="Processo não encontrado")
     * )
     */
    public function docUpdateProcesso() {}

    /**
     * @OA\Delete(
     *     path="/processos/{id}",
     *     summary="Deletar processo",
     *     description="Remove um processo",
     *     operationId="deleteProcesso",
     *     tags={"Processos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Processo removido"),
     *     @OA\Response(response=404, description="Processo não encontrado")
     * )
     */
    public function docDeleteProcesso() {}

    /**
     * @OA\Get(
     *     path="/fornecedores",
     *     summary="Listar fornecedores",
     *     description="Retorna lista paginada de fornecedores",
     *     operationId="listFornecedores",
     *     tags={"Fornecedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de fornecedores",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Fornecedor"))
     *         )
     *     )
     * )
     */
    public function docListFornecedores() {}

    /**
     * @OA\Post(
     *     path="/fornecedores",
     *     summary="Criar fornecedor",
     *     description="Cadastra um novo fornecedor",
     *     operationId="createFornecedor",
     *     tags={"Fornecedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"razao_social"},
     *             @OA\Property(property="razao_social", type="string"),
     *             @OA\Property(property="nome_fantasia", type="string"),
     *             @OA\Property(property="cnpj", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="telefone", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Fornecedor criado")
     * )
     */
    public function docCreateFornecedor() {}

    /**
     * @OA\Get(
     *     path="/fornecedores/{id}",
     *     summary="Obter fornecedor",
     *     description="Retorna detalhes de um fornecedor",
     *     operationId="getFornecedor",
     *     tags={"Fornecedores"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalhes do fornecedor", @OA\JsonContent(ref="#/components/schemas/Fornecedor")),
     *     @OA\Response(response=404, description="Fornecedor não encontrado")
     * )
     */
    public function docGetFornecedor() {}

    /**
     * @OA\Get(
     *     path="/contratos",
     *     summary="Listar contratos",
     *     description="Retorna lista de contratos",
     *     operationId="listContratos",
     *     tags={"Contratos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de contratos")
     * )
     */
    public function docListContratos() {}

    /**
     * @OA\Get(
     *     path="/dashboard",
     *     summary="Dados do dashboard",
     *     description="Retorna métricas e indicadores do dashboard",
     *     operationId="getDashboard",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dados do dashboard",
     *         @OA\JsonContent(ref="#/components/schemas/Dashboard")
     *     )
     * )
     */
    public function docGetDashboard() {}

    /**
     * @OA\Get(
     *     path="/assinaturas/atual",
     *     summary="Assinatura atual",
     *     description="Retorna a assinatura ativa do usuário",
     *     operationId="getAssinaturaAtual",
     *     tags={"Assinaturas"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Assinatura atual", @OA\JsonContent(ref="#/components/schemas/Assinatura")),
     *     @OA\Response(response=404, description="Nenhuma assinatura ativa")
     * )
     */
    public function docGetAssinaturaAtual() {}

    /**
     * @OA\Get(
     *     path="/assinaturas/status",
     *     summary="Status da assinatura",
     *     description="Retorna status detalhado da assinatura",
     *     operationId="getAssinaturaStatus",
     *     tags={"Assinaturas"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Status da assinatura")
     * )
     */
    public function docGetAssinaturaStatus() {}

    /**
     * @OA\Get(
     *     path="/planos",
     *     summary="Listar planos",
     *     description="Retorna lista de planos disponíveis",
     *     operationId="listPlanos",
     *     tags={"Assinaturas"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de planos",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Plano"))
     *     )
     * )
     */
    public function docListPlanos() {}

    /**
     * @OA\Get(
     *     path="/orgaos",
     *     summary="Listar órgãos",
     *     description="Retorna lista de órgãos públicos",
     *     operationId="listOrgaos",
     *     tags={"Órgãos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de órgãos")
     * )
     */
    public function docListOrgaos() {}

    /**
     * @OA\Get(
     *     path="/empenhos",
     *     summary="Listar empenhos",
     *     description="Retorna lista de empenhos",
     *     operationId="listEmpenhos",
     *     tags={"Empenhos"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de empenhos")
     * )
     */
    public function docListEmpenhos() {}

    /**
     * @OA\Get(
     *     path="/notas-fiscais",
     *     summary="Listar notas fiscais",
     *     description="Retorna lista de notas fiscais",
     *     operationId="listNotasFiscais",
     *     tags={"Notas Fiscais"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de notas fiscais")
     * )
     */
    public function docListNotasFiscais() {}
}
