# Publicar views de exportação no servidor (api.addsimp.com)

O erro **"View [exports.proposta_comercial] not found"** ocorre quando o Laravel no servidor está rodando a partir de `/var/www/html` e a pasta `resources/views/exports/` não existe lá.

## Solução rápida (no servidor)

Conecte no servidor (SSH) e crie a pasta e os arquivos. Você pode fazer de duas formas:

### Opção 1: Copiar do repositório (recomendado)

Se no servidor você tem o código do projeto (por exemplo em `/var/www/api.addireta.com` ou no mesmo lugar de onde o app é servido):

```bash
# No servidor, a partir da raiz do projeto da API (onde está resources/views/exports/)
mkdir -p /var/www/html/resources/views/exports
cp resources/views/exports/proposta_comercial.blade.php resources/views/exports/catalogo_ficha_tecnica.blade.php /var/www/html/resources/views/exports/
```

Se o app no servidor **é** o próprio projeto (não há `/var/www/html` separado), então a pasta `resources/views/exports/` já deve existir no deploy. Confira se os arquivos estão no repositório e se o deploy inclui essa pasta.

### Opção 2: Usar o script de deploy

No servidor, se existir o projeto em algum diretório (ex.: `/var/www/api.addireta.com`):

```bash
cd /var/www/api.addireta.com
export HTML_APP_PATH=/var/www/html
php deploy-views-to-html.php
```

Isso copia `resources/views/exports/*.blade.php` para `/var/www/html/resources/views/exports/`.

### Opção 3: Enviar do seu computador para o servidor

Do seu computador (onde está o código):

```bash
scp api.addireta.com/resources/views/exports/proposta_comercial.blade.php \
    usuario@api.addsimp.com:/var/www/html/resources/views/exports/

scp api.addireta.com/resources/views/exports/catalogo_ficha_tecnica.blade.php \
    usuario@api.addsimp.com:/var/www/html/resources/views/exports/
```

Antes, crie a pasta no servidor:

```bash
ssh usuario@api.addsimp.com "mkdir -p /var/www/html/resources/views/exports"
```

---

Depois de publicar as views, limpe o cache (opcional):

```bash
cd /var/www/html && php artisan view:clear
```

Em seguida, teste de novo:  
`GET https://api.addsimp.com/api/v1/processos/2/exportar/proposta-comercial`
