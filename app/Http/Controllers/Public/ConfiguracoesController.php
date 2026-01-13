<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Modules\Auth\Models\UserNotificationPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para configurações gerais do usuário/empresa
 */
class ConfiguracoesController extends Controller
{
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

            // Obter empresa ativa do usuário através do relacionamento
            // Isso garante que estamos buscando no contexto do tenant correto
            if (!$user->empresa_ativa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma empresa ativa encontrada para este usuário.',
                ], 404);
            }

            // Buscar empresa ativa através do relacionamento (garante contexto do tenant)
            $empresaModel = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->first();

            if (!$empresaModel) {
                // Fallback: tentar buscar diretamente pelo ID (caso relacionamento não retorne)
                $empresaModel = $this->empresaRepository->buscarModeloPorId($user->empresa_ativa_id);
                
                if (!$empresaModel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Empresa não encontrada.',
                    ], 404);
                }
            }

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
                    'telefone' => is_array($empresaModel->telefones) && !empty($empresaModel->telefones) ? $empresaModel->telefones[0] : ($empresaModel->telefone ?? null),
                    'telefones' => $empresaModel->telefones ?? [],
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

            // Validar dados
            $validated = $request->validate([
                'razao_social' => 'nullable|string|max:255',
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
            ]);

            // Obter empresa ativa do usuário através do relacionamento
            // Isso garante que estamos buscando no contexto do tenant correto
            if (!$user->empresa_ativa_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma empresa ativa encontrada para este usuário.',
                ], 404);
            }

            // Buscar empresa ativa através do relacionamento (garante contexto do tenant)
            $empresaModel = $user->empresas()->where('empresas.id', $user->empresa_ativa_id)->first();

            if (!$empresaModel) {
                // Fallback: tentar buscar diretamente pelo ID (caso relacionamento não retorne)
                $empresaModel = $this->empresaRepository->buscarModeloPorId($user->empresa_ativa_id);
                
                if (!$empresaModel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Empresa não encontrada.',
                    ], 404);
                }
            }

            // Preparar dados para atualização
            $dadosAtualizacao = [];
            
            if (isset($validated['razao_social'])) {
                $dadosAtualizacao['razao_social'] = $validated['razao_social'];
            }
            if (isset($validated['cnpj'])) {
                $dadosAtualizacao['cnpj'] = $validated['cnpj'];
            }
            if (isset($validated['email'])) {
                $dadosAtualizacao['email'] = $validated['email'];
            }
            if (isset($validated['endereco'])) {
                // Se vier 'endereco', usar como 'logradouro' (a migration usa 'logradouro')
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
                // Se vier telefone único, converter para array
                $dadosAtualizacao['telefones'] = [$validated['telefone']];
            }
            if (isset($validated['telefones'])) {
                $dadosAtualizacao['telefones'] = $validated['telefones'];
            }

            // Atualizar empresa
            $empresaModel->update($dadosAtualizacao);

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
    public function atualizarNotificacoes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado.',
                ], 401);
            }

            // Validar dados
            $validated = $request->validate([
                'email_notificacoes' => 'nullable|boolean',
                'push_notificacoes' => 'nullable|boolean',
                'notificar_processos_novos' => 'nullable|boolean',
                'notificar_documentos_vencendo' => 'nullable|boolean',
                'notificar_prazos' => 'nullable|boolean',
            ]);

            // Criar ou atualizar preferências
            $preferences = UserNotificationPreferences::criarOuAtualizar($user->id, $validated);

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $e->errors(),
            ], 422);
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
}
