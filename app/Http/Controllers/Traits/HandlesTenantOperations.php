<?php

namespace App\Http\Controllers\Traits;

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

trait HandlesTenantOperations
{

    /**
     * Obter regras de validação para criação de tenant
     */
    protected function getTenantValidationRules(bool $adminRequired = false): array
    {
        $rules = [
            // Dados da Empresa
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'emails_adicionais' => 'nullable|array',
            'banco' => 'nullable|string|max:255',
            'agencia' => 'nullable|string|max:255',
            'conta' => 'nullable|string|max:255',
            'tipo_conta' => 'nullable|string|in:corrente,poupanca',
            'pix' => 'nullable|string|max:255',
            'representante_legal_nome' => 'nullable|string|max:255',
            'representante_legal_cpf' => 'nullable|string|max:14',
            'representante_legal_cargo' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
        ];

        // Dados do Administrador
        if ($adminRequired) {
            $rules['admin_name'] = 'required|string|max:255';
            $rules['admin_email'] = 'required|email|max:255';
            $rules['admin_password'] = ['required', 'string', 'min:8', new \App\Rules\StrongPassword()];
        } else {
            $rules['admin_name'] = 'nullable|string|max:255';
            $rules['admin_email'] = 'nullable|email|max:255';
            $rules['admin_password'] = ['nullable', 'string', 'min:8', new \App\Rules\StrongPassword()];
        }

        return $rules;
    }

    /**
     * Obter mensagens de validação personalizadas
     */
    protected function getTenantValidationMessages(bool $adminRequired = false): array
    {
        $messages = [
            'razao_social.required' => 'A razão social da empresa é obrigatória.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado no sistema.',
            'email.email' => 'O e-mail deve ser válido.',
            'admin_email.email' => 'O e-mail do administrador deve ser válido.',
            'admin_password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ];

        if ($adminRequired) {
            $messages['admin_name.required'] = 'O nome do administrador é obrigatório.';
            $messages['admin_email.required'] = 'O e-mail do administrador é obrigatório.';
            $messages['admin_password.required'] = 'A senha do administrador é obrigatória.';
        }

        return $messages;
    }

    /**
     * Criar tenant com validação e tratamento de erros
     */
    protected function createTenant(Request $request, bool $requireAdmin = false): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate(
                $this->getTenantValidationRules($requireAdmin),
                $this->getTenantValidationMessages($requireAdmin)
            );

            $result = $this->tenantService->createTenantWithEmpresa($validated, $requireAdmin);

            $message = $result['admin_user'] 
                ? 'Empresa e usuário administrador criados com sucesso!'
                : 'Empresa criada com sucesso!';

            $response = [
                'message' => $message,
                'success' => true,
                'data' => [
                    'tenant' => $result['tenant'],
                ],
            ];

            if ($result['admin_user']) {
                $response['data']['admin_user'] = [
                    'name' => $result['admin_user']->name,
                    'email' => $result['admin_user']->email,
                ];
            }

            return response()->json($response, 201);

        } catch (ValidationException $e) {
            // Processar erros para garantir mensagens personalizadas
            $errors = $e->errors();
            $processedErrors = [];
            
            foreach ($errors as $field => $messages) {
                $processedErrors[$field] = array_map(function ($message) use ($field) {
                    // Se a mensagem for uma chave de tradução, usar mensagem personalizada
                    if ($message === 'validation.unique' && $field === 'cnpj') {
                        return 'Este CNPJ já está cadastrado no sistema.';
                    }
                    return $message;
                }, $messages);
            }
            
            return response()->json([
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $processedErrors,
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao criar tenant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $e->getMessage() ?? 'Erro ao processar a solicitação. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }

    /**
     * Atualizar tenant com validação
     */
    protected function updateTenant(Request $request, Tenant $tenant): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'razao_social' => 'sometimes|required|string|max:255',
            'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj,' . $tenant->id,
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:ativa,inativa',
            'endereco' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'cep' => 'nullable|string|max:10',
            'telefones' => 'nullable|array',
            'emails_adicionais' => 'nullable|array',
            'banco' => 'nullable|string|max:255',
            'agencia' => 'nullable|string|max:255',
            'conta' => 'nullable|string|max:255',
            'tipo_conta' => 'nullable|string|in:corrente,poupanca',
            'pix' => 'nullable|string|max:255',
            'representante_legal_nome' => 'nullable|string|max:255',
            'representante_legal_cpf' => 'nullable|string|max:14',
            'representante_legal_cargo' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
        ]);

        // Validar se CNPJ pode ser alterado
        if (!$this->tenantService->canUpdateCnpj($tenant, $validated['cnpj'] ?? null)) {
            return response()->json([
                'message' => 'O CNPJ da empresa não pode ser alterado.',
                'current_cnpj' => $tenant->cnpj,
            ], 422);
        }

        $tenant->update($validated);

        return response()->json([
            'message' => 'Empresa atualizada com sucesso!',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Listar tenants com filtros
     */
    protected function listTenants(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = [
            'status' => $request->input('status'),
            'search' => $request->input('search'),
            'per_page' => $request->input('per_page', 15),
        ];

        $tenants = $this->tenantService->searchTenants($filters);

        return response()->json($tenants);
    }
}

