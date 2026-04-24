<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\LoginDTO;
use App\Application\CadastroPublico\Services\UsersLookupService;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Senha;
use App\Domain\Exceptions\CredenciaisInvalidasException;
use App\Domain\Exceptions\MultiplosTenantsException;
use App\Services\AdminTenancyRunner;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\UserLookup;
use App\Modules\Auth\Models\AdminUser;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Login de Usuário
 * Orquestra o login, mas não sabe nada de banco de dados diretamente
 * 
 * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
 */
class LoginUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AdminTenancyRunner $adminTenancyRunner,
        private UsersLookupService $usersLookupService,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * 🛡️ ESTRATÉGIA DE VALIDAÇÃO EM CAMADAS:
     * 1. Camada de Localização (Global) - users_lookup
     * 2. Camada de Inicialização e "Double-Check" - Validação cruzada
     * 3. Camada de Estado (Domínio) - Status e permissões
     * 
     * Retorna array com dados do usuário, tenant, empresa e token
     */
    public function executar(LoginDTO $dto): array
    {
        Log::info('LoginUseCase::executar - Iniciando', [
            'email' => $dto->email,
            'has_tenant_id' => !empty($dto->tenantId),
        ]);
        
        try {
            // Validar email usando Value Object
            $email = Email::criar($dto->email);

            // 🛡️ CAMADA 1: Resolver Tenant (Estratégia O(1))
            // Este método implementa toda a lógica de resolução de tenant,
            // incluindo busca em users_lookup e tratamento de múltiplos tenants
            [$tenant, $empresaLoginIdResolvido] = $this->resolverTenant($dto, $email->value, $dto->password);

            // 🛡️ CAMADA 2: Inicializar Conexão
            // A partir daqui, as queries rodam no banco do cliente
            Log::debug('LoginUseCase::executar - Inicializando tenancy', ['tenant_id' => $tenant->id]);
            tenancy()->initialize($tenant);

            // 🛡️ CAMADA 2: Validação Cruzada (Integridade)
            // Verificar se o usuário realmente existe no banco do tenant
            // Isso previne "usuário fantasma" (existe no lookup mas não no tenant)
            Log::debug('LoginUseCase::executar - Buscando usuário no banco do tenant', [
                'email' => $email->value,
                'tenant_id' => $tenant->id,
            ]);
            $user = $this->userRepository->buscarPorEmail($email->value);
            
            if (!$user) {
                // 🔥 FALHA DE INTEGRIDADE: Existe no lookup mas não no banco do tenant
                // Tratar como credenciais inválidas (não revelar problema de sistema)
                Log::critical('LoginUseCase::executar - DESSINCRONIA_TENANT: Usuário não encontrado no banco do Tenant', [
                    'email' => $email->value,
                    'tenant_id' => $tenant->id,
                    'problema' => 'Usuário existe em users_lookup mas não no banco do tenant',
                ]);
                
                // Prevenir timing attack
                $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
                Hash::check($dto->password, $dummyHash);
                
                throw new CredenciaisInvalidasException('Usuário não encontrado nesta empresa.');
            }

            // 🛡️ CAMADA 3: Validação de Credenciais (Value Object Senha)
            Log::debug('LoginUseCase::executar - Validando senha');
            $senha = new Senha($user->senhaHash);
            $isValidPassword = $senha->verificar($dto->password);
            
            if (!$isValidPassword) {
                Log::warning('LoginUseCase::executar - Senha incorreta', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'tenant_id' => $tenant->id,
                ]);
                throw new CredenciaisInvalidasException();
            }

            // 🛡️ CAMADA 3: Validação de Status (Políticas de Negócio)
            $this->validarStatusAcesso($user, $tenant);

            // 🛡️ CAMADA 3: Resolução de Empresa
            Log::debug('LoginUseCase::executar - Resolvendo empresa ativa');
            $empresaIdPreferida = $dto->empresaId ?? $empresaLoginIdResolvido;
            $empresaAtiva = $this->resolverEmpresaAtiva($user, $tenant, $empresaIdPreferida);

            // 🛡️ CAMADA 3: Geração de Token (Stateless)
            // Garantir que o tenant_id no JWT seja EXATAMENTE o tenant validado
            $tenantIdFinal = $tenant->id;
            
            // Verificar se o tenant_id fornecido no request corresponde ao encontrado
            if ($dto->tenantId && $dto->tenantId !== $tenantIdFinal) {
                Log::warning('LoginUseCase::executar - ⚠️ Tenant ID fornecido não corresponde ao encontrado', [
                    'tenant_id_fornecido' => $dto->tenantId,
                    'tenant_id_encontrado' => $tenantIdFinal,
                    'user_id' => $user->id,
                    'acao' => 'Usando tenant_id encontrado (fonte de verdade)',
                ]);
            }
            
            $jwtService = app(\App\Services\JWTService::class);
            $token = $jwtService->generateToken([
                'user_id'    => $user->id,
                'tenant_id'  => $tenantIdFinal, // 🔥 CRÍTICO: Usar tenant_id validado
                'empresa_id' => $empresaAtiva?->id,
                'role'       => null, // Pode ser adicionado se necessário
            ]);

            Log::info('LoginUseCase::executar - Login realizado com sucesso', [
                'user_id' => $user->id,
                'tenant_id' => $tenantIdFinal,
                'empresa_ativa_id' => $empresaAtiva?->id,
                'consistencia' => 'Token JWT gerado com tenant_id validado e consistente',
            ]);

            // Buscar modelo completo do usuário para foto_perfil (se necessário)
            $userModel = $this->userRepository->buscarModeloPorId($user->id);
            
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nome,
                    'email' => $user->email,
                    'empresa_ativa_id' => $user->empresaAtivaId,
                    'foto_perfil' => $userModel?->foto_perfil ?? null,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'razao_social' => $tenant->razao_social,
                ],
                'empresa' => $empresaAtiva ? [
                    'id' => $empresaAtiva->id,
                    'razao_social' => $empresaAtiva->razaoSocial,
                ] : null,
                'token' => $token, // JWT token stateless
            ];
        } catch (CredenciaisInvalidasException | MultiplosTenantsException $e) {
            // Re-lançar exceções de domínio sem modificar
            throw $e;
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('LoginUseCase::executar - Erro de banco de dados', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (\RuntimeException $e) {
            Log::error('LoginUseCase::executar - Erro de infraestrutura', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('LoginUseCase::executar - Erro inesperado', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
            ]);
            throw new CredenciaisInvalidasException();
        } finally {
            // Finalizar contexto do tenant
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * 🛡️ CAMADA 1: Resolver Tenant (Estratégia O(1))
     * 
     * Implementa toda a lógica de resolução de tenant:
     * - Se tenant_id fornecido → validar e retornar
     * - Se não fornecido → buscar em users_lookup
     * - Se múltiplos tenants → lançar MultiplosTenantsException
     * - Se não encontrar → lançar CredenciaisInvalidasException
     * 
     * @param LoginDTO $dto
     * @param string $email
     * @return array{0: Tenant, 1: ?int} [tenant, empresa_login_id quando há escolha única]
     * @throws CredenciaisInvalidasException
     * @throws MultiplosTenantsException
     */
    private function resolverTenant(LoginDTO $dto, string $email, string $password): array
    {
        // Caso 1: Tenant ID fornecido explicitamente
        if ($dto->tenantId) {
            $tenantId = (int) $dto->tenantId;
            Log::debug('LoginUseCase::resolverTenant - Tenant ID fornecido', ['tenant_id' => $tenantId]);

            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            if (! $tenantDomain) {
                Log::warning('LoginUseCase::resolverTenant - Tenant não encontrado', [
                    'tenant_id' => $tenantId,
                    'email' => $email,
                ]);
                throw new CredenciaisInvalidasException('Empresa não encontrada.');
            }

            $tenant = $this->tenantRepository->buscarModeloPorId($tenantId);
            if (! $tenant) {
                Log::error('LoginUseCase::resolverTenant - Modelo Tenant não pôde ser carregado', [
                    'tenant_id' => $tenantId,
                ]);
                throw new CredenciaisInvalidasException('Erro ao acessar empresa.');
            }

            return [$tenant, null];
        }

        // Caso 2: Buscar automaticamente via users_lookup (O(1))
        Log::debug('LoginUseCase::resolverTenant - Buscando tenant via users_lookup', ['email' => $email]);

        $lookups = $this->usersLookupService->encontrarPorEmail($email);

        if (empty($lookups)) {
            Log::debug('LoginUseCase::resolverTenant - Usuário não encontrado em users_lookup, tentando fallback', [
                'email' => $email,
            ]);

            $tenant = $this->buscarTenantPorEmail($email);
            if (! $tenant) {
                throw new CredenciaisInvalidasException();
            }

            try {
                tenancy()->initialize($tenant);
                $user = $this->userRepository->buscarPorEmail($email);
                if ($user) {
                    $empresaAtiva = $this->userRepository->buscarEmpresaAtiva($user->id);
                    $this->usersLookupService->registrar(
                        $tenant->id,
                        $user->id,
                        $empresaAtiva?->id,
                        $email,
                        $tenant->cnpj ?? ''
                    );
                    Log::info('LoginUseCase::resolverTenant - Registro adicionado ao users_lookup via fallback', [
                        'email' => $email,
                        'tenant_id' => $tenant->id,
                    ]);
                }
                tenancy()->end();
            } catch (\Exception $e) {
                Log::warning('LoginUseCase::resolverTenant - Erro ao registrar lookup via fallback', [
                    'error' => $e->getMessage(),
                ]);
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }

            return [$tenant, null];
        }

        $linhas = $this->coletarLinhasSelecaoLogin($email, $password, $lookups);

        if ($linhas === []) {
            throw new CredenciaisInvalidasException();
        }

        if (count($linhas) === 1) {
            $tenantId = (int) $linhas[0]['tenant_id'];
            $tenant = $this->tenantRepository->buscarModeloPorId($tenantId);
            if (! $tenant) {
                throw new CredenciaisInvalidasException();
            }
            $empresaIdLinha = $linhas[0]['empresa_id'];

            return [$tenant, $empresaIdLinha !== null ? (int) $empresaIdLinha : null];
        }

        Log::info('LoginUseCase::resolverTenant - Múltiplas opções de acesso (tenants e/ou empresas)', [
            'email' => $email,
            'linhas' => count($linhas),
        ]);

        throw new MultiplosTenantsException(
            'Este email está associado a múltiplas empresas. Selecione qual deseja acessar.',
            $linhas
        );
    }

    /**
     * Uma linha por empresa vinculada ao usuário no tenant (mesmo tenant pode aparecer mais de uma vez).
     * Só inclui linha após validar senha no tenant (evita vazar dados sem autenticação).
     *
     * @param  array<int, \App\Domain\UsersLookup\Entities\UserLookup>  $lookups
     * @return list<array{tenant_id: int, empresa_id: ?int, razao_social: string, cnpj: string, user_id: int}>
     */
    private function coletarLinhasSelecaoLogin(string $email, string $password, array $lookups): array
    {
        $linhas = [];

        foreach ($lookups as $lookup) {
            try {
                $tenantDomain = $this->tenantRepository->buscarPorId($lookup->tenantId);
                if (! $tenantDomain) {
                    Log::warning('LoginUseCase::coletarLinhasSelecaoLogin - Lookup aponta para tenant inexistente', [
                        'lookup_id' => $lookup->id,
                        'tenant_id' => $lookup->tenantId,
                    ]);

                    continue;
                }

                if (! $this->validarSenhaNoTenant($tenantDomain, $email, $password)) {
                    continue;
                }

                $empresaRows = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($lookup) {
                    return DB::table('empresa_user')
                        ->join('empresas', 'empresas.id', '=', 'empresa_user.empresa_id')
                        ->where('empresa_user.user_id', $lookup->userId)
                        ->orderBy('empresas.id')
                        ->get([
                            'empresas.id as empresa_id',
                            'empresas.razao_social',
                            'empresas.cnpj',
                        ]);
                });

                if ($empresaRows->isNotEmpty()) {
                    foreach ($empresaRows as $emp) {
                        $linhas[] = [
                            'tenant_id' => (int) $tenantDomain->id,
                            'empresa_id' => (int) $emp->empresa_id,
                            'razao_social' => (string) $emp->razao_social,
                            'cnpj' => $this->formatarCnpjParaExibicaoLogin((string) ($emp->cnpj ?? '')),
                            'user_id' => (int) $lookup->userId,
                        ];
                    }

                    continue;
                }

                if ($lookup->empresaId !== null && $lookup->empresaId > 0) {
                    $emp = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($lookup) {
                        return Empresa::query()->find($lookup->empresaId);
                    });
                    if ($emp && $emp->razao_social) {
                        $linhas[] = [
                            'tenant_id' => (int) $tenantDomain->id,
                            'empresa_id' => (int) $emp->id,
                            'razao_social' => (string) $emp->razao_social,
                            'cnpj' => $this->formatarCnpjParaExibicaoLogin((string) ($emp->cnpj ?? '')),
                            'user_id' => (int) $lookup->userId,
                        ];

                        continue;
                    }
                }

                $rotulo = $this->rotuloEmpresaParaSelecaoMultipla($tenantDomain, $lookup);
                $linhas[] = [
                    'tenant_id' => (int) $tenantDomain->id,
                    'empresa_id' => null,
                    'razao_social' => $rotulo['razao_social'],
                    'cnpj' => $rotulo['cnpj'],
                    'user_id' => (int) $lookup->userId,
                ];
            } catch (\Exception $e) {
                Log::error('LoginUseCase::coletarLinhasSelecaoLogin - Erro ao montar linhas', [
                    'tenant_id' => $lookup->tenantId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $linhas;
    }

    /**
     * Valida a senha do usuário dentro do contexto de um tenant específico.
     * Retorna true se o usuário existe no tenant E a senha corresponde.
     * Retorna false para dessincronia (usuário no lookup mas não no tenant) ou senha errada.
     * Exceções de infraestrutura (DB fora, etc.) propagam normalmente.
     */
    private function validarSenhaNoTenant(
        \App\Domain\Tenant\Entities\Tenant $tenantDomain,
        string $email,
        string $password
    ): bool {
        return $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email, $password) {
            $user = $this->userRepository->buscarPorEmail($email);
            if (!$user || empty($user->senhaHash)) {
                return false;
            }
            return Hash::check($password, $user->senhaHash);
        }) ?? false;
    }

    /**
     * Na seleção de múltiplos tenants, cada linha deve refletir a empresa do vínculo
     * (users_lookup → empresas no DB do tenant), não só o CNPJ/razão do cadastro do tenant,
     * que pode ser outra empresa do mesmo ambiente.
     *
     * @param  \App\Domain\UsersLookup\Entities\UserLookup  $lookup
     * @return array{razao_social: string, cnpj: string}
     */
    private function rotuloEmpresaParaSelecaoMultipla(
        \App\Domain\Tenant\Entities\Tenant $tenantDomain,
        \App\Domain\UsersLookup\Entities\UserLookup $lookup
    ): array {
        $razaoFallback = $tenantDomain->razaoSocial;
        $cnpjFallback = (string) ($tenantDomain->cnpj ?? '');

        try {
            $info = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($lookup) {
                if ($lookup->empresaId !== null && $lookup->empresaId > 0) {
                    $emp = Empresa::query()->find($lookup->empresaId);
                    if ($emp && $emp->razao_social) {
                        return ['razao' => (string) $emp->razao_social, 'cnpj' => (string) ($emp->cnpj ?? '')];
                    }
                }
                $cnpjLimpo = preg_replace('/\D/', '', $lookup->cnpj) ?? '';
                if (strlen($cnpjLimpo) === 14) {
                    $emp = Empresa::query()->where('cnpj', $cnpjLimpo)->first();
                    if ($emp && $emp->razao_social) {
                        return ['razao' => (string) $emp->razao_social, 'cnpj' => (string) ($emp->cnpj ?? $cnpjLimpo)];
                    }
                }

                return null;
            });

            if (is_array($info) && ($info['razao'] ?? '') !== '') {
                return [
                    'razao_social' => $info['razao'],
                    'cnpj' => $this->formatarCnpjParaExibicaoLogin((string) ($info['cnpj'] ?? $lookup->cnpj)),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('LoginUseCase::rotuloEmpresaParaSelecaoMultipla - usando dados do tenant', [
                'tenant_id' => $tenantDomain->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'razao_social' => $razaoFallback,
            'cnpj' => $this->formatarCnpjParaExibicaoLogin($cnpjFallback !== '' ? $cnpjFallback : $lookup->cnpj),
        ];
    }

    private function formatarCnpjParaExibicaoLogin(string $cnpj): string
    {
        $d = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($d) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($d, 0, 2),
            substr($d, 2, 3),
            substr($d, 5, 3),
            substr($d, 8, 4),
            substr($d, 12, 2)
        );
    }

    /**
     * 🛡️ CAMADA 3: Resolver Empresa Ativa
     *
     * Busca e valida a empresa ativa do usuário, garantindo consistência com o tenant
     */
    private function resolverEmpresaAtiva($user, Tenant $tenant, ?int $empresaIdPreferida = null)
    {
        if ($empresaIdPreferida !== null && $empresaIdPreferida > 0) {
            try {
                $this->userRepository->atualizarEmpresaAtiva($user->id, $empresaIdPreferida);

                return $this->userRepository->buscarEmpresaAtiva($user->id);
            } catch (DomainException $e) {
                Log::warning('LoginUseCase::resolverEmpresaAtiva - empresa_id da seleção inválida ou sem acesso', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'empresa_id' => $empresaIdPreferida,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $empresaAtiva = $this->userRepository->buscarEmpresaAtiva($user->id);

        $lookupEmpresaId = UserLookup::query()
            ->where('email', $user->email)
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'ativo')
            ->whereNull('deleted_at')
            ->whereNotNull('empresa_id')
            ->value('empresa_id');

        if ($lookupEmpresaId) {
            $lookupEmpresa = Empresa::find((int) $lookupEmpresaId);
            $lookupTemAssinaturaAtiva = \App\Domain\Assinatura\Queries\AssinaturaQueries::empresaTemAssinaturaValida((int) $lookupEmpresaId);

            if ($lookupEmpresa && (!$empresaAtiva || $empresaAtiva->id !== $lookupEmpresa->id)) {
                if ($lookupTemAssinaturaAtiva || !$empresaAtiva) {
                    $empresaAtiva = $lookupEmpresa;
                    Log::info('LoginUseCase::resolverEmpresaAtiva - Empresa preferida via users_lookup', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenant->id,
                        'empresa_id' => $empresaAtiva->id,
                        'lookup_tem_assinatura_ativa' => $lookupTemAssinaturaAtiva,
                    ]);
                }
            }
        }

        if (!$empresaAtiva) {
            $empresas = $this->userRepository->buscarEmpresas($user->id);
            $empresaAtiva = !empty($empresas) ? $empresas[0] : null;
        }

        if (!$empresaAtiva) {
            $assinaturaEmpresaId = $this->resolverEmpresaDaAssinaturaAtiva($tenant->id);

            if ($assinaturaEmpresaId) {
                $empresaAtiva = Empresa::find($assinaturaEmpresaId);

                if ($empresaAtiva) {
                    Log::warning('LoginUseCase::resolverEmpresaAtiva - Empresa inferida pela assinatura ativa', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenant->id,
                        'empresa_id' => $empresaAtiva->id,
                    ]);
                }
            }
        }

        if ($empresaAtiva) {
            try {
                $user = $this->userRepository->atualizarEmpresaAtiva($user->id, $empresaAtiva->id);
            } catch (DomainException $e) {
                Log::warning('LoginUseCase::resolverEmpresaAtiva - Não foi possível persistir empresa_ativa_id', [
                    'user_id' => $user->id,
                    'empresa_id' => $empresaAtiva->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Validar consistência empresa-tenant (se necessário)
        if ($empresaAtiva) {
            $tenantDaEmpresa = $this->buscarTenantPorEmpresa($empresaAtiva->id);
            
            if ($tenantDaEmpresa && $tenantDaEmpresa->id !== $tenant->id) {
                // Empresa está em tenant diferente - verificar permissão
                $usuarioTemPermissao = $this->verificarPermissaoUsuarioEmpresa(
                    $user->id,
                    $empresaAtiva->id,
                    $tenantDaEmpresa->id
                );
                
                if ($usuarioTemPermissao) {
                    // Usuário tem permissão - usar tenant da empresa
                    Log::info('LoginUseCase::resolverEmpresaAtiva - Usando tenant da empresa', [
                        'tenant_id' => $tenantDaEmpresa->id,
                        'empresa_id' => $empresaAtiva->id,
                    ]);
                    // Nota: O tenant já foi inicializado, mas a empresa está em outro
                    // Isso é tratado no código principal que valida o tenant correto
                } else {
                    Log::warning('LoginUseCase::resolverEmpresaAtiva - Usuário sem permissão na empresa', [
                        'user_id' => $user->id,
                        'empresa_id' => $empresaAtiva->id,
                        'tenant_id_empresa' => $tenantDaEmpresa->id,
                    ]);
                }
            }
        }
        
        return $empresaAtiva;
    }

    /**
     * 🛡️ CAMADA 3: Validar Status de Acesso
     * 
     * Verifica se o usuário, tenant e empresa estão ativos
     */
    private function validarStatusAcesso($user, Tenant $tenant): void
    {
        // Validar se tenant está ativo
        if ($tenant->status !== 'ativa') {
            Log::warning('LoginUseCase::validarStatusAcesso - Tenant inativo', [
                'tenant_id' => $tenant->id,
                'status' => $tenant->status,
            ]);
            throw new CredenciaisInvalidasException();
        }

        // Validar se usuário está ativo (se houver campo de status)
        // Nota: A validação de status do usuário pode ser feita aqui se necessário
        // Por enquanto, assumimos que usuários deletados (soft delete) não são retornados pelo repository
    }

    /**
     * Buscar tenant automaticamente pelo email do usuário
     * Itera por todos os tenants procurando o usuário
     * 
     * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
     */
    private function buscarTenantPorEmail(string $email): ?Tenant
    {
        \Log::debug('LoginUseCase::buscarTenantPorEmail - Iniciando busca', ['email' => $email]);
        
        // Buscar todos os tenants usando repository (Domain, não Eloquent)
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'per_page' => 1000, // Buscar todos
        ]);
        
        \Log::debug('LoginUseCase::buscarTenantPorEmail - Tenants encontrados', [
            'total' => $tenantsPaginator->total(),
        ]);
        
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
                $user = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($email) {
                    // Tentar buscar usuário neste tenant
                    return $this->userRepository->buscarPorEmail($email);
                });
                
                if ($user) {
                    \Log::info('LoginUseCase::buscarTenantPorEmail - Usuário encontrado', [
                        'tenant_id' => $tenantDomain->id,
                        'user_id' => $user->id,
                    ]);
                    // Converter Domain Entity para Model (necessário para tenancy()->initialize())
                    $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    return $tenantModel; // Usuário encontrado neste tenant
                }
            } catch (\Exception $e) {
                // Se houver erro ao acessar o tenant, continuar para o próximo
                \Log::warning("Erro ao buscar usuário no tenant {$tenantDomain->id}: " . $e->getMessage());
                // AdminTenancyRunner já garantiu finalização do tenancy no finally
                continue;
            }
        }
        
        \Log::warning('LoginUseCase::buscarTenantPorEmail - Usuário não encontrado em nenhum tenant', [
            'email' => $email,
        ]);
        return null; // Usuário não encontrado em nenhum tenant
    }

    /**
     * Buscar tenant correto baseado na empresa ativa
     * Itera por todos os tenants procurando a empresa
     * 
     * 🔥 CRÍTICO: Garante que o tenant retornado seja o correto da empresa ativa,
     * não apenas onde o usuário foi encontrado
     * 
     * 🔥 ARQUITETURA LIMPA: Usa AdminTenancyRunner para isolar lógica de tenancy
     */
    private function buscarTenantPorEmpresa(int $empresaId): ?Tenant
    {
        // Buscar todos os tenants usando repository (Domain, não Eloquent)
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros([
            'per_page' => 1000, // Buscar todos
        ]);
        
        foreach ($tenantsPaginator->items() as $tenantDomain) {
            try {
                // 🔥 ARQUITETURA LIMPA: AdminTenancyRunner isola toda lógica de tenancy
                $empresa = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
                    // Tentar buscar empresa neste tenant
                    return \App\Models\Empresa::find($empresaId);
                });
                
                if ($empresa) {
                    // Converter Domain Entity para Model (necessário para tenancy()->initialize())
                    $tenantModel = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                    return $tenantModel; // Empresa encontrada neste tenant
                }
            } catch (\Exception $e) {
                // Se houver erro ao acessar o tenant, continuar para o próximo
                \Log::debug("Erro ao buscar empresa no tenant {$tenantDomain->id}: " . $e->getMessage());
                // AdminTenancyRunner já garantiu finalização do tenancy no finally
                continue;
            }
        }
        
        return null; // Empresa não encontrada em nenhum tenant
    }

    /**
     * Verificar se o usuário existe em um tenant específico
     * 
     * @param int $userId
     * @param int $tenantId
     * @return bool
     */
    private function verificarUsuarioExisteNoTenant(int $userId, int $tenantId): bool
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            if (!$tenantDomain) {
                return false;
            }
            
            $usuarioExiste = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                $user = \App\Modules\Auth\Models\User::find($userId);
                return $user !== null && !$user->trashed();
            });
            
            return $usuarioExiste ?? false;
        } catch (\Exception $e) {
            \Log::warning('LoginUseCase::verificarUsuarioExisteNoTenant - Erro ao verificar', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verificar se o usuário tem permissão para acessar uma empresa específica
     * Verifica através da tabela pivot empresa_user no tenant da empresa
     * 
     * @param int $userId
     * @param int $empresaId
     * @param int $tenantId
     * @return bool
     */
    private function verificarPermissaoUsuarioEmpresa(int $userId, int $empresaId, int $tenantId): bool
    {
        try {
            $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
            if (!$tenantDomain) {
                return false;
            }
            
            $temPermissao = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId, $empresaId) {
                // Verificar se existe registro na tabela pivot empresa_user
                $pivotExiste = \Illuminate\Support\Facades\DB::table('empresa_user')
                    ->where('user_id', $userId)
                    ->where('empresa_id', $empresaId)
                    ->exists();
                
                return $pivotExiste;
            });
            
            return $temPermissao ?? false;
        } catch (\Exception $e) {
            \Log::warning('LoginUseCase::verificarPermissaoUsuarioEmpresa - Erro ao verificar', [
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function resolverEmpresaDaAssinaturaAtiva(int $tenantId): ?int
    {
        try {
            $assinatura = \App\Domain\Assinatura\Queries\AssinaturaQueries::assinaturaAtualPorTenant($tenantId);

            return $assinatura?->empresa_id ? (int) $assinatura->empresa_id : null;
        } catch (\Exception $e) {
            Log::warning('LoginUseCase::resolverEmpresaDaAssinaturaAtiva - Falha ao inferir empresa', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

}

