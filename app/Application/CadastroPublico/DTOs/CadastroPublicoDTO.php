<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\DTOs;

use App\Application\CadastroPublico\DTOs\PagamentoDTO;
use App\Application\CadastroPublico\DTOs\AfiliacaoDTO;
use App\Domain\Exceptions\DomainException;

/**
 * DTO para cadastro público completo
 * 
 * Agrega todos os dados necessários para criar:
 * - Tenant
 * - Empresa
 * - Usuário Admin
 * - Assinatura
 * - Pagamento (opcional)
 */
final class CadastroPublicoDTO
{
    public function __construct(
        // Dados do Plano
        public readonly int $planoId,
        public readonly string $periodo = 'mensal', // 'mensal' ou 'anual'
        
        // Dados da Empresa (Tenant)
        public readonly string $razaoSocial,
        public readonly string $cnpj, // Obrigatório agora
        public readonly ?string $email = null,
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?array $telefones = null,
        public readonly ?string $logo = null,
        
        // Dados do Usuário Administrador
        public readonly string $adminName,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        
        // Dados de Pagamento (opcional - obrigatório se plano não for gratuito)
        public readonly ?PagamentoDTO $pagamento = null,
        
        // Dados de Afiliação (opcional)
        public readonly ?AfiliacaoDTO $afiliacao = null,
        
        // Idempotência (opcional - para evitar duplicações)
        public readonly ?string $idempotencyKey = null,
        
        // Referência de Afiliado (opcional - para rastreamento automático)
        public readonly ?string $referenciaAfiliado = null, // Código do afiliado (?ref=code)
        public readonly ?string $sessionId = null, // ID da sessão do navegador
    ) {}

    public static function fromArray(array $data): self
    {
        // Validar que CNPJ está presente (obrigatório)
        if (empty($data['cnpj'])) {
            throw new DomainException('CNPJ é obrigatório para cadastro de empresa.');
        }

        return new self(
            planoId: (int) $data['plano_id'],
            periodo: $data['periodo'] ?? 'mensal',
            razaoSocial: $data['razao_social'],
            cnpj: $data['cnpj'], // Já validado acima
            email: $data['email'] ?? null,
            endereco: $data['endereco'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            cep: $data['cep'] ?? null,
            telefones: $data['telefones'] ?? null,
            logo: $data['logo'] ?? null,
            adminName: $data['admin_name'],
            adminEmail: $data['admin_email'],
            adminPassword: $data['admin_password'],
            pagamento: isset($data['payment_method']) 
                ? PagamentoDTO::fromArray($data) 
                : null,
            afiliacao: (!empty($data['cupom_codigo']) && !empty($data['afiliado_id']))
                ? AfiliacaoDTO::fromArray($data)
                : null,
            idempotencyKey: $data['idempotency_key'] ?? $data['idempotencyKey'] ?? null,
            referenciaAfiliado: $data['ref'] ?? $data['referencia_afiliado'] ?? $data['referenciaAfiliado'] ?? null,
            sessionId: $data['session_id'] ?? $data['sessionId'] ?? null,
        );
    }
}

