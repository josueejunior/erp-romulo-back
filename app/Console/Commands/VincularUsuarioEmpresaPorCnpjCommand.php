<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\UsersLookup\Entities\UserLookup;
use App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Models\Empresa;
use App\Models\Tenant;
use App\Models\TenantEmpresa;
use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operação de suporte: vincula um usuário do tenant a uma empresa (CNPJ),
 * cria empresa se não existir, mapeia tenant_empresas, users_lookup e assinatura em plano gratuito.
 */
class VincularUsuarioEmpresaPorCnpjCommand extends Command
{
    protected $signature = 'support:vincular-usuario-empresa-cnpj
                            {--tenant-id= : ID do tenant (obrigatório)}
                            {--cnpj= : CNPJ (14 dígitos; padrão 63517911000100 se vazio)}
                            {--razao-social=GETNINJAS : Razão social ao criar empresa}
                            {--nome-fantasia= : Nome fantasia opcional}
                            {--representante= : Representante legal opcional}
                            {--user-email= : Email exato do usuário (prioridade sobre busca)}
                            {--user-search=romulo : Trecho para buscar em email ou name}
                            {--perfil=admin : Perfil na pivot empresa_user}
                            {--sem-assinatura : Não cria assinatura em plano gratuito}
                            {--dry-run : Só exibe o que seria feito}';

    protected $description = 'Vincula usuário a empresa por CNPJ (cria empresa/assinatura/lookup se necessário)';

    public function __construct(
        private readonly CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private readonly PlanoRepositoryInterface $planoRepository,
        private readonly AssinaturaRepositoryInterface $assinaturaRepository,
        private readonly UserLookupRepositoryInterface $userLookupRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = (int) ($this->option('tenant-id') ?: 0);
        if ($tenantId < 1) {
            $this->error('Informe --tenant-id=');

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant {$tenantId} não encontrado.");

            return self::FAILURE;
        }

        $cnpjBruto = (string) ($this->option('cnpj') ?: '63517911000100');
        $cnpjLimpo = preg_replace('/\D/', '', $cnpjBruto) ?? '';
        if (strlen($cnpjLimpo) !== 14) {
            $this->error('CNPJ inválido (esperado 14 dígitos).');

            return self::FAILURE;
        }

        $razaoSocial = trim((string) $this->option('razao-social')) ?: 'GETNINJAS';
        $nomeFantasia = trim((string) ($this->option('nome-fantasia') ?: ''));
        $representante = trim((string) ($this->option('representante') ?: ''));
        $perfil = strtolower(trim((string) $this->option('perfil'))) ?: 'admin';
        $perfilPermitidos = ['admin', 'operacional', 'financeiro', 'consulta'];
        if (! in_array($perfil, $perfilPermitidos, true)) {
            $this->error('Perfil inválido. Use: '.implode(', ', $perfilPermitidos));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $semAssinatura = (bool) $this->option('sem-assinatura');

        $tenantAnterior = tenancy()->tenant;
        try {
            tenancy()->initialize($tenant);
        } catch (Throwable $e) {
            $this->error('Falha ao inicializar tenant: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $userEmailOpt = trim((string) ($this->option('user-email') ?: ''));
            $userSearch = trim((string) ($this->option('user-search') ?: 'romulo'));

            if ($userEmailOpt !== '') {
                $users = User::query()->where('email', $userEmailOpt)->get();
            } else {
                $like = '%'.addcslashes($userSearch, '%_\\').'%';
                $users = User::query()
                    ->where(function ($q) use ($like) {
                        $q->where('email', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    })
                    ->get();
            }

            if ($users->isEmpty()) {
                $this->error('Nenhum usuário encontrado com os critérios informados.');

                return self::FAILURE;
            }

            if ($users->count() > 1 && $userEmailOpt === '') {
                $this->warn('Vários usuários encontrados; use --user-email= para escolher um.');
                $this->table(['id', 'name', 'email'], $users->map(fn (User $u) => [$u->id, $u->name, $u->email])->toArray());

                return self::FAILURE;
            }

            /** @var User $user */
            $user = $users->first();

            $this->info("Usuário: {$user->name} <{$user->email}> (id {$user->id})");

            $empresa = Empresa::query()->where('cnpj', $cnpjLimpo)->first();
            if (! $empresa) {
                $this->info("Empresa com CNPJ {$cnpjLimpo} não existe; será criada com razão social: {$razaoSocial}");
                if ($dryRun) {
                    $this->warn('[dry-run] Não criou empresa.');

                    return self::SUCCESS;
                }
                $empresa = Empresa::query()->create([
                    'razao_social' => $razaoSocial,
                    'nome_fantasia' => $nomeFantasia !== '' ? $nomeFantasia : null,
                    'cnpj' => $cnpjLimpo,
                    'email' => $user->email,
                    'telefone' => '00000000000',
                    'representante_legal' => $representante !== '' ? $representante : null,
                    'status' => 'ativa',
                ]);
            } else {
                $this->info("Empresa existente: id {$empresa->id} — {$empresa->razao_social}");
            }

            if ($dryRun) {
                $this->warn('[dry-run] Parado antes de gravar vínculos.');

                return self::SUCCESS;
            }

            $jaVinculado = DB::table('empresa_user')
                ->where('empresa_id', $empresa->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $jaVinculado) {
                $user->empresas()->attach($empresa->id, ['perfil' => $perfil]);
                $this->info("Vínculo empresa_user criado (perfil {$perfil}).");
            } else {
                $user->empresas()->updateExistingPivot($empresa->id, ['perfil' => $perfil]);
                $this->info("Vínculo empresa_user já existia; perfil atualizado para {$perfil}.");
            }

            $user->empresa_ativa_id = $empresa->id;
            $user->save();
            $this->info("empresa_ativa_id do usuário definido para {$empresa->id}.");

            TenantEmpresa::createOrUpdateMapping($tenantId, (int) $empresa->id);
            $this->info('Mapeamento tenant_empresas atualizado.');

            $lookup = new UserLookup(
                id: null,
                email: $user->email,
                cnpj: $cnpjLimpo,
                tenantId: $tenantId,
                userId: (int) $user->id,
                empresaId: (int) $empresa->id,
                status: 'ativo',
            );
            $this->userLookupRepository->criar($lookup);
            $this->info('users_lookup atualizado (email + tenant).');

            if (! $semAssinatura) {
                $existente = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa((int) $empresa->id, $tenantId);
                if ($existente) {
                    $this->info('Já existe assinatura ativa para esta empresa; não criou nova.');
                } else {
                    $planoGratuito = null;
                    foreach ($this->planoRepository->listar(['ativo' => true]) as $plano) {
                        $pm = $plano->precoMensal ?? 0;
                        if ($pm == 0 || $pm === null) {
                            $planoGratuito = $plano;
                            break;
                        }
                    }
                    if (! $planoGratuito) {
                        $this->warn('Nenhum plano com preço mensal zero encontrado; assinatura não criada.');
                    } else {
                        $inicio = Carbon::now();
                        $fim = $inicio->copy()->addDays(3);
                        $dto = new CriarAssinaturaDTO(
                            userId: (int) $user->id,
                            planoId: (int) $planoGratuito->id,
                            status: 'ativa',
                            dataInicio: $inicio,
                            dataFim: $fim,
                            valorPago: 0.0,
                            metodoPagamento: 'gratuito',
                            transacaoId: null,
                            diasGracePeriod: 0,
                            observacoes: 'Trial suporte — support:vincular-usuario-empresa-cnpj',
                            tenantId: $tenantId,
                            empresaId: (int) $empresa->id,
                        );
                        $this->criarAssinaturaUseCase->executar($dto);
                        $this->info("Assinatura criada (plano gratuito id {$planoGratuito->id}, 3 dias).");
                    }
                }
            } else {
                $this->info('Pulou criação de assinatura (--sem-assinatura).');
            }

            $this->info('Concluído com sucesso.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            report($e);

            return self::FAILURE;
        } finally {
            if ($tenantAnterior) {
                tenancy()->initialize($tenantAnterior);
            } else {
                tenancy()->end();
            }
        }
    }
}
