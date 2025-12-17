# 🌱 Executar Seeder de Planos

## Comando

```bash
php artisan db:seed --class=PlanosSeeder
```

## O que será criado

3 planos padrão:

1. **Básico** - R$ 99/mês
   - 10 processos
   - 3 usuários
   - 1GB armazenamento

2. **Profissional** - R$ 299/mês
   - 50 processos
   - 10 usuários
   - 10GB armazenamento

3. **Enterprise** - R$ 799/mês
   - Ilimitado
   - Ilimitado
   - Ilimitado

## ⚠️ Importante

- Os planos são criados no **banco central** (não tenant)
- Podem ser visualizados sem autenticação
- Podem ser contratados por qualquer tenant

