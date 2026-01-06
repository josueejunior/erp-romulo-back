<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Log;

/**
 * 🔥 Serviço JWT Stateless
 * 
 * Gera e valida tokens JWT sem estado, sem sessão, sem Redis.
 * Perfeito para escalabilidade horizontal.
 */
class JWTService
{
    private string $secret;
    private string $issuer;
    private int $expiration;

    public function __construct()
    {
        $this->secret = config('jwt.secret', env('JWT_SECRET', config('app.key')));
        $this->issuer = config('jwt.issuer', env('JWT_ISSUER', config('app.url')));
        $this->expiration = config('jwt.expiration', env('JWT_EXPIRATION', 3600)); // 1 hora padrão
    }

    /**
     * Gerar token JWT
     * 
     * @param array $payload Dados do usuário (user_id, tenant_id, role, etc.)
     * @return string Token JWT
     */
    public function generateToken(array $payload): string
    {
        $now = time();
        
        $jwtPayload = [
            'iss' => $this->issuer, // Issuer
            'sub' => $payload['user_id'] ?? null, // Subject (user_id)
            'iat' => $now, // Issued at
            'exp' => $now + $this->expiration, // Expiration
            'nbf' => $now, // Not before
        ];

        // Adicionar dados customizados
        if (isset($payload['tenant_id'])) {
            $jwtPayload['tenant_id'] = $payload['tenant_id'];
        }
        
        if (isset($payload['empresa_id'])) {
            $jwtPayload['empresa_id'] = $payload['empresa_id'];
        }
        
        if (isset($payload['role'])) {
            $jwtPayload['role'] = $payload['role'];
        }
        
        if (isset($payload['is_admin'])) {
            $jwtPayload['is_admin'] = $payload['is_admin'];
        }

        // Adicionar outros campos do payload
        foreach ($payload as $key => $value) {
            if (!in_array($key, ['user_id', 'tenant_id', 'empresa_id', 'role', 'is_admin'])) {
                $jwtPayload[$key] = $value;
            }
        }

        try {
            $token = JWT::encode($jwtPayload, $this->secret, 'HS256');
            
            Log::debug('JWTService::generateToken - Token gerado', [
                'user_id' => $payload['user_id'] ?? null,
                'tenant_id' => $payload['tenant_id'] ?? null,
                'expires_in' => $this->expiration,
            ]);
            
            return $token;
        } catch (\Exception $e) {
            Log::error('JWTService::generateToken - Erro ao gerar token', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validar e decodificar token JWT
     * 
     * @param string $token Token JWT
     * @return array Payload decodificado
     * @throws \Exception Se o token for inválido
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            
            // Converter objeto para array
            $payload = (array) $decoded;
            
            Log::debug('JWTService::validateToken - Token válido', [
                'user_id' => $payload['sub'] ?? null,
                'tenant_id' => $payload['tenant_id'] ?? null,
            ]);
            
            return $payload;
        } catch (ExpiredException $e) {
            Log::warning('JWTService::validateToken - Token expirado');
            throw new \Exception('Token expirado', 401);
        } catch (SignatureInvalidException $e) {
            Log::warning('JWTService::validateToken - Assinatura inválida');
            throw new \Exception('Token inválido', 401);
        } catch (\Exception $e) {
            Log::error('JWTService::validateToken - Erro ao validar token', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Token inválido', 401);
        }
    }

    /**
     * Obter payload do token sem validar (útil para debug)
     * 
     * @param string $token Token JWT
     * @return array Payload (sem validação)
     */
    public function getPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('Token inválido', 401);
        }
        
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        
        if (!$payload) {
            throw new \Exception('Token inválido', 401);
        }
        
        return $payload;
    }
}

