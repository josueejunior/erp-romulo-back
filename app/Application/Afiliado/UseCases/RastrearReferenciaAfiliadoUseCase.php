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
     * 🔥 REGRA LAST CLICK: Se já existe referência para esta sessão/email, 
     * a nova referência sobrescreve a anterior (Last Click wins)
     * 
     * 🔥 TTL: Referência expira em 90 dias (configurável via env)
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

        // 🔥 REGRA LAST CLICK: Buscar referência existente (mesmo que expirada)
        // Se encontrar, vamos atualizar para o novo afiliado (Last Click sobrescreve)
        $query = AfiliadoReferencia::where('cadastro_concluido', false);
        
        if ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($email) {
            $query->where('email', $email);
        }

        $existente = $query->orderBy('created_at', 'desc')->first();

        // TTL padrão: 90 dias (configurável via env)
        $ttlDias = (int) env('AFILIADO_REFERENCIA_TTL_DAYS', 90);
        $expiraEm = now()->addDays($ttlDias);

        if ($existente) {
            // 🔥 LAST CLICK: Atualizar referência existente para o novo afiliado
            Log::info('RastrearReferenciaAfiliadoUseCase - Last Click: Atualizando referência existente', [
                'referencia_id' => $existente->id,
                'afiliado_anterior' => $existente->afiliado_id,
                'afiliado_novo' => $afiliado->id,
            ]);

            $existente->update([
                'afiliado_id' => $afiliado->id,
                'referencia_code' => strtoupper(trim($referenciaCode)),
                'ip_address' => $ipAddress ?? $existente->ip_address,
                'user_agent' => $userAgent ?? $existente->user_agent,
                'email' => $email ?? $existente->email,
                'primeiro_acesso' => $existente->primeiro_acesso ?? now(),
                'expira_em' => $expiraEm,
                'atribuicao_valida' => true, // Resetar validade
                'metadata' => $metadata ?? $existente->metadata,
                'registrado_como_clique' => true, // Marcar como novo clique
            ]);

            return $existente->fresh();
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
            'expira_em' => $expiraEm,
            'atribuicao_valida' => true,
            'registrado_como_clique' => true, // Funil: Clique
            'registrado_como_lead' => false,
            'registrado_como_venda' => false,
            'metadata' => $metadata,
        ]);

        Log::info('RastrearReferenciaAfiliadoUseCase - Referência criada', [
            'referencia_id' => $referencia->id,
            'afiliado_id' => $afiliado->id,
            'referencia_code' => $referenciaCode,
            'expira_em' => $expiraEm->format('Y-m-d H:i:s'),
            'ttl_dias' => $ttlDias,
        ]);

        return $referencia;
    }

    /**
     * Busca referência ativa por sessão ou email
     * 
     * 🔥 Valida TTL: Só retorna referências que não expiraram
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

        $query = AfiliadoReferencia::where('cadastro_concluido', false)
            ->where('atribuicao_valida', true)
            ->where(function ($q) {
                $q->whereNull('expira_em')
                  ->orWhere('expira_em', '>', now());
            });

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        if ($email) {
            $query->orWhere('email', $email);
        }

        $referencia = $query->orderBy('created_at', 'desc')->first();

        // Se encontrou mas expirou, marcar como inválida
        if ($referencia && $referencia->expira_em && $referencia->expira_em <= now()) {
            $referencia->update(['atribuicao_valida' => false]);
            Log::info('RastrearReferenciaAfiliadoUseCase - Referência expirada', [
                'referencia_id' => $referencia->id,
                'expira_em' => $referencia->expira_em,
            ]);
            return null;
        }

        return $referencia;
    }

    /**
     * Marca referência como lead (quando cadastro gratuito/trial é iniciado)
     * 
     * @param int $referenciaId
     * @return void
     */
    public function marcarComoLead(int $referenciaId): void
    {
        $referencia = AfiliadoReferencia::find($referenciaId);
        
        if (!$referencia) {
            return;
        }

        if (!$referencia->registrado_como_lead) {
            $referencia->update([
                'registrado_como_lead' => true,
                'lead_registrado_em' => now(),
            ]);

            Log::info('RastrearReferenciaAfiliadoUseCase - Referência marcada como lead', [
                'referencia_id' => $referenciaId,
                'afiliado_id' => $referencia->afiliado_id,
            ]);
        }
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
            'registrado_como_lead' => true, // Se chegou aqui, é lead
            'lead_registrado_em' => $referencia->lead_registrado_em ?? now(),
        ]);

        Log::info('RastrearReferenciaAfiliadoUseCase - Referência marcada como concluída', [
            'referencia_id' => $referenciaId,
            'tenant_id' => $tenantId,
            'afiliado_id' => $referencia->afiliado_id,
        ]);
    }

    /**
     * Marca referência como venda (quando pagamento é confirmado)
     * 
     * @param int $referenciaId
     * @return void
     */
    public function marcarComoVenda(int $referenciaId): void
    {
        $referencia = AfiliadoReferencia::find($referenciaId);
        
        if (!$referencia) {
            return;
        }

        if (!$referencia->registrado_como_venda) {
            $referencia->update([
                'registrado_como_venda' => true,
                'venda_registrada_em' => now(),
            ]);

            Log::info('RastrearReferenciaAfiliadoUseCase - Referência marcada como venda', [
                'referencia_id' => $referenciaId,
                'afiliado_id' => $referencia->afiliado_id,
            ]);
        }
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





