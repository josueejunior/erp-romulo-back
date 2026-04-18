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
     */
    public function generateToken(array $payload): string
    {
        $now = time();
        
        $jwtPayload = [
            'iss' => $this->issuer,
            'sub' => $payload['user_id'] ?? null,
            'iat' => $now,
            'exp' => $now + $this->expiration,
            'nbf' => $now,
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

        return JWT::encode($jwtPayload, $this->secret, 'HS256');
    }

    /**
     * Validar e decodificar token JWT
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            throw new \Exception('Token expirado', 401);
        } catch (SignatureInvalidException $e) {
            throw new \Exception('Token inválido', 401);
        } catch (\Exception $e) {
            throw new \Exception('Token inválido', 401);
        }
    }

    /**
     * Obter payload do token sem validar (útil para debug)
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
