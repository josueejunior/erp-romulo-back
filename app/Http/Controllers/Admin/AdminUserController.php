<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Auth\Models\User;
use App\Services\RedisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Modules\Permission\Models\Role;
use Stancl\Tenancy\Facades\Tenancy;

class AdminUserController extends Controller
{
    /**
     * Listar usuários de uma empresa (tenant)
     */
    public function index(Request $request, Tenant $tenant)
    {
        // Garantir que não há tenancy ativo
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Inicializar contexto do tenant
        tenancy()->initialize($tenant);

        try {
            $query = User::withTrashed()->with(['roles', 'empresas']);

            if ($request->has('search') && $request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'ilike', "%{$request->search}%")
                      ->orWhere('email', 'ilike', "%{$request->search}%");
                });
            }

            $users = $query->orderBy('name')->paginate(15);

            // Transformar para incluir roles
            $users->getCollection()->transform(function ($user) {
                $user->roles_list = $user->getRoleNames();
                $user->empresa_ativa = $user->empresas->first();
                return $user;
            });

            return response()->json($users);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Mostrar um usuário específico
     */
    public function show(Request $request, Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::withTrashed()->with(['roles', 'empresas'])->findOrFail($userId);
            $user->roles_list = $user->getRoleNames();
            $user->empresa_ativa = $user->empresas->first();
            
            return response()->json($user);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Criar usuário em uma empresa
     */
    public function store(Request $request, Tenant $tenant)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            // Validação: email deve ser único APENAS dentro deste tenant
            // Cada tenant tem seu próprio banco, então emails podem repetir entre tenants
            // Mas dentro do mesmo tenant, o email deve ser único
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => [
                        'required',
                        'email',
                        'max:255',
                        Rule::unique('users', 'email')
                            ->whereNull('excluido_em'), // Ignorar soft deleted
                    ],
                    'password' => ['required', 'string', 'min:8', new \App\Rules\StrongPassword()],
                    'role' => 'required|string|in:Administrador,Operacional,Financeiro,Consulta',
                    // Aceitar múltiplas empresas ou uma única (compatibilidade)
                    'empresas' => 'nullable|array|min:1',
                    'empresas.*' => 'required|integer|exists:empresas,id',
                    'empresa_id' => [
                        'nullable', // Tornado nullable para compatibilidade
                        function ($attribute, $value, $fail) use ($tenant) {
                            if ($value !== null) {
                                if (!is_numeric($value)) {
                                    $fail('O ID da empresa deve ser um número válido.');
                                    return;
                                }
                                
                                $empresaId = (int)$value;
                                if ($empresaId <= 0) {
                                    $fail('O ID da empresa deve ser um número positivo.');
                                    return;
                                }
                                
                                // Verificar se a empresa existe no banco do tenant atual
                                // O tenancy já foi inicializado acima
                                if (!\App\Models\Empresa::where('id', $empresaId)->exists()) {
                                    $fail('A empresa selecionada não existe neste tenant.');
                                }
                            }
                        }
                    ],
                    'empresa_ativa_id' => 'nullable|integer|exists:empresas,id',
                ], [
                    'name.required' => 'O nome do usuário é obrigatório.',
                    'email.required' => 'O e-mail do usuário é obrigatório.',
                    'email.email' => 'O e-mail deve ser válido.',
                    'email.unique' => 'Este e-mail já está em uso neste tenant.',
                    'password.required' => 'A senha é obrigatória.',
                    'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
                    'role.required' => 'O perfil (role) do usuário é obrigatório.',
                    'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
                    'empresas.min' => 'Selecione pelo menos uma empresa.',
                    'empresas.*.required' => 'Cada empresa selecionada deve ter um ID válido.',
                    'empresas.*.exists' => 'Uma ou mais empresas selecionadas não existem neste tenant.',
                    'empresa_ativa_id.exists' => 'A empresa ativa selecionada não existe neste tenant.',
                ]);
                
                // Verificar se há empresas disponíveis no tenant
                $empresasDisponiveis = \App\Models\Empresa::count();
                if ($empresasDisponiveis === 0) {
                    throw ValidationException::withMessages([
                        'empresas' => ['Este tenant não possui empresas cadastradas. Crie uma empresa primeiro antes de criar usuários.'],
                    ]);
                }
                
                // Validar que pelo menos uma empresa foi fornecida
                if (empty($validated['empresas']) && empty($validated['empresa_id'])) {
                    throw ValidationException::withMessages([
                        'empresas' => ['Selecione pelo menos uma empresa.'],
                    ]);
                }
                
                // Validar que todas as empresas pertencem ao tenant atual
                if (!empty($validated['empresas'])) {
                    $this->validateEmpresasInTenant($validated['empresas'], $tenant);
                }
                if (!empty($validated['empresa_id'])) {
                    $this->validateEmpresasInTenant([$validated['empresa_id']], $tenant);
                }
                
                // Validar que empresa_ativa_id está nas empresas associadas (se fornecido)
                if (!empty($validated['empresa_ativa_id'])) {
                    $empresasFornecidas = $validated['empresas'] ?? (!empty($validated['empresa_id']) ? [$validated['empresa_id']] : []);
                    if (!empty($empresasFornecidas) && !in_array($validated['empresa_ativa_id'], $empresasFornecidas)) {
                        throw ValidationException::withMessages([
                            'empresa_ativa_id' => ['A empresa ativa deve estar entre as empresas selecionadas.'],
                        ]);
                    }
                }
            } catch (ValidationException $e) {
                // Processar erros para garantir mensagens personalizadas
                $errors = $e->errors();
                $processedErrors = [];
                
                foreach ($errors as $field => $messages) {
                    $processedErrors[$field] = array_map(function ($message) use ($field) {
                        // Se a mensagem for uma chave de tradução, usar mensagem personalizada
                        if ($message === 'validation.required' && $field === 'password') {
                            return 'A senha é obrigatória.';
                        }
                        if ($message === 'validation.required' && $field === 'name') {
                            return 'O nome do usuário é obrigatório.';
                        }
                        if ($message === 'validation.required' && $field === 'email') {
                            return 'O e-mail do usuário é obrigatório.';
                        }
                        if ($message === 'validation.required' && $field === 'role') {
                            return 'O perfil (role) do usuário é obrigatório.';
                        }
                        if ($message === 'validation.required' && $field === 'empresas') {
                            return 'Selecione pelo menos uma empresa.';
                        }
                        if (str_contains($message, 'validation.required')) {
                            return 'O campo ' . $field . ' é obrigatório.';
                        }
                        return $message;
                    }, $messages);
                }
                
                // Personalizar mensagem de erro para email duplicado
                if (isset($processedErrors['email'])) {
                    $processedErrors['email'] = ['Este email já está em uso neste tenant.'];
                }
                
                throw ValidationException::withMessages($processedErrors);
            }

            // Determinar empresas a associar (priorizar array empresas[], senão usar empresa_id)
            $empresasIds = !empty($validated['empresas']) 
                ? $validated['empresas'] 
                : (!empty($validated['empresa_id']) ? [$validated['empresa_id']] : []);
            
            // Validar que pelo menos uma empresa foi fornecida (já validado acima, mas garantir)
            if (empty($empresasIds)) {
                throw ValidationException::withMessages([
                    'empresas' => ['Selecione pelo menos uma empresa.'],
                ]);
            }

            // Determinar empresa ativa: usar a fornecida se estiver nas empresas selecionadas, senão usar a primeira
            $empresaAtivaId = $validated['empresa_ativa_id'] ?? $empresasIds[0];
            
            // Garantir que empresa_ativa_id está nas empresas associadas
            if (!in_array($empresaAtivaId, $empresasIds)) {
                $empresaAtivaId = $empresasIds[0];
            }

            // Criar usuário
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'empresa_ativa_id' => $empresaAtivaId,
            ]);

            // Atribuir role
            $user->assignRole($validated['role']);

            // Associar usuário a todas as empresas com o perfil correspondente ao role
            $syncData = [];
            foreach ($empresasIds as $empresaId) {
                $syncData[$empresaId] = [
                    'perfil' => strtolower($validated['role'])
                ];
            }
            $user->empresas()->sync($syncData);

            $user->load(['roles', 'empresas']);
            $user->roles_list = $user->getRoleNames();

            // Invalidar cache de autenticação para este email
            RedisService::invalidateEmailToTenant($validated['email']);
            RedisService::invalidateLoginCache($validated['email']);

            return response()->json([
                'message' => 'Usuário criado com sucesso!',
                'user' => $user,
            ], 201);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::withTrashed()->findOrFail($userId);
            
            // Validação: email deve ser único APENAS dentro deste tenant
            // Ignorar soft deleted e o próprio usuário sendo editado
            try {
                $validated = $request->validate([
                    'name' => 'sometimes|required|string|max:255',
                    'email' => [
                        'sometimes',
                        'required',
                        'email',
                        'max:255',
                        Rule::unique('users', 'email')
                            ->ignore($user->id)
                            ->whereNull('excluido_em'), // Ignorar soft deleted
                    ],
                    'password' => ['nullable', 'string', 'min:8', new \App\Rules\StrongPassword()],
                    'role' => 'sometimes|required|string|in:Administrador,Operacional,Financeiro,Consulta',
                    // Aceitar múltiplas empresas ou uma única (compatibilidade)
                    'empresas' => 'sometimes|array|min:1',
                    'empresas.*' => 'required|integer|exists:empresas,id',
                    'empresa_id' => [
                        'sometimes',
                        function ($attribute, $value, $fail) use ($tenant) {
                            if ($value !== null) {
                                if (!is_numeric($value)) {
                                    $fail('O ID da empresa deve ser um número válido.');
                                    return;
                                }
                                
                                $empresaId = (int)$value;
                                if ($empresaId <= 0) {
                                    $fail('O ID da empresa deve ser um número positivo.');
                                    return;
                                }
                                
                                // Verificar se a empresa existe no banco do tenant atual
                                // O tenancy já foi inicializado acima
                                if (!\App\Models\Empresa::where('id', $empresaId)->exists()) {
                                    $fail('A empresa selecionada não existe neste tenant.');
                                }
                            }
                        }
                    ],
                    'empresa_ativa_id' => 'sometimes|integer|exists:empresas,id',
                ], [
                    'name.required' => 'O nome do usuário é obrigatório.',
                    'email.required' => 'O e-mail do usuário é obrigatório.',
                    'email.email' => 'O e-mail deve ser válido.',
                    'email.unique' => 'Este e-mail já está em uso neste tenant.',
                    'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
                    'role.required' => 'O perfil (role) do usuário é obrigatório.',
                    'role.in' => 'O perfil deve ser: Administrador, Operacional, Financeiro ou Consulta.',
                    'empresas.min' => 'Selecione pelo menos uma empresa.',
                    'empresas.*.required' => 'Cada empresa selecionada deve ter um ID válido.',
                    'empresas.*.exists' => 'Uma ou mais empresas selecionadas não existem neste tenant.',
                    'empresa_ativa_id.exists' => 'A empresa ativa selecionada não existe neste tenant.',
                ]);
                
                // Validar que todas as empresas pertencem ao tenant atual
                if (!empty($validated['empresas'])) {
                    $this->validateEmpresasInTenant($validated['empresas'], $tenant);
                }
                if (!empty($validated['empresa_id'])) {
                    $this->validateEmpresasInTenant([$validated['empresa_id']], $tenant);
                }
                
                // Validar que empresa_ativa_id está nas empresas associadas (se fornecido)
                if (!empty($validated['empresa_ativa_id'])) {
                    $empresasFornecidas = $validated['empresas'] ?? (!empty($validated['empresa_id']) ? [$validated['empresa_id']] : []);
                    
                    // Se empresas foram fornecidas, validar que empresa_ativa_id está entre elas
                    if (!empty($empresasFornecidas)) {
                        if (!in_array($validated['empresa_ativa_id'], $empresasFornecidas)) {
                            throw ValidationException::withMessages([
                                'empresa_ativa_id' => ['A empresa ativa deve estar entre as empresas selecionadas.'],
                            ]);
                        }
                    } else {
                        // Se nenhuma empresa foi fornecida, validar que empresa_ativa_id está nas empresas já associadas
                        $empresasAssociadas = $user->empresas->pluck('id')->toArray();
                        if (!empty($empresasAssociadas) && !in_array($validated['empresa_ativa_id'], $empresasAssociadas)) {
                            throw ValidationException::withMessages([
                                'empresa_ativa_id' => ['A empresa ativa deve estar entre as empresas associadas ao usuário.'],
                            ]);
                        }
                    }
                }
            } catch (ValidationException $e) {
                // Processar erros para garantir mensagens personalizadas
                $errors = $e->errors();
                $processedErrors = [];
                
                foreach ($errors as $field => $messages) {
                    $processedErrors[$field] = array_map(function ($message) use ($field) {
                        // Se a mensagem for uma chave de tradução, usar mensagem personalizada
                        if ($message === 'validation.required' && $field === 'name') {
                            return 'O nome do usuário é obrigatório.';
                        }
                        if ($message === 'validation.required' && $field === 'email') {
                            return 'O e-mail do usuário é obrigatório.';
                        }
                        if ($message === 'validation.required' && $field === 'role') {
                            return 'O perfil (role) do usuário é obrigatório.';
                        }
                        if ($message === 'validation.required' && $field === 'empresas') {
                            return 'Selecione pelo menos uma empresa.';
                        }
                        if (str_contains($message, 'validation.required')) {
                            return 'O campo ' . $field . ' é obrigatório.';
                        }
                        return $message;
                    }, $messages);
                }
                
                // Personalizar mensagem de erro para email duplicado
                if (isset($processedErrors['email'])) {
                    $processedErrors['email'] = ['Este email já está em uso neste tenant.'];
                }
                
                throw ValidationException::withMessages($processedErrors);
            }

            // Atualizar campos básicos
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            // Atualizar associações com empresas
            $empresasIds = null;
            $roleParaPerfil = strtolower($validated['role'] ?? $user->getRoleNames()->first() ?? 'consulta');
            
            if (isset($validated['empresas']) && !empty($validated['empresas'])) {
                // Múltiplas empresas fornecidas
                $empresasIds = $validated['empresas'];
            } elseif (isset($validated['empresa_id']) && !empty($validated['empresa_id'])) {
                // Compatibilidade: apenas uma empresa fornecida
                $empresasIds = [$validated['empresa_id']];
            }
            
            // Se empresas foram fornecidas, atualizar associações
            if ($empresasIds !== null) {
                $syncData = [];
                foreach ($empresasIds as $empresaId) {
                    $syncData[$empresaId] = [
                        'perfil' => $roleParaPerfil
                    ];
                }
                $user->empresas()->sync($syncData);
                
                // Atualizar empresa ativa
                // Se empresa_ativa_id foi fornecido e está nas empresas associadas, usar ele
                // Senão, usar a primeira empresa da lista
                if (isset($validated['empresa_ativa_id']) && in_array($validated['empresa_ativa_id'], $empresasIds)) {
                    $user->empresa_ativa_id = $validated['empresa_ativa_id'];
                } else {
                    // Usar a primeira empresa da lista
                    $user->empresa_ativa_id = $empresasIds[0];
                }
            } elseif (isset($validated['empresa_ativa_id'])) {
                // Apenas empresa_ativa_id foi fornecido (sem alterar empresas associadas)
                // Verificar se a empresa ativa está nas empresas já associadas
                $empresasAssociadas = $user->empresas->pluck('id')->toArray();
                if (in_array($validated['empresa_ativa_id'], $empresasAssociadas)) {
                    $user->empresa_ativa_id = $validated['empresa_ativa_id'];
                } else {
                    // Se a empresa ativa não está nas associadas, usar a primeira disponível
                    if (!empty($empresasAssociadas)) {
                        $user->empresa_ativa_id = $empresasAssociadas[0];
                    }
                }
            }

            $user->save();

            // Atualizar role se fornecido
            if (isset($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            }

            $user->load(['roles', 'empresas']);
            $user->roles_list = $user->getRoleNames();

            // Invalidar cache de autenticação se email ou senha foram alterados
            $oldEmail = $user->getOriginal('email');
            if (isset($validated['email']) && $validated['email'] !== $oldEmail) {
                RedisService::invalidateEmailToTenant($oldEmail);
                RedisService::invalidateLoginCache($oldEmail);
            }
            if (isset($validated['email'])) {
                RedisService::invalidateEmailToTenant($validated['email']);
                RedisService::invalidateLoginCache($validated['email']);
            }
            if (!empty($validated['password'])) {
                // Se senha foi alterada, invalidar cache de login
                RedisService::invalidateLoginCache($user->email);
            }

            return response()->json([
                'message' => 'Usuário atualizado com sucesso!',
                'user' => $user,
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Excluir/Inativar usuário
     */
    public function destroy(Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::findOrFail($userId);
            $userEmail = $user->email;
            
            // Soft delete
            $user->delete();

            // Invalidar cache de autenticação
            RedisService::invalidateEmailToTenant($userEmail);
            RedisService::invalidateLoginCache($userEmail);

            return response()->json([
                'message' => 'Usuário inativado com sucesso!',
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Reativar usuário
     */
    public function reactivate(Tenant $tenant, $userId)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $user = User::withTrashed()->findOrFail($userId);
            $user->restore();

            return response()->json([
                'message' => 'Usuário reativado com sucesso!',
                'user' => $user,
            ]);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Obter empresas disponíveis no tenant
     */
    public function empresas(Tenant $tenant)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize($tenant);

        try {
            $empresas = \App\Models\Empresa::all();
            return response()->json($empresas);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Validar que todas as empresas pertencem ao tenant atual
     */
    private function validateEmpresasInTenant(array $empresasIds, Tenant $tenant): void
    {
        $jaInicializado = tenancy()->initialized;
        
        if (!$jaInicializado) {
            tenancy()->initialize($tenant);
        }
        
        try {
            $empresasExistentes = \App\Models\Empresa::whereIn('id', $empresasIds)->pluck('id')->toArray();
            $empresasInvalidas = array_diff($empresasIds, $empresasExistentes);
            
            if (!empty($empresasInvalidas)) {
                // Se empresa_id foi usado, retornar erro específico
                if (count($empresasInvalidas) === 1 && count($empresasIds) === 1) {
                    throw ValidationException::withMessages([
                        'empresa_id' => ['A empresa selecionada não existe neste tenant.']
                    ]);
                }
                
                throw ValidationException::withMessages([
                    'empresas' => ['As seguintes empresas não existem neste tenant: ' . implode(', ', $empresasInvalidas)]
                ]);
            }
        } finally {
            if (!$jaInicializado) {
                tenancy()->end();
            }
        }
    }
}
