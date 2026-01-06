<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Salvar no storage público
            $path = $file->storeAs('uploads/images', $filename, 'public');
            
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
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer upload da imagem: ' . $e->getMessage(),
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
}


