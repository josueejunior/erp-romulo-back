# 🔧 Correção do Composer - l5-swagger

## ❌ Problema

O `composer.json` tinha uma versão inválida do `l5-swagger`:

```json
"darkaonline/l5-swagger": "^8.7"  // ❌ Versão 8.7 não existe!
```

**Versões disponíveis:**
- 8.0.0 até 8.6.5
- Depois pula para 9.x e 10.x

Isso impedia qualquer `composer require` de funcionar, incluindo `firebase/php-jwt`.

## ✅ Solução Aplicada

Como o projeto usa **Laravel 12**, foi ajustado para a versão mais recente:

```json
"darkaonline/l5-swagger": "^10.0"  // ✅ Compatível com Laravel 12
```

## 📋 Próximos Passos

1. **Atualizar o l5-swagger:**
   ```bash
   composer update darkaonline/l5-swagger
   ```

2. **Instalar firebase/php-jwt:**
   ```bash
   composer require firebase/php-jwt
   ```

3. **Ou fazer tudo de uma vez:**
   ```bash
   composer update
   ```

## 🔍 Verificação

Após atualizar, verificar se tudo está OK:

```bash
composer show darkaonline/l5-swagger
composer show firebase/php-jwt
```

## 📚 Referências

- [l5-swagger no Packagist](https://packagist.org/packages/darkaonline/l5-swagger)
- Versões disponíveis: 8.0.0-8.6.5, 9.0.0+, 10.0.0+

