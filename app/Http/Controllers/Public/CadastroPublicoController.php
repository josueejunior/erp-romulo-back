<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Controller para cadastro público (sem autenticação)
 * Permite criar tenant, assinatura e usuário em uma única operação
 */
class CadastroPublicoController extends Controller
{
    public function __construct(
        private CriarTenantUseCase $criarTenantUseCase,
        private CriarAssinaturaUseCase $criarAssinaturaUseCase,
    ) {}

    /**
     * Criar cadastro completo: tenant + assinatura + usuário
     */
    public function store(Request $request)
    {
        try {
            // Validar dados
            $validated = $request->validate([
                // Dados do plano
                'plano_id' => 'required|exists:planos,id',
                'periodo' => 'nullable|string|in:mensal,anual',
                
                // Dados da empresa (tenant)
                'razao_social' => 'required|string|max:255',
                'cnpj' => 'nullable|string|max:18|unique:tenants,cnpj',
                'email' => 'nullable|email|max:255',
                'endereco' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'cep' => 'nullable|string|max:10',
                'telefones' => 'nullable|array',
                
                // Dados do usuário administrador
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255',
                'admin_password' => 'required|string|min:8',
            ]);

            // 1. Criar tenant com empresa e usuário admin
            $tenantDTO = CriarTenantDTO::fromArray($validated);
            $tenantResult = $this->criarTenantUseCase->executar($tenantDTO, requireAdmin: true);
            
            $tenant = $tenantResult['tenant'];
            $empresa = $tenantResult['empresa'];
            $adminUser = $tenantResult['admin_user'];

            // 2. Criar assinatura com o plano selecionado
            $periodo = $validated['periodo'] ?? 'mensal';
            $plano = \App\Modules\Assinatura\Models\Plano::find($validated['plano_id']);
            
            if (!$plano) {
                throw new DomainException('Plano não encontrado.');
            }

            // Calcular data de término baseado no período
            $dataInicio = Carbon::now();
            $dataFim = $periodo === 'anual' 
                ? $dataInicio->copy()->addYear() 
                : $dataInicio->copy()->addMonth();
            
            $valorPago = $periodo === 'anual' && $plano->preco_anual 
                ? $plano->preco_anual 
                : $plano->preco_mensal;

            // Para cadastro público, criar assinatura como 'ativa' mas com observação de pagamento pendente
            // O pagamento pode ser processado posteriormente
            $assinaturaDTO = new CriarAssinaturaDTO(
                tenantId: $tenant->id,
                planoId: $plano->id,
                status: 'ativa', // Ativa para permitir uso imediato
                dataInicio: $dataInicio,
                dataFim: $dataFim,
                valorPago: 0, // Será atualizado quando o pagamento for processado
                metodoPagamento: 'gratuito', // Será atualizado quando o pagamento for processado
                transacaoId: null,
                diasGracePeriod: 7,
                observacoes: 'Cadastro público - pagamento pendente',
            );

            $assinatura = $this->criarAssinaturaUseCase->executar($assinaturaDTO);

            return response()->json([
                'message' => 'Cadastro realizado com sucesso!',
                'success' => true,
                'data' => [
                    'tenant' => [
                        'id' => $tenant->id,
                        'razao_social' => $tenant->razaoSocial,
                        'cnpj' => $tenant->cnpj,
                        'email' => $tenant->email,
                    ],
                    'empresa' => [
                        'id' => $empresa->id,
                        'razao_social' => $empresa->razaoSocial,
                    ],
                    'usuario' => [
                        'id' => $adminUser->id,
                        'name' => $adminUser->nome,
                        'email' => $adminUser->email,
                    ],
                    'assinatura' => [
                        'id' => $assinatura->id,
                        'plano' => [
                            'id' => $plano->id,
                            'nome' => $plano->nome,
                        ],
                        'data_fim' => $dataFim->format('Y-m-d'),
                    ],
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos. Verifique os campos preenchidos.',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erro ao realizar cadastro público', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar o cadastro. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'success' => false,
            ], 500);
        }
    }
}

