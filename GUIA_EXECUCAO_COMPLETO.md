# GUIA DE EXECUÇÃO - Sistema Licitação (Completo)

## 📋 Pré-requisitos

- PHP 8.1+
- Laravel 10.x
- Node.js 16+
- npm ou yarn
- Docker (opcional)
- PostgreSQL ou MySQL

## 🚀 BACKEND (Laravel)

### 1. Instalação e Setup

```bash
cd erp-romulo-back

# Instalar dependências
composer install

# Copiar variáveis de ambiente
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate

# Configurar banco de dados em .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_romulo
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### 2. Executar Migrations

```bash
# Criar tabelas
php artisan migrate

# Verificar status
php artisan migrate:status
```

**Migrations criadas para Curto Prazo:**
- 2025_12_31_170000_create_orcamentos_table
- 2025_12_31_170100_create_orcamento_itens_table
- 2025_12_31_170200_create_formacao_precos_table
- 2025_12_31_170300_add_disputa_julgamento_fields_to_processo_itens

### 3. Seeders (Opcional)

```bash
# Criar dados de teste
php artisan db:seed

# Criar com factory
php artisan tinker
>>> User::factory()->count(10)->create();
>>> Empresa::factory()->count(5)->create();
>>> Processo::factory()->count(20)->create();
```

### 4. Executar Servidor

**Desenvolvimento:**
```bash
# Terminal 1: Servidor Laravel
php artisan serve
# Acesso: http://localhost:8000

# Terminal 2: Scheduler (Para transições automáticas de status)
php artisan schedule:work
# Logs em: storage/logs/schedule.log

# Terminal 3 (Opcional): Queue Worker
php artisan queue:work
```

**Produção:**
```bash
# Usar supervisor ou systemd para manter scheduler rodando
# Exemplo com supervisor:
cat > /etc/supervisor/conf.d/erp-romulo.conf << EOF
[program:erp-romulo-schedule]
process_name=%(program_name)s_%(process_num)02d
command=php /home/app/artisan schedule:run
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/erp-romulo-schedule.log
EOF

supervisorctl reread
supervisorctl update
supervisorctl start erp-romulo-schedule
```

### 5. Testes

```bash
# Executar todos os testes
php artisan test

# Testes específicos
php artisan test --filter OrcamentoTest
php artisan test --filter FormacaoPrecoTest
php artisan test --filter ProcessoItemDisputaTest

# Com cobertura
php artisan test --coverage

# Com output verbose
php artisan test -v
```

### 6. Debug

```bash
# Verificar routes
php artisan route:list | grep -E "orcamento|formacao|disputa"

# Verificar migrations
php artisan migrate:status

# Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Verificar logs
tail -f storage/logs/laravel.log
```

---

## 🎨 FRONTEND (React)

### 1. Instalação

```bash
cd erp-romulo-front

# Instalar dependências
npm install
# ou yarn install

# Copiar variáveis de ambiente
cp .env.example .env.local
```

### 2. Configurar Variáveis

```env
# .env.local
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME="ERP Rômulo"
VITE_APP_URL=http://localhost:5173
```

### 3. Executar Servidor

```bash
# Desenvolvimento
npm run dev
# Acesso: http://localhost:5173

# Build para produção
npm run build

# Preview da build
npm run preview
```

### 4. Estrutura de Componentes

```
src/
├── components/
│   ├── processo/
│   │   ├── OrcamentosProcesso.jsx ✅
│   │   ├── CalendarioDisputas.jsx ✅
│   │   ├── ProcessoItemDisputaJulgamento.jsx ✅
│   │   ├── ProcessoDocumentos.jsx
│   │   └── ProcessoShow.jsx
│   ├── common/
│   │   ├── Header.jsx
│   │   ├── Sidebar.jsx
│   │   └── Footer.jsx
│   └── layout/
│       └── MainLayout.jsx
├── pages/
│   ├── processo/
│   │   ├── ProcessoList.jsx
│   │   ├── ProcessoShow.jsx
│   │   └── ProcessoCreate.jsx
│   ├── orcamento/
│   │   └── OrcamentoDetail.jsx
│   └── dashboard/
│       └── Dashboard.jsx
├── hooks/
│   ├── useOrcamento.js
│   ├── useCalendarioDisputas.js
│   └── useProceso.js
├── services/
│   ├── api/
│   │   ├── processo.api.js
│   │   ├── orcamento.api.js
│   │   └── auth.api.js
│   └── api.js
└── stores/
    ├── processoStore.js
    ├── orcamentoStore.js
    └── authStore.js
```

---

## 🐳 DOCKER (Opcional)

### 1. Build da Imagem

```bash
# Build do backend
docker build -t erp-romulo-backend -f Dockerfile .

# Build do frontend
docker build -t erp-romulo-frontend -f Dockerfile -C erp-romulo-front .
```

### 2. Docker Compose

```bash
# Levantar containers
docker-compose up -d

# Verificar status
docker-compose ps

# Ver logs
docker-compose logs -f

# Parar containers
docker-compose down
```

**docker-compose.yml Exemplo:**
```yaml
version: '3.8'

services:
  # PostgreSQL Database
  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: erp_romulo
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

  # Laravel Backend
  backend:
    build:
      context: ./erp-romulo-back
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    environment:
      DB_HOST: postgres
      DB_PORT: 5432
    depends_on:
      - postgres
    volumes:
      - ./erp-romulo-back:/app

  # React Frontend
  frontend:
    build:
      context: ./erp-romulo-front
      dockerfile: Dockerfile
    ports:
      - "5173:5173"
    environment:
      VITE_API_BASE_URL: http://backend:8000/api/v1
    depends_on:
      - backend

volumes:
  postgres_data:
```

---

## 🔐 Autenticação

### 1. Registrar Usuário

```bash
# Via API
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin",
    "email": "admin@example.com",
    "password": "password",
    "password_confirmation": "password"
  }'
```

### 2. Login

```bash
# Via API
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'

# Resposta:
{
  "token": "eyJ0eXAiOiJKV1QiLC...",
  "user": { ... }
}
```

### 3. Usar Token

```javascript
// Frontend
const token = localStorage.getItem('auth_token');

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    Authorization: `Bearer ${token}`,
    'X-Empresa-ID': empresaId
  }
});
```

---

## 📊 Testes de Integração

### 1. Com Postman

**Importar Collection:**
```bash
# 1. Abrir Postman
# 2. File > Import
# 3. Selecionar: postman-collection.json
```

**Variáveis de Ambiente:**
```json
{
  "base_url": "http://localhost:8000/api/v1",
  "token": "seu_token_aqui",
  "empresa_id": "1",
  "processo_id": "1"
}
```

### 2. Com cURL

```bash
# Listar orçamentos
curl -H "Authorization: Bearer $TOKEN" \
     -H "X-Empresa-ID: 1" \
     http://localhost:8000/api/v1/processos/1/orcamentos

# Criar orçamento
curl -X POST http://localhost:8000/api/v1/processos/1/orcamentos \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Empresa-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "fornecedor_id": 1,
    "itens": [{
      "processo_item_id": 1,
      "quantidade": 10,
      "preco_unitario": 100
    }]
  }'

# Atualizar valor disputa
curl -X PATCH http://localhost:8000/api/v1/processos/1/itens/1/valor-final-disputa \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Empresa-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{"valor_final_pos_disputa": 850}'
```

---

## 🐛 Troubleshooting

### Erro: "SQLSTATE[08006]"
```bash
# Verificar conexão com banco
php artisan tinker
>>> DB::connection()->getPdo();

# Criar banco se não existir
createdb erp_romulo
```

### Erro: "Class not found"
```bash
# Limpar cache e recarregar autoload
composer dump-autoload
php artisan cache:clear
```

### Erro: "Unauthorized"
```bash
# Verificar token
# Verificar header X-Empresa-ID
# Verificar .env: APP_KEY e JWT_SECRET
```

### Scheduler não roda
```bash
# Verificar se o comando está agendado
php artisan schedule:list

# Executar manualmente
php artisan AtualizarStatusProcessosAutomatico
```

---

## 📈 Monitoramento

### Logs

```bash
# Backend
tail -f storage/logs/laravel.log

# Específico
tail -f storage/logs/laravel-2025-12-31.log
```

### Performance

```bash
# Debugbar (desenvolvimento)
# Adicionar em .env: DEBUGBAR_ENABLED=true

# Verificar queries
DB::enableQueryLog();
// ... seu código
dd(DB::getQueryLog());
```

---

## 🚢 Deploy em Produção

### 1. Preparação

```bash
# Backend
cd erp-romulo-back
composer install --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Frontend
cd erp-romulo-front
npm install
npm run build
# Build gerado em: dist/
```

### 2. Nginx Configuration

```nginx
server {
    listen 80;
    server_name api.exemplo.com;

    root /home/app/public;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}

server {
    listen 80;
    server_name www.exemplo.com;

    root /home/app/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        proxy_pass http://api.exemplo.com;
    }
}
```

### 3. Supervisor Configuration

```ini
[program:erp-romulo-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/app/artisan queue:work
autostart=true
autorestart=true
stdout_logfile=/var/log/erp-romulo-queue.log

[program:erp-romulo-schedule]
process_name=%(program_name)s
command=php /home/app/artisan schedule:run
autostart=true
autorestart=true
stdout_logfile=/var/log/erp-romulo-schedule.log
```

---

## ✅ Checklist Pré-Produção

- [ ] Banco de dados migrado
- [ ] Variáveis de ambiente configuradas
- [ ] Chaves de segurança geradas
- [ ] Scheduler em execução contínua
- [ ] Backups configurados
- [ ] SSL/TLS habilitado
- [ ] Rate limiting configurado
- [ ] Logs rotacionando
- [ ] Monitoramento ativo
- [ ] CDN configurado (opcional)

---

## 📞 Contato e Suporte

- **API Docs**: `/api/v1/docs` (via Swagger)
- **Health Check**: `GET /api/v1/health`
- **Status Page**: `/status`

---

**Última atualização:** 31/12/2025
**Pronto para Produção:** ✅ Sim

