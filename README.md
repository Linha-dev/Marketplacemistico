# 🔮 Marketplace Místico

Marketplace de produtos místicos e esotéricos conectando vendedores especializados e compradores interessados em sua jornada espiritual.

## 📋 Sobre o Projeto

O Marketplace Místico é uma plataforma web completa para compra e venda de produtos esotéricos, místicos e espirituais. A plataforma oferece um sistema robusto de gerenciamento de produtos, autenticação de usuários e permite que clientes se tornem vendedores facilmente.

## ✨ Funcionalidades Principais

### Para Todos os Usuários
- 🔍 **Explorar Produtos**: Navegue por diversos produtos místicos organizados por categorias
- 📱 **Interface Responsiva**: Acesso completo via desktop, tablet e dispositivos móveis
- 🔐 **Sistema de Autenticação Seguro**: Login e registro com criptografia de senhas (bcrypt)
- 🛡️ **Proteção JWT**: Autenticação baseada em tokens JWT com validade de 7 dias

### Para Clientes (Compradores)
- 🛒 **Carrinho de Compras**: Adicione produtos ao carrinho e gerencie suas compras
- 👤 **Perfil Personalizável**: Gerencie suas informações pessoais
- 📦 **Visualização de Pedidos**: Acompanhe o histórico de suas compras
- 📍 **Gerenciamento de Endereços**: Cadastre e gerencie endereços de entrega
- 🏪 **Upgrade para Vendedor**: Possibilidade de se tornar vendedor mantendo capacidade de compra

### Para Vendedores
- ➕ **Adicionar Produtos**: Cadastre novos produtos com nome, descrição, preço, estoque e imagem
- 📊 **Dashboard de Vendedor**: Visualize e gerencie todos os seus produtos
- ✏️ **Editar Produtos**: Atualize informações dos produtos existentes
- 🗑️ **Excluir Produtos**: Remova produtos do catálogo
- 🏪 **Perfil da Loja**: Configure nome da loja, categoria, descrição e CPF/CNPJ
- 📈 **Controle de Estoque**: Gerencie quantidade disponível de cada produto
- 👁️ **Publicação de Produtos**: Escolha quais produtos exibir publicamente (rascunho ou publicado)
- 🛒 **Comprar como Cliente**: Vendedores mantêm todas as funcionalidades de comprador

### Categorias de Produtos
- 🔮 Cristais e Pedras
- 🕯️ Velas e Incensos
- 📿 Amuletos e Talismãs
- 📚 Livros Esotéricos
- 🌿 Ervas e Óleos
- 🔯 Artigos Ritualísticos
- 🃏 Tarô e Oráculos
- ✨ Outros

## 🛠️ Tecnologias Utilizadas

### Backend (Node.js — original)
- **Node.js** - Runtime JavaScript
- **Express** - Framework HTTP
- **Fly.io** - Hospedagem do backend
- **PostgreSQL** (Neon Database) - Banco de dados
- **bcryptjs** - Criptografia de senhas
- **jsonwebtoken** - Autenticação JWT

### Backend (PHP — deploy em hostings convencionais)
- **PHP 8.1+** - Runtime (sem frameworks externos, zero dependências Composer)
- **MySQL / MariaDB** - Banco de dados (padrão Hostinger shared hosting)
- **PDO** - Acesso ao banco (suporta MySQL e PostgreSQL via `DB_DRIVER`)
- **password_hash / password_verify** - bcrypt nativo do PHP
- **HMAC-SHA256 JWT** - Implementação JWT pura PHP (sem biblioteca)
- **cURL** - Integração com EFI/Pix e Melhor Envio

### Frontend
- **HTML5** - Estrutura
- **CSS3** - Estilização (design system customizado)
- **JavaScript (Vanilla)** - Lógica da aplicação
- **LocalStorage** - Persistência de sessão no navegador

### Segurança
- ✅ Sanitização de inputs (strip_tags, htmlspecialchars)
- ✅ Validação de CPF/CNPJ
- ✅ Validação de email
- ✅ Senhas hasheadas com bcrypt
- ✅ Tokens JWT com expiração
- ✅ CORS configurado
- ✅ Prepared statements PDO (SQL injection prevention)
- ✅ Rate limiting por IP e rota (banco de dados)

## 🚀 Como Executar o Projeto

---

### 🐘 Versão PHP — Deploy no Hostinger (ou qualquer hosting convencional)

> Esta é a versão recomendada para deploys em **Hostinger**, **KingHost**, **LocaWeb**, e outros hostings que oferecem PHP + MySQL.

#### Pré-requisitos
- Plano com PHP 8.1+ e MySQL (qualquer plano Hostinger Business ou superior)
- Acesso ao painel de controle (hPanel) e File Manager ou FTP
- Opcionalmente, acesso SSH (disponível nos planos Business/Cloud)

#### Passo a passo — Deploy Hostinger

**1. Crie o banco de dados MySQL**

No painel Hostinger → **Databases → MySQL Databases**:
- Crie um banco de dados
- Crie um usuário com permissões completas no banco

**2. Importe o schema MySQL**

Via **phpMyAdmin** (Databases → phpMyAdmin), importe o arquivo:
```
php/schema_mysql.sql
```

Isso criará todas as tabelas necessárias: `users`, `sellers`, `products`, `orders`, `payments`, `refunds`, `webhook_events`, etc.

**3. Envie os arquivos para o servidor**

Copie o conteúdo da pasta **`php/`** para a pasta **`public_html/`** do seu hosting (via File Manager ou FTP):

```
public_html/
├── .htaccess            ← do php/
├── index.php            ← do php/
├── api/
│   └── index.php        ← do php/api/
├── src/                 ← do php/src/
│   ├── Config.php
│   ├── Database.php
│   └── ...
```

Copie também os arquivos do frontend (`public/`):
```
public_html/
├── index.html   ← de public/index.html
├── app.js       ← de public/app.js
├── style.css    ← de public/style.css
└── favicon.svg  ← de public/favicon.svg
```

**4. Configure as variáveis de ambiente**

Copie `php/.env.exemplo` para `public_html/.env` e preencha com seus dados reais:

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=seu_banco
DB_USER=seu_usuario
DB_PASS=sua_senha

JWT_SECRET=uma_string_longa_e_secreta_aqui

ALLOWED_ORIGIN=https://seu-dominio.com

EFI_CLIENT_ID=...
EFI_PIX_KEY=...
MELHOR_ENVIO_ACCESS_TOKEN=...
```

> ⚠️ **Segurança**: O arquivo `.env` **não deve** ficar acessível publicamente. O `.htaccess` já bloqueia o acesso direto a arquivos `.env`. Verifique que o `mod_rewrite` está habilitado no seu plano.

**5. Atualize a URL da API no frontend**

No arquivo `public/app.js`, localize a constante `API_BASE_URL` e aponte para o seu domínio:

```js
// Antes (Vercel/Fly.io):
const API_BASE_URL = 'https://seu-backend.fly.dev';

// Depois (Hostinger):
const API_BASE_URL = 'https://seu-dominio.com/api';
```

**6. Verifique o funcionamento**

Acesse: `https://seu-dominio.com/api/health`

Você deve ver:
```json
{"success": true, "data": {"status": "ok", "database": "connected", ...}}
```

#### Variáveis de ambiente completas

Consulte o arquivo `php/.env.exemplo` para a lista completa de variáveis disponíveis.

#### Suporte a PostgreSQL (VPS Hostinger)

Se você tiver um VPS com PostgreSQL, basta alterar o driver:
```env
DB_DRIVER=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=seu_banco
```

O schema PostgreSQL original está em `schema.sql` na raiz do projeto.

---

### ⚡ Versão Node.js — Deploy no Fly.io (original)

#### Pré-requisitos
- Node.js (versão 22 ou superior)
- Banco de dados PostgreSQL (recomendado: Neon)

#### Instalação

1. Clone o repositório:
```bash
git clone https://github.com/victordg0223/Marketplacemistico.git
cd Marketplacemistico
```

2. Instale as dependências:
```bash
npm install
```

3. Configure as variáveis de ambiente:
Crie um arquivo `.env` na raiz do projeto com:
```env
DATABASE_URL=sua_connection_string_postgresql
JWT_SECRET=sua_chave_secreta_jwt
ALLOWED_ORIGIN=http://localhost:3000
```

4. Aplique as migrações no seu banco de dados:
```bash
npm run db:migrate
```

Rollback da última migração (quando necessário):
```bash
npm run db:rollback
```

5. Inicie o servidor de desenvolvimento:
```bash
npm run dev
```

6. Acesse a aplicação em `http://localhost:8080`

## 🧪 Testes (TDD)

O projeto utiliza **Jest** para testes unitários e de integração. Os testes seguem a filosofia TDD, garantindo que as regras de negócio sejam validadas antes e durante o desenvolvimento.

### Como executar os testes
```bash
# Executar todos os testes
npm test

# Executar um arquivo de teste específico
npm test -- tests/unit/sanitize.test.js

# Executar testes em modo watch (se estiver em ambiente local)
npx jest --watch
```

### Cobertura de Testes Atual
- **Unitários**: Validação de inputs (CPF/CNPJ, e-mail, senhas) e lógica do carrinho de compras.
- **Integração**: Fluxo de registro de usuários e criação de pedidos com validação de estoque e permissões.

## 📁 Estrutura do Projeto

```
Marketplacemistico/
├── backend/                  # Backend Node.js (original)
│   ├── api-handler.js        # Dispatcher central de rotas da API
│   ├── routes.js             # Registro de rotas
│   ├── middleware.js          # CORS e middlewares
│   ├── auth/                 # Autenticacao
│   ├── products/             # Produtos
│   ├── orders/               # Pedidos
│   ├── modules/              # Dominios de negocio (MVC)
│   └── observability/        # Logs, metricas e alertas
├── php/                      # ← VERSÃO PHP (deploy Hostinger)
│   ├── .htaccess             # URL rewriting (Apache mod_rewrite)
│   ├── .env.exemplo          # Template de variáveis de ambiente
│   ├── index.php             # Entrypoint SPA fallback
│   ├── schema_mysql.sql      # Schema MySQL completo
│   ├── api/
│   │   └── index.php         # Dispatcher da API (router)
│   └── src/
│       ├── Config.php        # Carregamento de .env
│       ├── Database.php      # PDO wrapper (MySQL/PostgreSQL)
│       ├── Response.php      # sendSuccess / sendError
│       ├── Auth.php          # JWT puro PHP (HS256)
│       ├── Middleware.php    # CORS + cabeçalhos de segurança
│       ├── RateLimit.php     # Rate limiting via banco
│       ├── Sanitize.php      # Sanitização e validação de inputs
│       ├── RBAC.php          # Controle de acesso por papel
│       ├── Router.php        # Roteador por regex
│       ├── handlers/         # Handlers por domínio
│       │   ├── auth/         # login, register, me, refresh, Google
│       │   ├── products/     # CRUD + publicação
│       │   ├── orders/       # CRUD + status + pós-venda
│       │   ├── payments/     # Pix EFI + refunds
│       │   ├── users/        # profile, upgrade, endereços
│       │   ├── sellers/      # me, byId, orders, products
│       │   ├── shipping/     # cotação Melhor Envio
│       │   ├── webhooks/     # EFI + Melhor Envio
│       │   ├── finance/      # ledger, reconciliação
│       │   ├── manual_payouts/ # repasses manuais
│       │   └── observability/  # alertas, métricas, histórico
│       └── services/
│           ├── EfiClient.php         # Cliente EFI / Pix
│           └── MelhorEnvioClient.php # Cliente Melhor Envio
├── public/                   # Frontend estático (HTML/CSS/JS)
│   ├── index.html            # Página principal
│   ├── app.js                # Lógica JavaScript
│   ├── style.css             # Estilos
│   └── favicon.svg           # Ícone do site
├── migrations/              # Migrações SQL incrementais (up/down)
├── scripts/migrate.js       # Runner de migrações e rollback
├── schema.sql               # Schema PostgreSQL (Node.js / VPS)
├── Dockerfile                # Build de container para Fly.io
├── fly.toml                  # Configuracao de deploy no Fly.io
├── server.js                 # Entrypoint Express
└── package.json              # Dependencias Node.js
```

## 🔑 API Endpoints

### Autenticação
- `POST /api/auth/register` - Registrar novo usuário (cliente ou vendedor)
- `POST /api/auth/login` - Login de usuário

### Produtos
- `GET /api/products` - Listar produtos (com filtros de categoria e vendedor)
- `POST /api/products` - Criar novo produto (requer autenticação de vendedor)
- `DELETE /api/products/[id]` - Excluir produto (requer autenticação de vendedor)

### Usuários
- `GET /api/users/profile` - Obter perfil do usuário
- `POST /api/users/upgrade-to-vendor` - Converter cliente em vendedor

## 💾 Modelo de Dados

### Principais Tabelas
- **users** - Informações dos usuários (clientes e vendedores)
- **sellers** - Dados específicos de vendedores (loja, categoria)
- **products** - Catálogo de produtos
- **addresses** - Endereços de entrega
- **orders** - Pedidos realizados
- **order_items** - Itens de cada pedido

## 🔄 Fluxo de Upgrade de Cliente para Vendedor

1. Cliente faz login na plataforma
2. Acessa opção "Tornar-se Vendedor" no dashboard
3. Preenche dados da loja (nome, categoria, descrição, CPF/CNPJ)
4. Sistema cria registro de vendedor e atualiza tipo do usuário
5. **Novo token JWT é gerado** com permissões de vendedor
6. Cliente imediatamente pode adicionar produtos (sem necessidade de logout/login)

> **Nota Técnica**: A atualização do token JWT após upgrade é essencial para que o vendedor tenha acesso imediato às funcionalidades de venda, já que as APIs validam permissões através do token.

---

## 🔄 Notas da Migração Node.js → PHP

### O que foi migrado

| Componente (Node.js) | Equivalente PHP |
|---|---|
| `backend/db.js` (pg) | `php/src/Database.php` (PDO/MySQL) |
| `backend/middleware.js` | `php/src/Middleware.php` |
| `backend/sanitize.js` | `php/src/Sanitize.php` |
| `backend/auth-middleware.js` | `php/src/Auth.php` |
| `backend/rbac.js` | `php/src/RBAC.php` |
| `bcryptjs` | `password_hash()` / `password_verify()` (PHP nativo) |
| `jsonwebtoken` | HMAC-SHA256 JWT puro PHP (`php/src/Auth.php`) |
| `xss` library | `strip_tags()` + `htmlspecialchars()` |
| Node.js rate limiting | Rate limiting por banco de dados (`php/src/RateLimit.php`) |
| Express router | `php/src/Router.php` (regex routes) |
| `node-fetch` / `fetch` | cURL nativo do PHP |
| Todos os handlers `/api/*` | `php/src/handlers/**/*.php` |
| EFI client | `php/src/services/EfiClient.php` |
| Melhor Envio client | `php/src/services/MelhorEnvioClient.php` |
| `schema.sql` (PostgreSQL) | `php/schema_mysql.sql` (MySQL) |

### Diferenças importantes

#### Banco de dados
- O backend Node.js usa **PostgreSQL** com a biblioteca `pg`.
- A versão PHP usa **MySQL** por padrão (padrão Hostinger shared hosting).
- **PostgreSQL também é suportado** na versão PHP: basta definir `DB_DRIVER=pgsql` no `.env` e usar `schema.sql` (raiz).
- Sintaxe adaptada: `$1,$2` → `?`, `RETURNING *` → `lastInsertId() + SELECT`, `ON CONFLICT DO UPDATE` → `ON DUPLICATE KEY UPDATE`, `JSONB` → `JSON`, `INTERVAL '30 minutes'` → `INTERVAL 30 MINUTE`.

#### Métricas em memória
- O Node.js usa contadores em memória (`observability/metrics-store.js`) que persistem enquanto o processo está rodando.
- Em PHP (shared hosting), cada request é stateless. As métricas são derivadas do banco de dados em vez de contadores em memória.

#### Variáveis de ambiente
- Node.js usa `dotenv` e `process.env`.
- PHP usa um loader manual (`php/src/Config.php`) que lê `.env` da raiz do projeto.

#### Sem Composer / sem dependências externas
- A versão PHP **não requer Composer**, `npm`, nem nenhuma dependência externa.
- 100% PHP puro com extensões padrão: `PDO`, `pdo_mysql`, `pdo_pgsql`, `curl`, `openssl`.

### Checklist de compatibilidade Hostinger

- [x] PHP 8.1+ (Hostinger oferece 8.1, 8.2, 8.3)
- [x] MySQL 8.0 ou MariaDB 10.x
- [x] Apache + mod_rewrite (habilitado por padrão nos planos)
- [x] ext-pdo, ext-pdo_mysql (habilitados por padrão)
- [x] ext-curl (habilitado por padrão)
- [x] ext-openssl (habilitado por padrão — necessário para HTTPS/JWT)
- [x] Sem Composer necessário

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para:
1. Fazer fork do projeto
2. Criar uma branch para sua feature (`git checkout -b feature/MinhaFeature`)
3. Commit suas mudanças (`git commit -m 'Adiciona nova feature'`)
4. Push para a branch (`git push origin feature/MinhaFeature`)
5. Abrir um Pull Request

## 📄 Licença

Este projeto está sob a licença especificada no arquivo LICENSE.

## 👥 Autores

- [@victordg0223](https://github.com/victordg0223)
- [@ojuras](https://github.com/oJuras)

## 📞 Suporte

email: miwoadm@gmail.com
whatsapp: (11)91199-3949
Para dúvidas ou sugestões, abra uma issue no repositório do GitHub.

---

✨ **Marketplace Místico** - Conectando o mundo espiritual através da tecnologia
