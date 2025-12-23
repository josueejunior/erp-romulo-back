<?php

/**
 * Script para atualizar migrations existentes para usar a nova arquitetura
 * 
 * Uso: php scripts/update-migrations.php
 */

$migrationsPath = __DIR__ . '/../database/migrations';
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($migrationsPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$updated = 0;
$skipped = 0;

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    $originalContent = $content;
    
    // Pular se já usa a nova arquitetura
    if (strpos($content, 'App\Database\Migrations\Migration') !== false) {
        $skipped++;
        continue;
    }

    // Substituir imports
    $content = str_replace(
        'use Illuminate\Database\Migrations\Migration;',
        'use App\Database\Migrations\Migration;',
        $content
    );
    
    $content = str_replace(
        'use Illuminate\Database\Schema\Blueprint;',
        'use App\Database\Schema\Blueprint;',
        $content
    );

    // Adicionar propriedade $table se for create_*
    if (preg_match('/create_(\w+)\.php/', $file->getFilename(), $matches)) {
        $tableName = $matches[1];
        
        // Verificar se já tem a propriedade
        if (strpos($content, 'public string $table') === false) {
            // Adicionar após a declaração da classe
            $content = preg_replace(
                '/return new class extends Migration\s*\{/',
                "return new class extends Migration\n{\n    public string \$table = '{$tableName}';",
                $content
            );
        }
    }

    // Aplicar melhorias comuns (opcional - comentado para não quebrar migrations existentes)
    // Você pode descomentar e ajustar conforme necessário
    
    if ($content !== $originalContent) {
        file_put_contents($file->getPathname(), $content);
        $updated++;
        echo "✓ Atualizado: {$file->getFilename()}\n";
    } else {
        $skipped++;
    }
}

echo "\n✅ Concluído!\n";
echo "Atualizadas: {$updated}\n";
echo "Ignoradas (já atualizadas): {$skipped}\n";


