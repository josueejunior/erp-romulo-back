<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para adicionar headers de segurança em todas as respostas
 * 
 * Headers implementados:
 * - X-Content-Type-Options: Previne MIME type sniffing
 * - X-Frame-Options: Previne clickjacking
 * - X-XSS-Protection: Proteção XSS legacy (para browsers antigos)
 * - Strict-Transport-Security: Força HTTPS (HSTS)
 * - Referrer-Policy: Controla informação do Referrer
 * - Permissions-Policy: Controla features do browser
 * - Content-Security-Policy: Controla fontes de conteúdo
 */
class SecurityHeaders
{
    /**
     * Headers de segurança padrão
     */
    protected array $headers = [
        // Previne MIME type sniffing - browser não tenta "adivinhar" o tipo
        'X-Content-Type-Options' => 'nosniff',
        
        // Previne clickjacking - página não pode ser carregada em iframe
        'X-Frame-Options' => 'DENY',
        
        // Proteção XSS para browsers antigos (Chrome, IE)
        'X-XSS-Protection' => '1; mode=block',
        
        // Controla informação enviada no header Referrer
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        
        // Remove header que expõe versão do servidor
        'X-Powered-By' => '',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Não interferir com requisições OPTIONS (preflight CORS)
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }
        
        $response = $next($request);
        
        // Se não for uma Response válida, retornar sem modificar
        if (!$response instanceof Response) {
            return $response;
        }
        
        // Adicionar headers básicos de segurança
        foreach ($this->headers as $header => $value) {
            if ($value === '') {
                $response->headers->remove($header);
            } else {
                $response->headers->set($header, $value);
            }
        }
        
        // HSTS - Strict Transport Security (apenas em produção com HTTPS)
        if ($this->shouldEnableHsts($request)) {
            // max-age=31536000 = 1 ano
            // includeSubDomains = aplica a subdomínios
            // preload = permite inclusão na lista de preload dos browsers
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        // Content-Security-Policy (CSP) - apenas para respostas HTML
        if ($this->shouldAddCsp($response)) {
            $response->headers->set('Content-Security-Policy', $this->buildCsp());
        }
        
        // Permissions-Policy (antigo Feature-Policy)
        $response->headers->set('Permissions-Policy', $this->buildPermissionsPolicy());
        
        return $response;
    }
    
    /**
     * Verificar se HSTS deve ser habilitado
     */
    protected function shouldEnableHsts(Request $request): bool
    {
        // Habilitar apenas em produção e quando via HTTPS
        return app()->environment('production') 
            && ($request->secure() || $request->header('X-Forwarded-Proto') === 'https');
    }
    
    /**
     * Verificar se deve adicionar CSP (apenas para HTML)
     */
    protected function shouldAddCsp(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'text/html');
    }
    
    /**
     * Construir Content-Security-Policy
     * 
     * Nota: CSP muito restritivo pode quebrar funcionalidades.
     * Ajustar conforme necessidade do projeto.
     */
    protected function buildCsp(): string
    {
        $directives = [
            // Scripts: apenas do próprio domínio
            "default-src 'self'",
            
            // Scripts: próprio domínio + inline necessário para alguns frameworks
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            
            // Estilos: próprio domínio + inline (comum em SPA)
            "style-src 'self' 'unsafe-inline'",
            
            // Imagens: próprio domínio + data URIs + HTTPS
            "img-src 'self' data: https:",
            
            // Fontes: próprio domínio + Google Fonts
            "font-src 'self' https://fonts.gstatic.com",
            
            // Conexões: próprio domínio (API calls)
            "connect-src 'self' https://api.addireta.com wss:",
            
            // Frames: nenhum (prevenção clickjacking)
            "frame-ancestors 'none'",
            
            // Forms: apenas próprio domínio
            "form-action 'self'",
            
            // Base URI: apenas próprio domínio
            "base-uri 'self'",
        ];
        
        return implode('; ', $directives);
    }
    
    /**
     * Construir Permissions-Policy
     * 
     * Desabilita features do browser que não são necessárias
     */
    protected function buildPermissionsPolicy(): string
    {
        $policies = [
            'accelerometer' => '()',
            'camera' => '()',
            'geolocation' => '()',
            'gyroscope' => '()',
            'magnetometer' => '()',
            'microphone' => '()',
            'payment' => '()',
            'usb' => '()',
        ];
        
        $parts = [];
        foreach ($policies as $feature => $value) {
            $parts[] = "{$feature}={$value}";
        }
        
        return implode(', ', $parts);
    }
}
