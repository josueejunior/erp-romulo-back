<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\UseCases;

use App\Application\CadastroPublico\DTOs\CadastroPublicoDTO;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Application\Payment\UseCases\ProcessarAssinaturaPlanoUseCase;
use App\Application\Empresa\UseCases\RegistrarAfiliadoNaEmpresaUseCase;
use App\Application\Afiliado\UseCases\ValidarCupomAfiliadoUseCase;
use App\Domain\Assinatura\Services\AssinaturaDomainService;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Exceptions\EmailJaCadastradoException;
use App\Domain\Exceptions\CnpjJaCadastradoException;
use App\Domain\Payment\ValueObjects\PaymentRequest;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case: Cadastrar Empresa Publicamente
 * 
 * Orquestra todo o fluxo de cadastro público:
 * - Valida duplicidades
 * - Cria tenant e empresa
 * - Cria usuário admin
 * - Processa pagamento (se necessário)
 * - Cria assinatura
 * - Registra afiliado (se aplicável)
 * 
 * Este Use Case centraliza TODA a lógica de orquestração,
 * removendo responsabilidades do Controller.
 */
final class CadastrarEmpresaPublicamenteUseCase
{
    public function __construct(
        private readonly CriarTenantUseCase $criarTenantUseCase,
        private readonly CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private readonly ProcessarAssinaturaPlanoUseCase $processarAssinaturaPlanoUseCase,
        private readonly RegistrarAfiliadoNaEmpresaUseCase $registrarAfiliadoNaEmpresaUseCase,
        private readonly ValidarCupomAfiliadoUseCase $validarCupomAfiliadoUseCase,
        private readonly AssinaturaDomainService $assinaturaDomainService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly PlanoRepositoryInterface $planoRepository,
    ) {}

    /**
     * Executa o cadastro público completo
     * 
     * @return array{
     *   tenant: \App\Models\Tenant,
     *   empresa: \App\Domain\Empresa\Entities\Empresa,
     *   admin_user: \App\Modules\Auth\Models\User,
     *   assinatura: \App\Modules\Assinatura\Models\Assinatura,
     *   plano: \App\Modules\Assinatura\Models\Plano,
     *   data_fim: \Carbon\Carbon,
     *   payment_result?: array
     * }
     */
    public function executar(CadastroPublicoDTO $dto): array
    {
        Log::debug('CadastrarEmpresaPublicamenteUseCase::executar', [
            'plano_id' => $dto->planoId,
            'razao_social' => $dto->razaoSocial,
            'admin_email' => $dto->adminEmail,
        ]);

        // 1. Validar duplicidades (regra de negócio)
        $this->validarDuplicidades($dto);

        // 2. Buscar plano (via repository - não Eloquent direto)
        $plano = $this->planoRepository->buscarModeloPorId($dto->planoId);
        if (!$plano) {
            throw new \DomainException('Plano não encontrado.');
        }

        // 3. Criar tenant com empresa e usuário admin
        $tenantResult = $this->criarTenantEUsuario($dto);

        // 4. Registrar afiliado na empresa (se aplicável)
        if ($dto->afiliacao) {
            $this->registrarAfiliado($tenantResult['empresa'], $dto->afiliacao);
        }

        // 5. Processar pagamento e criar assinatura
        $assinaturaResult = $this->processarPagamentoECriarAssinatura(
            $tenantResult,
            $plano,
            $dto
        );

        return [
            'tenant' => $tenantResult['tenant'],
            'empresa' => $tenantResult['empresa'],
            'admin_user' => $tenantResult['admin_user'],
            'assinatura' => $assinaturaResult['assinatura'],
            'plano' => $assinaturaResult['plano'],
            'data_fim' => $assinaturaResult['data_fim'],
            'payment_result' => $assinaturaResult['payment_result'] ?? null,
        ];
    }

    /**
     * Valida duplicidades de email e CNPJ
     * 
     * @throws EmailJaCadastradoException
     * @throws CnpjJaCadastradoException
     */
    private function validarDuplicidades(CadastroPublicoDTO $dto): void
    {
        // Validar email
        if ($this->userRepository->emailExiste($dto->adminEmail)) {
            Log::info('Tentativa de cadastro com email já existente', [
                'email' => $dto->adminEmail,
            ]);
            
            throw new EmailJaCadastradoException($dto->adminEmail);
        }

        // Validar CNPJ (se informado)
        if ($dto->cnpj) {
            $cnpjLimpo = preg_replace('/\D/', '', $dto->cnpj);
            $tenantExistente = $this->tenantRepository->buscarPorCnpj($dto->cnpj);
            
            if (!$tenantExistente) {
                $tenantExistente = $this->tenantRepository->buscarPorCnpj($cnpjLimpo);
            }
            
            if ($tenantExistente) {
                Log::info('Tentativa de cadastro com CNPJ já existente', [
                    'cnpj' => $dto->cnpj,
                ]);
                
                throw new CnpjJaCadastradoException($dto->cnpj);
            }
        }
    }

    /**
     * Cria tenant com empresa e usuário admin
     */
    private function criarTenantEUsuario(CadastroPublicoDTO $dto): array
    {
        // Converter DTO para CriarTenantDTO
        $tenantDTO = CriarTenantDTO::fromArray([
            'razao_social' => $dto->razaoSocial,
            'cnpj' => $dto->cnpj,
            'email' => $dto->email,
            'endereco' => $dto->endereco,
            'cidade' => $dto->cidade,
            'estado' => $dto->estado,
            'cep' => $dto->cep,
            'telefones' => $dto->telefones,
            'logo' => $dto->logo,
            'status' => 'ativa',
            'admin_name' => $dto->adminName,
            'admin_email' => $dto->adminEmail,
            'admin_password' => $dto->adminPassword,
        ]);

        return $this->criarTenantUseCase->executar($tenantDTO, requireAdmin: true);
    }

    /**
     * Registra afiliado na empresa
     */
    private function registrarAfiliado($empresa, $afiliacao): void
    {
        try {
            $this->registrarAfiliadoNaEmpresaUseCase->executar(
                empresaId: $empresa->id,
                afiliadoId: $afiliacao->afiliadoId,
                codigo: $afiliacao->codigo,
                descontoAplicado: $afiliacao->descontoAplicado
            );
        } catch (\Exception $e) {
            Log::error('Erro ao registrar afiliado na empresa durante cadastro público', [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id ?? null,
                'afiliado_id' => $afiliacao->afiliadoId ?? null,
            ]);
            // Não lança exceção - apenas loga para não bloquear o cadastro
        }
    }

    /**
     * Processa pagamento e cria assinatura
     */
    private function processarPagamentoECriarAssinatura(
        array $tenantResult,
        $plano,
        CadastroPublicoDTO $dto
    ): array {
        $isPlanoGratuito = $this->assinaturaDomainService->isPlanoGratuito($plano, $dto->periodo);

        // Se for plano gratuito, criar assinatura diretamente
        if ($isPlanoGratuito) {
            return $this->criarAssinaturaGratuita(
                $tenantResult['admin_user'],
                $tenantResult['tenant'],
                $plano,
                $dto
            );
        }

        // Se não houver dados de pagamento, criar assinatura pendente
        if (!$dto->pagamento) {
            return $this->criarAssinaturaPendente(
                $tenantResult['admin_user'],
                $tenantResult['tenant'],
                $plano,
                $dto
            );
        }

        // Processar pagamento
        return $this->processarPagamento(
            $tenantResult['tenant'],
            $plano,
            $dto
        );
    }

    /**
     * Cria assinatura gratuita
     */
    private function criarAssinaturaGratuita($adminUser, $tenant, $plano, CadastroPublicoDTO $dto): array
    {
        $dataInicio = Carbon::now();
        $dataFim = $this->assinaturaDomainService->calcularDataFim($plano, $dto->periodo, $dataInicio);
        $diasGracePeriod = $this->assinaturaDomainService->calcularDiasGracePeriod($plano);
        $metodoPagamento = $this->assinaturaDomainService->determinarMetodoPagamento($plano);

        $assinaturaDTO = new CriarAssinaturaDTO(
            userId: $adminUser->id,
            planoId: $plano->id,
            status: 'ativa',
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: 0.0,
            metodoPagamento: $metodoPagamento,
            transacaoId: null,
            diasGracePeriod: $diasGracePeriod,
            observacoes: 'Plano gratuito - teste de 3 dias',
            tenantId: $tenant->id,
        );

        $assinatura = $this->criarAssinaturaUseCase->executar($assinaturaDTO);

        return [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];
    }

    /**
     * Cria assinatura pendente (sem pagamento processado)
     */
    private function criarAssinaturaPendente($adminUser, $tenant, $plano, CadastroPublicoDTO $dto): array
    {
        $dataInicio = Carbon::now();
        $dataFim = $this->assinaturaDomainService->calcularDataFim($plano, $dto->periodo, $dataInicio);
        $valorOriginal = $this->assinaturaDomainService->calcularValor($plano, $dto->periodo);
        $diasGracePeriod = $this->assinaturaDomainService->calcularDiasGracePeriod($plano);

        // Aplicar desconto de afiliado se houver
        $valorPago = $valorOriginal;
        $observacoes = 'Cadastro público - pagamento pendente';

        if ($dto->afiliacao && $valorOriginal > 0) {
            try {
                $cupomInfo = $this->validarCupomAfiliadoUseCase->calcularDesconto(
                    $dto->afiliacao->codigo,
                    $valorOriginal
                );

                if ($cupomInfo['valido']) {
                    $valorPago = $cupomInfo['valor_final'];
                    $observacoes .= sprintf(
                        ' | Cupom %s aplicado: %s%% de desconto | Afiliado ID: %d',
                        $cupomInfo['codigo'],
                        $cupomInfo['percentual_desconto'],
                        $cupomInfo['afiliado_id']
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao aplicar cupom no cadastro público', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $assinaturaDTO = new CriarAssinaturaDTO(
            userId: $adminUser->id,
            planoId: $plano->id,
            status: 'pendente',
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: $valorPago,
            metodoPagamento: 'pendente',
            transacaoId: null,
            diasGracePeriod: $diasGracePeriod,
            observacoes: $observacoes,
            tenantId: $tenant->id,
        );

        $assinatura = $this->criarAssinaturaUseCase->executar($assinaturaDTO);

        return [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];
    }

    /**
     * Processa pagamento e cria assinatura
     */
    private function processarPagamento($tenant, $plano, CadastroPublicoDTO $dto): array
    {
        $valorOriginal = $this->assinaturaDomainService->calcularValor($plano, $dto->periodo);
        
        // Aplicar desconto de afiliado se houver
        $valorFinal = $valorOriginal;
        if ($dto->afiliacao && $valorOriginal > 0) {
            try {
                $cupomInfo = $this->validarCupomAfiliadoUseCase->calcularDesconto(
                    $dto->afiliacao->codigo,
                    $valorOriginal
                );
                if ($cupomInfo['valido']) {
                    $valorFinal = $cupomInfo['valor_final'];
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao aplicar cupom no pagamento do cadastro público', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Criar PaymentRequest
        $paymentRequestData = [
            'amount' => $valorFinal,
            'description' => "Plano {$plano->nome} - {$dto->periodo} - Sistema Rômulo",
            'payer_email' => $dto->pagamento->payerEmail,
            'payer_cpf' => $dto->pagamento->payerCpf,
            'payment_method_id' => $dto->pagamento->isPix() ? 'pix' : null,
            'external_reference' => "tenant_{$tenant->id}_plano_{$plano->id}_cadastro",
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plano_id' => $plano->id,
                'periodo' => $dto->periodo,
                'cadastro_publico' => true,
            ],
        ];

        // Para cartão, adicionar token e parcelas
        if ($dto->pagamento->isCreditCard()) {
            $paymentRequestData['card_token'] = $dto->pagamento->cardToken;
            $paymentRequestData['installments'] = $dto->pagamento->installments;
            unset($paymentRequestData['payment_method_id']);
        }

        $paymentRequest = PaymentRequest::fromArray($paymentRequestData);

        // Processar pagamento usando o Use Case
        $assinatura = $this->processarAssinaturaPlanoUseCase->executar(
            $tenant,
            $plano,
            $paymentRequest,
            $dto->periodo
        );

        $dataFim = Carbon::parse($assinatura->data_fim);

        $result = [
            'assinatura' => $assinatura,
            'plano' => $plano,
            'data_fim' => $dataFim,
        ];

        // Se for PIX pendente, incluir dados do QR Code
        // TODO: Refatorar ProcessarAssinaturaPlanoUseCase para retornar PaymentResultDTO
        // com dados do QR Code, evitando vazamento de infraestrutura (PaymentLog)
        if ($assinatura->status === 'pendente' && $assinatura->metodo_pagamento === 'pix') {
            // Buscar dados do pagamento via PaymentLog (infraestrutura)
            // Nota: Em DDD ideal, isso viria de um PaymentResultDTO retornado pelo
            // ProcessarAssinaturaPlanoUseCase, mas por enquanto mantemos compatibilidade
            // com a estrutura existente. Isso deveria ser abstraído via PaymentRepository.
            $paymentLog = \App\Models\PaymentLog::where('tenant_id', $tenant->id)
                ->where('plano_id', $plano->id)
                ->latest()
                ->first();
            
            if ($paymentLog && isset($paymentLog->dados_resposta['pix_qr_code'])) {
                $result['payment_result'] = [
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'pix_qr_code' => $paymentLog->dados_resposta['pix_qr_code'],
                    'pix_qr_code_base64' => $paymentLog->dados_resposta['pix_qr_code_base64'] ?? null,
                    'pix_ticket_url' => $paymentLog->dados_resposta['pix_ticket_url'] ?? null,
                ];
            }
        }

        return $result;
    }
}

