#!/usr/bin/env php
<?php
/**
 * Script para publicar as views de exportação em ambientes onde o app roda em /var/www/html.
 * Execute UMA VEZ no servidor (ex.: php deploy-views-to-html.php) ou após atualizar as views.
 *
 * Uso no servidor:
 *   cd /var/www/html
 *   php /caminho/para/deploy-views-to-html.php
 *
 * Ou, se o projeto api.addireta.com estiver no servidor:
 *   php /var/www/api.addireta.com/deploy-views-to-html.php
 */

$targetBase = getenv('HTML_APP_PATH') ?: '/var/www/html';
$sourceBase = __DIR__;

$views = [
    'resources/views/exports/proposta_comercial.blade.php',
    'resources/views/exports/catalogo_ficha_tecnica.blade.php',
];

foreach ($views as $relativePath) {
    $source = $sourceBase . '/' . $relativePath;
    $target = $targetBase . '/' . $relativePath;

    if (!is_file($source)) {
        echo "AVISO: Arquivo de origem não encontrado: {$source}\n";
        continue;
    }

    $dir = dirname($target);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo "ERRO: Não foi possível criar o diretório: {$dir}\n";
            continue;
        }
        echo "Criado diretório: {$dir}\n";
    }

    $content = file_get_contents($source);
    if ($content === false) {
        echo "ERRO: Não foi possível ler: {$source}\n";
        continue;
    }

    if (file_put_contents($target, $content) === false) {
        echo "ERRO: Não foi possível escrever: {$target}\n";
        continue;
    }

    echo "OK: {$relativePath} -> {$target}\n";
}

echo "Concluído.\n";
