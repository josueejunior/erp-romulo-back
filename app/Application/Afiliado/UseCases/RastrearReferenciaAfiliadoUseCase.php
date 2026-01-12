<?php

declare(strict_types=1);

namespace App\Application\Afiliado\UseCases;

use App\Modules\Afiliado\Models\Afiliado;
use App\Models\AfiliadoReferencia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Use Case: Rastrear Referência de Afiliado
 * 
 * Registra quando um lead acessa o site através de um link de afiliado
 */
final class RastrearReferenciaAfiliadoUseCase
{
    /**
     * Rastreia uma referência de afiliado
     * 
     * @param string $referenciaCode Código do afiliado (ex: "seunome" de ?ref=seunome)
     * @param string|null $sessionId ID da sessão do navegador
     * @param string|null $ipAddress IP do usuário
     * @param string|null $userAgent User agent do navegador
     * @param string|null $email Email do lead (quando disponível)
     * @param array|null $metadata Metadados adicionais (UTM, origem, etc)
     * @return AfiliadoReferencia|null Retorna a referência criada ou null se afiliado não encontrado
     */
    public function executar(
        string $referenciaCode,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $email = null,
        ?array $metadata = null
    ): ?AfiliadoReferencia {
        Log::debug('RastrearReferenciaAfiliadoUseCase::executar', [
            'referencia_code' => $referenciaCode,
            'session_id' => $sessionId,
            'email' => $email,
        ]);

        // Buscar afiliado pelo código
        $afiliado = Afiliado::where('codigo', strtoupper(trim($referenciaCode)))
            ->where('ativo', true)
            ->first();

        if (!$afiliado) {
            Log::warning('RastrearReferenciaAfiliadoUseCase - Afiliado não encontrado ou inativo', [
                'referencia_code' => $referenciaCode,
            ]);
            return null;
        }

        // Verificar se já existe referência para esta sessão/email
        $query = AfiliadoReferencia::where('afiliado_id', $afiliado->id);
        
        if ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($email) {
            $query->where('email', $email);
        } else {
            // Se não tem sessão nem email, criar nova sempre
        }

        $existente = $query->where('cadastro_concluido', false)->first();

        if ($existente) {
            Log::debug('RastrearReferenciaAfiliadoUseCase - Referência já existe', [
                'referencia_id' => $existente->id,
            ]);
            return $existente;
        }

        // Criar nova referência
        $referencia = AfiliadoReferencia::create([
            'afiliado_id' => $afiliado->id,
            'referencia_code' => strtoupper(trim($referenciaCode)),
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'email' => $email,
            'primeiro_acesso' => now(),
            'metadata' => $metadata,
        ]);

        Log::info('RastrearReferenciaAfiliadoUseCase - Referência criada', [
            'referencia_id' => $referencia->id,
            'afiliado_id' => $afiliado->id,
            'referencia_code' => $referenciaCode,
        ]);

        return $referencia;
    }

    /**
     * Busca referência ativa por sessão ou email
     * 
     * @param string|null $sessionId
     * @param string|null $email
     * @return AfiliadoReferencia|null
     */
    public function buscarReferenciaAtiva(?string $sessionId = null, ?string $email = null): ?AfiliadoReferencia
    {
        if (!$sessionId && !$email) {
            return null;
        }

        $query = AfiliadoReferencia::where('cadastro_concluido', false);

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        if ($email) {
            $query->orWhere('email', $email);
        }

        return $query->orderBy('created_at', 'desc')->first();
    }

    /**
     * Marca referência como concluída (quando cadastro é finalizado)
     * 
     * @param int $referenciaId
     * @param int $tenantId
     * @param string|null $cnpj
     * @return void
     */
    public function marcarComoConcluida(int $referenciaId, int $tenantId, ?string $cnpj = null): void
    {
        $referencia = AfiliadoReferencia::find($referenciaId);
        
        if (!$referencia) {
            Log::warning('RastrearReferenciaAfiliadoUseCase - Referência não encontrada para marcar como concluída', [
                'referencia_id' => $referenciaId,
            ]);
            return;
        }

        $referencia->update([
            'tenant_id' => $tenantId,
            'cnpj' => $cnpj,
            'cadastro_concluido' => true,
            'cadastro_concluido_em' => now(),
        ]);

        Log::info('RastrearReferenciaAfiliadoUseCase - Referência marcada como concluída', [
            'referencia_id' => $referenciaId,
            'tenant_id' => $tenantId,
            'afiliado_id' => $referencia->afiliado_id,
        ]);
    }

    /**
     * Verifica se um CNPJ já usou cupom de algum afiliado
     * 
     * @param string $cnpj
     * @return bool
     */
    public function cnpjJaUsouCupom(string $cnpj): bool
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        
        return AfiliadoReferencia::where('cnpj', $cnpj)
            ->orWhere('cnpj', $cnpjLimpo)
            ->where('cupom_aplicado', true)
            ->exists();
    }
}



