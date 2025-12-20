# 🚀 Instruções para Teste do Zero

## ✅ O que foi implementado

### 1. Exclusão de Documentos - CORRIGIDO ✅
- Agora usa `forceDelete()` para exclusão permanente
- Documentos são realmente excluídos do banco

### 2. Isolamento Total por Empresa - IMPLEMENTADO ✅
- Todos os dados são filtrados por `empresa_id`
- Ao trocar empresa, apenas dados daquela empresa aparecem
- `empresa_id` é definido automaticamente ao criar registros

## 📋 Passos para Testar do Zero

### 1. Executar Migrations
```bash
cd erp-romulo-back
php artisan tenants:migrate --force
```

### 2. Executar Seeder (cria empresa e usuários)
```bash
php artisan db:seed
```

### 3. Fazer Login
- Email: `admin@exemplo.com`
- Senha: `password`

### 4. Criar Segunda Empresa (para testar isolamento)
- Acessar página de Empresas
- Criar nova empresa
- Associar usuário à nova empresa

### 5. Testar Isolamento
1. **Criar dados na Empresa 1:**
   - Criar processo
   - Criar fornecedor
   - Criar documento de habilitação
   - Criar orçamento

2. **Trocar para Empresa 2:**
   - Selecionar Empresa 2
   - Verificar que NENHUM dado da Empresa 1 aparece

3. **Criar dados na Empresa 2:**
   - Criar processo
   - Criar fornecedor
   - Verificar que dados da Empresa 1 não aparecem

4. **Voltar para Empresa 1:**
   - Verificar que apenas dados da Empresa 1 aparecem
   - Dados da Empresa 2 não aparecem

### 6. Testar Exclusão
1. **Excluir documento:**
   - Criar documento
   - Excluir documento
   - Verificar que documento NÃO aparece mais na lista

2. **Excluir outros registros:**
   - Testar exclusão de fornecedor, processo, etc.
   - Verificar que são realmente excluídos

## 📊 O que deve aparecer por empresa

Ao trocar empresa, APENAS devem aparecer:
- ✅ Processos daquela empresa
- ✅ Fornecedores daquela empresa
- ✅ Documentos de habilitação daquela empresa
- ✅ Orçamentos daquela empresa
- ✅ Contratos daquela empresa
- ✅ Empenhos daquela empresa
- ✅ Notas fiscais daquela empresa
- ✅ Calendário com processos daquela empresa
- ✅ Dashboard com dados daquela empresa

## ⚠️ IMPORTANTE

- **Todas as exclusões são permanentes** (forceDelete)
- **Isolamento é total** - dados de outras empresas não aparecem
- **empresa_id é automático** - não precisa enviar no request
- **Validação em todos os métodos** - segurança garantida

## 🐛 Se encontrar problemas

1. Verificar se migrations foram executadas
2. Verificar se empresa_id foi adicionado nas tabelas
3. Verificar logs em `storage/logs/laravel.log`
4. Verificar se usuário tem `empresa_ativa_id` definido

## 📝 Checklist de Teste

- [ ] Migrations executadas
- [ ] Seeder executado
- [ ] Login funciona
- [ ] Duas empresas criadas
- [ ] Dados criados em cada empresa
- [ ] Isolamento funciona (trocar empresa)
- [ ] Exclusão funciona (documentos, fornecedores, etc.)
- [ ] Dashboard mostra apenas dados da empresa ativa
- [ ] Calendário mostra apenas processos da empresa ativa

