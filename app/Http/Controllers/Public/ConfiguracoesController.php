<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Http\Requests\Configuracoes\AtualizarNotificacoesRequest;
use App\Modules\Auth\Models\UserNotificationPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller para configurações gerais do usuário/empresa
 */
class ConfiguracoesController extends Controller
{
    private const TENANT_UPDATE_RULES = [
        'razao_social' => 'nullable|string|max:255',
        'nome_fantasia' => 'nullable|string|max:255',
        'cnpj' => 'nullable|string|max:18',
        'email' => 'nullable|email|max:255',
        'endereco' => 'nullable|string|max:255',
        'logradouro' => 'nullable|string|max:255',
        'numero' => 'nullable|string|max:20',
        'bairro' => 'nullable|string|max:255',
        'complemento' => 'nullable|string|max:255',
        'cidade' => 'nullable|string|max:255',
        'estado' => 'nullable|string|max:2',
        'cep' => 'nullable|string|max:10',
        'telefone' => 'nullable|string|max:20',
        'telefones' => 'nullable|array',
        'representante_legal_nome' => 'nullable|string|max:255',
        'representante_legal_cpf' => 'nullable|string|max:14',
        'representante_legal_rg' => 'nullable|string|max:30',
        'representante_legal_telefone' => 'nullable|string|max:20',
        'representante_legal_email' => 'nullable|email|max:255',
        'representante_legal_cargo' => 'nullable|string|max:255',
        'email_financeiro' => 'nullable|email|max:255',
        'email_licitacao' => 'nullable|email|max:255',
        'whatsapp' => 'nullable|string|max:20',
        'telefone_fixo' => 'nullable|string|max:20',
        'site' => 'nullable|string|max:255',
        'inscricao_estadual' => 'nullable|string|max:20',
        'inscricao_municipal' => 'nullable|string|max:20',
        'cnae_principal' => 'nullable|string|max:20',
        'data_abertura' => 'nullable|date',
        'regime_tributario' => 'nullable|string|max:100',
        'banco' => 'nullable|string|max:255',
        'agencia' => 'nullable|string|max:20',
        'conta' => 'nullable|string|max:20',
        'tipo_conta' => 'nullable|string|max:20',
        'pix' => 'nullable|string|max:255',
        'favorecido_razao_social' => 'nullable|string|max:255',
        'favorecido_cnpj' => 'nullable|string|max:18',
        'responsavel_comercial' => 'nullable|string|max:255',
        'responsavel_financeiro' => 'nullable|string|max:255',
        'responsavel_licitacoes' => 'nullable|string|max:255',
        'ramo_atuacao' => 'nullable|string|max:255',
        'principais_produtos_servicos' => 'nullable|string|max:500',
        'marcas_trabalhadas' => 'nullable|string|max:500',
        'observacoes' => 'nullable|string|max:1000',
    ];

    private const TENANT_DATA_FIELDS = [
        'email_financeiro',
        'email_licitacao',
        'whatsapp',
        'telefone_fixo',
        'site',
        'inscricao_estadual',
        'inscricao_municipal',
        'cnae_principal',
        'data_abertura',
        'regime_tributario',
        'favorecido_razao_social',
        'favorecido_cnpj',
        'representante_legal_cpf',
        'representante_legal_rg',
        'representante_legal_telefone',
        'representante_legal_email',
        'responsavel_comercial',
        'responsavel_financeiro',
        'responsavel_licitacoes',
        'ramo_atuacao',
        'principais_produtos_servicos',
        'marcas_trabalhadas',
        'observacoes',
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
        'representante_legal_nome',
        'representante_legal_cargo',
    ];

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * Obtém dados da empresa ativa do usuário autenticado
     */
    public function getTenant(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado.',
                ], 401);
            }

            if (!$user->empresa_ativa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma empresa ativa encontrada para este usuário.',
                ], 404);
            }
            $empresaModel = $this->resolveEmpresaAtivaModel($user);
            if (!$empresaModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não encontrada.',
                ], 404);
            }

            $tenantModel = tenancy()->initialized ? tenancy()->tenant : null;
            $tenantData = is_array($tenantModel?->data) ? $tenantModel->data : [];
            $telefonesEmpresa = is_array($empresaModel->telefones) ? $empresaModel->telefones : [];
            $telefonePrincipal = $this->resolveTelefonePrincipal($empresaModel, $telefonesEmpresa);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $empresaModel->id,
                    'razao_social' => $empresaModel->razao_social,
                    'nome_fantasia' => $empresaModel->nome_fantasia,
                    'cnpj' => $empresaModel->cnpj,
                    'email' => $empresaModel->email,
                    'endereco' => $empresaModel->logradouro,
                    'numero' => $empresaModel->numero,
                    'bairro' => $empresaModel->bairro,
                    'complemento' => $empresaModel->complemento,
                    'cidade' => $empresaModel->cidade,
                    'estado' => $empresaModel->estado,
                    'cep' => $empresaModel->cep,
                    'telefone' => $telefonePrincipal ?? $empresaModel->telefone ?? null,
                    'telefones' => $telefonesEmpresa,
                    'representante_legal_nome' => $empresaModel->representante_legal,
                    'representante_legal_cargo' => $empresaModel->cargo_representante,
                    // Campos complementares vindos do tenant central (evita abas vazias para qualquer usuário)
                    'email_financeiro' => $tenantData['email_financeiro'] ?? null,
                    'email_licitacao' => $tenantData['email_licitacao'] ?? null,
                    'whatsapp' => $tenantData['whatsapp'] ?? null,
                    'telefone_fixo' => $tenantData['telefone_fixo'] ?? null,
                    'site' => $tenantData['site'] ?? null,
                    'inscricao_estadual' => $tenantData['inscricao_estadual'] ?? null,
                    'inscricao_municipal' => $tenantData['inscricao_municipal'] ?? null,
                    'cnae_principal' => $tenantData['cnae_principal'] ?? null,
                    'data_abertura' => $tenantData['data_abertura'] ?? null,
                    'regime_tributario' => $tenantData['regime_tributario'] ?? null,
                    'banco' => $tenantData['banco'] ?? $empresaModel->banco_nome ?? null,
                    'agencia' => $tenantData['agencia'] ?? $empresaModel->banco_agencia ?? null,
                    'conta' => $tenantData['conta'] ?? $empresaModel->banco_conta ?? null,
                    'tipo_conta' => $tenantData['tipo_conta'] ?? $empresaModel->banco_tipo ?? null,
                    'pix' => $tenantData['pix'] ?? null,
                    'favorecido_razao_social' => $tenantData['favorecido_razao_social'] ?? null,
                    'favorecido_cnpj' => $tenantData['favorecido_cnpj'] ?? null,
                    'representante_legal_cpf' => $tenantData['representante_legal_cpf'] ?? null,
                    'representante_legal_rg' => $tenantData['representante_legal_rg'] ?? null,
                    'representante_legal_telefone' => $tenantData['representante_legal_telefone'] ?? null,
                    'representante_legal_email' => $tenantData['representante_legal_email'] ?? null,
                    'responsavel_comercial' => $tenantData['responsavel_comercial'] ?? null,
                    'responsavel_financeiro' => $tenantData['responsavel_financeiro'] ?? null,
                    'responsavel_licitacoes' => $tenantData['responsavel_licitacoes'] ?? null,
                    'ramo_atuacao' => $tenantData['ramo_atuacao'] ?? null,
                    'principais_produtos_servicos' => $tenantData['principais_produtos_servicos'] ?? null,
                    'marcas_trabalhadas' => $tenantData['marcas_trabalhadas'] ?? null,
                    'observacoes' => $tenantData['observacoes'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('ConfiguracoesController::getTenant - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter dados da empresa.',
            ], 500);
        }
    }

    /**
     * Atualiza dados da empresa ativa do usuário autenticado
     */
    public function atualizarTenant(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado.',
                ], 401);
            }

            $validated = $request->validate(self::TENANT_UPDATE_RULES);

            if (!$user->empresa_ativa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma empresa ativa encontrada para este usuário.',
                ], 404);
            }

            $empresaModel = $this->resolveEmpresaAtivaModel($user);
            if (!$empresaModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não encontrada.',
                ], 404);
            }

            $empresaModel = $this->resolveEmpresaByCnpjContext($user, $empresaModel, $validated);
            $this->assertCnpjNotInUseByAnotherEmpresa($empresaModel, $validated);
            $dadosAtualizacao = $this->buildEmpresaUpdatePayload($validated);

            // Atualizar empresa
            $empresaModel->update($dadosAtualizacao);

            // Persistir também dados estendidos no tenant central (mesma fonte do /auth/user)
            $this->syncTenantData($validated);

            Log::info('ConfiguracoesController::atualizarTenant - Empresa atualizada com sucesso', [
                'user_id' => $user->id,
                'empresa_id' => $empresaModel->id,
                'dados_atualizados' => array_keys($dadosAtualizacao),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dados da empresa atualizados com sucesso!',
                'data' => [
                    'id' => $empresaModel->id,
                    'razao_social' => $empresaModel->razao_social,
                    'cnpj' => $empresaModel->cnpj,
                    'email' => $empresaModel->email,
                    'cidade' => $empresaModel->cidade,
                    'estado' => $empresaModel->estado,
                    'cep' => $empresaModel->cep,
                    'telefones' => $empresaModel->telefones,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ConfiguracoesController::atualizarTenant - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar dados da empresa. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Obtém configurações de notificações do usuário autenticado
     */
    public function getNotificacoes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado.',
                ], 401);
            }

            $preferences = UserNotificationPreferences::buscarOuPadrao($user->id);

            return response()->json([
                'success' => true,
                'data' => $preferences,
            ]);
        } catch (\Exception $e) {
            Log::error('ConfiguracoesController::getNotificacoes - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter configurações de notificações.',
            ], 500);
        }
    }

    /**
     * Atualiza configurações de notificações do usuário autenticado
     */
    public function atualizarNotificacoes(AtualizarNotificacoesRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Dados já validados pelo Form Request
            $validated = array_filter($request->validated(), fn($v) => $v !== null);

            if (empty($validated)) {
                $preferences = UserNotificationPreferences::where('user_id', $user->id)->first()
                    ?? new UserNotificationPreferences(['user_id' => $user->id]);
            } else {
                // Criar ou atualizar preferências
                $preferences = UserNotificationPreferences::criarOuAtualizar($user->id, $validated);
            }

            Log::info('ConfiguracoesController::atualizarNotificacoes - Preferências atualizadas com sucesso', [
                'user_id' => $user->id,
                'preferencias' => $validated,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Configurações de notificações salvas com sucesso!',
                'data' => [
                    'email_notificacoes' => $preferences->email_notificacoes,
                    'push_notificacoes' => $preferences->push_notificacoes,
                    'notificar_processos_novos' => $preferences->notificar_processos_novos,
                    'notificar_documentos_vencendo' => $preferences->notificar_documentos_vencendo,
                    'notificar_prazos' => $preferences->notificar_prazos,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('ConfiguracoesController::atualizarNotificacoes - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar configurações de notificações. Tente novamente.',
            ], 500);
        }
    }

    private function resolveEmpresaAtivaModel(object $user): ?\App\Models\Empresa
    {
        $empresaModel = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->first();
        if ($empresaModel) {
            return $empresaModel;
        }

        return $this->empresaRepository->buscarModeloPorId($user->empresa_ativa_id);
    }

    private function resolveTelefonePrincipal(\App\Models\Empresa $empresaModel, array $telefonesEmpresa): ?string
    {
        if (!empty($telefonesEmpresa)) {
            $primeiroTelefone = $telefonesEmpresa[0];
            return is_array($primeiroTelefone)
                ? ($primeiroTelefone['numero'] ?? null)
                : $primeiroTelefone;
        }

        return $empresaModel->telefone ?? null;
    }

    private function resolveEmpresaByCnpjContext(object $user, \App\Models\Empresa $empresaModel, array $validated): \App\Models\Empresa
    {
        if (!isset($validated['cnpj']) || $validated['cnpj'] === $empresaModel->cnpj) {
            return $empresaModel;
        }

        $empresaComMesmoCnpj = \App\Models\Empresa::query()
            ->where('cnpj', $validated['cnpj'])
            ->where('id', '!=', $empresaModel->id)
            ->first();

        if (!$empresaComMesmoCnpj) {
            return $empresaModel;
        }

        $empresaPertenceAoUsuario = $user->empresas()
            ->where('empresas.id', $empresaComMesmoCnpj->id)
            ->exists();

        if (!$empresaPertenceAoUsuario) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este CNPJ já está cadastrado em outra empresa deste tenant.',
            ]);
        }

        if ((int) $user->empresa_ativa_id !== (int) $empresaComMesmoCnpj->id) {
            $user->empresa_ativa_id = $empresaComMesmoCnpj->id;
            $user->save();
        }

        return $empresaComMesmoCnpj;
    }

    private function assertCnpjNotInUseByAnotherEmpresa(\App\Models\Empresa $empresaModel, array $validated): void
    {
        if (!isset($validated['cnpj']) || $validated['cnpj'] === $empresaModel->cnpj) {
            return;
        }

        $cnpjEmUso = \App\Models\Empresa::query()
            ->where('cnpj', $validated['cnpj'])
            ->where('id', '!=', $empresaModel->id)
            ->exists();

        if ($cnpjEmUso) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este CNPJ já está cadastrado em outra empresa deste tenant.',
            ]);
        }
    }

    private function buildEmpresaUpdatePayload(array $validated): array
    {
        $dadosAtualizacao = [];

        if (isset($validated['razao_social'])) {
            $dadosAtualizacao['razao_social'] = $validated['razao_social'];
        }
        if (array_key_exists('nome_fantasia', $validated)) {
            $dadosAtualizacao['nome_fantasia'] = $validated['nome_fantasia'];
        }
        if (isset($validated['cnpj'])) {
            $dadosAtualizacao['cnpj'] = $validated['cnpj'];
        }
        if (isset($validated['email'])) {
            $dadosAtualizacao['email'] = $validated['email'];
        }
        if (isset($validated['endereco'])) {
            $dadosAtualizacao['logradouro'] = $validated['endereco'];
        }
        if (isset($validated['logradouro'])) {
            $dadosAtualizacao['logradouro'] = $validated['logradouro'];
        }
        if (isset($validated['numero'])) {
            $dadosAtualizacao['numero'] = $validated['numero'];
        }
        if (isset($validated['bairro'])) {
            $dadosAtualizacao['bairro'] = $validated['bairro'];
        }
        if (isset($validated['complemento'])) {
            $dadosAtualizacao['complemento'] = $validated['complemento'];
        }
        if (isset($validated['cidade'])) {
            $dadosAtualizacao['cidade'] = $validated['cidade'];
        }
        if (isset($validated['estado'])) {
            $dadosAtualizacao['estado'] = strtoupper($validated['estado']);
        }
        if (isset($validated['cep'])) {
            $dadosAtualizacao['cep'] = $validated['cep'];
        }
        if (isset($validated['telefone'])) {
            $dadosAtualizacao['telefones'] = [$validated['telefone']];
        }
        if (isset($validated['telefones'])) {
            $dadosAtualizacao['telefones'] = $validated['telefones'];
        }
        if (isset($validated['representante_legal_nome'])) {
            $dadosAtualizacao['representante_legal'] = $validated['representante_legal_nome'];
        }
        if (isset($validated['representante_legal_cargo'])) {
            $dadosAtualizacao['cargo_representante'] = $validated['representante_legal_cargo'];
        }

        return $dadosAtualizacao;
    }

    private function syncTenantData(array $validated): void
    {
        if (!tenancy()->initialized || !tenancy()->tenant) {
            return;
        }

        $tenant = tenancy()->tenant;
        $tenantDataAtual = is_array($tenant->data) ? $tenant->data : [];

        foreach (self::TENANT_DATA_FIELDS as $campo) {
            if (array_key_exists($campo, $validated)) {
                $tenantDataAtual[$campo] = $validated[$campo];
            }
        }

        $tenant->data = $tenantDataAtual;
        $tenant->save();
    }
}
