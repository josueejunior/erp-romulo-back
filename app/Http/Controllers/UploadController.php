<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller para upload de arquivos (imagens, documentos, etc.)
 */
class UploadController extends Controller
{
    /**
     * Upload de imagem (foto de perfil, logo, etc.)
     */
    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB
            ]);

            $file = $request->file('image');
            
            // ✅ SEGURANÇA: Validar tipo MIME real (não apenas extensão)
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $realMimeType = mime_content_type($file->getRealPath());
            
            if (!in_array($realMimeType, $allowedMimes)) {
                Log::warning('Tentativa de upload com tipo MIME inválido', [
                    'real_mime' => $realMimeType,
                    'reported_mime' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de arquivo não permitido.',
                ], 422);
            }
            
            // ✅ SEGURANÇA: Validar assinatura de imagem (magic bytes)
            $fileContent = file_get_contents($file->getRealPath());
            $validSignatures = [
                "\xFF\xD8\xFF", // JPEG
                "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", // PNG
                "\x47\x49\x46\x38", // GIF
                "RIFF", // WEBP (verifica se contém RIFF)
            ];
            
            $isValidSignature = false;
            foreach ($validSignatures as $signature) {
                if (strpos($fileContent, $signature) === 0 || 
                    ($signature === "RIFF" && strpos($fileContent, "RIFF") === 0 && strpos($fileContent, "WEBP") !== false)) {
                    $isValidSignature = true;
                    break;
                }
            }
            
            if (!$isValidSignature) {
                Log::warning('Tentativa de upload com assinatura de arquivo inválida', [
                    'mime_type' => $realMimeType,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Arquivo de imagem inválido ou corrompido.',
                ], 422);
            }
            
            // ✅ SEGURANÇA: Gerar nome único para evitar sobrescrita
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // ✅ SEGURANÇA: Sanitizar nome do arquivo (remover caracteres especiais)
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            
            // ✅ SEGURANÇA: Isolar por tenant (se aplicável)
            $directory = 'uploads/images';
            if (auth()->check() && function_exists('tenant')) {
                $tenantId = tenant('id') ?? 'global';
                $directory = "uploads/{$tenantId}/images";
            }
            
            // Salvar no storage público
            $path = $file->storeAs($directory, $filename, 'public');
            
            // Retornar URL pública
            $url = Storage::url($path);
            
            // Se estiver usando URL absoluta
            $fullUrl = url($url);

            return response()->json([
                'success' => true,
                'url' => $fullUrl,
                'path' => $path,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação: ' . implode(', ', $e->errors()['image'] ?? []),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao fazer upload de imagem', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Trace desabilitado',
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer upload da imagem. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Deletar imagem
     */
    public function deleteImage(Request $request)
    {
        try {
            $request->validate([
                'path' => 'required|string',
            ]);

            $path = $request->input('path');
            
            // Remover 'uploads/' do início se existir
            if (strpos($path, 'uploads/') === 0) {
                $path = substr($path, 8);
            }
            
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => true,
                'message' => 'Imagem deletada com sucesso',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar imagem', [
                'error' => $e->getMessage(),
                'path' => $request->input('path'),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar imagem',
            ], 500);
        }
    }

    /**
     * Gera URL assinada para visualizar uma imagem de upload (evita 403 em /storage).
     * Aceita URL completa (https://.../storage/uploads/...) ou path (uploads/...).
     * Retorna URL válida por 1 hora.
     */
    public static function signedUrlForAnexo(?string $anexoUrl): ?string
    {
        if (empty($anexoUrl)) {
            return null;
        }
        $path = $anexoUrl;
        if (preg_match('#/storage/(.+)$#', $anexoUrl, $m)) {
            $path = $m[1];
        }
        $path = ltrim($path, '/');
        if (strpos($path, '..') !== false || strpos($path, 'uploads/') !== 0) {
            return null;
        }
        try {
            return URL::temporarySignedRoute('serve-upload', now()->addHours(1), ['path' => $path], true);
        } catch (\Throwable $e) {
            Log::warning('UploadController::signedUrlForAnexo failed', ['url' => $anexoUrl, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Serve uma imagem de upload via URL assinada (sem expor /storage diretamente).
     * Rota: GET /api/v1/serve-upload?path=uploads/...&signature=...&expires=...
     */
    public function serveImage(Request $request): StreamedResponse|BinaryFileResponse|\Illuminate\Http\Response
    {
        $path = $request->query('path', '');
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, '..') !== false || strpos($path, 'uploads/') !== 0) {
            abort(404);
        }
        $fullPath = Storage::disk('public')->path($path);
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            abort(404);
        }
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mime]);
    }
}



