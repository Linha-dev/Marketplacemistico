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

### Backend
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

### 🐘 Deploy no Hostinger (ou qualquer hosting convencional)

> Deploy em **Hostinger**, **KingHost**, **LocaWeb**, e outros hostings que oferecem PHP + MySQL.

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

## 📁 Estrutura do Projeto

```
Marketplacemistico/
├── php/                      # Backend PHP (deploy Hostinger)
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
├── schema.sql               # Schema PostgreSQL (VPS)
└── scripts/
    └── new-sprint-branch.ps1 # Automação de branches
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

## 💾 Banco de Dados — Scripts SQL e Conexão

### Qual arquivo de schema usar?

| Cenário | Arquivo | Banco |
|---|---|---|
| Hostinger shared hosting | `php/schema_mysql.sql` | MySQL 5.7+ / MariaDB 10.x |
| VPS com PostgreSQL | `schema.sql` (raiz) | PostgreSQL 14+ |

### `php/schema_mysql.sql` — Schema MySQL (Hostinger)

Cria as seguintes tabelas (nesta ordem, respeitando as foreign keys):

| Tabela | Descrição |
|---|---|
| `users` | Usuários do sistema (compradores e vendedores). Armazena email, senha (bcrypt), Google ID, CPF/CNPJ e telefone. |
| `sellers` | Perfil da loja de cada vendedor: nome, categoria, taxa de comissão, modo de repasse (`manual` ou automático via EFI). |
| `seller_billing_profiles` | Dados bancários do vendedor para repasse: banco, agência, conta, chave Pix. |
| `seller_shipping_profiles` | Endereço de origem do vendedor para cotação de frete (CEP, cidade, estado). |
| `addresses` | Endereços de entrega dos compradores. |
| `products` | Catálogo de produtos: nome, categoria, preço, estoque, imagem, dimensões e peso (para frete). |
| `shipping_quotes` | Cotações de frete geradas pelo Melhor Envio, válidas por tempo limitado. |
| `orders` | Pedidos realizados: subtotal, frete, desconto, grand total, status de pagamento e envio. |
| `order_items` | Itens de cada pedido: snapshot do nome, preço e dimensões no momento da compra. |
| `payments` | Registros de pagamentos gerados (EFI/Pix). Contém status (`pending`, `approved`, `refused`, etc.) e resposta bruta do gateway. |
| `refunds` | Solicitações de reembolso associadas a um pagamento. |
| `payment_splits` | Divisão financeira de cada pagamento: valor bruto, taxa de plataforma, taxa do gateway, valor líquido do vendedor. |
| `finance_ledger` | Livro-caixa da plataforma: entradas e saídas por pedido/pagamento. |
| `manual_payouts` | Repasses manuais pendentes para vendedores. |
| `webhook_events` | Log de todos os webhooks recebidos (EFI, Melhor Envio) com controle de idempotência. |
| `shipments` | Envios criados no Melhor Envio: ID do envio, código de rastreamento, URL da etiqueta. |
| `shipment_events` | Histórico de eventos de rastreamento de cada envio. |
| `rate_limits` | Controle de rate limiting por IP/rota (sem Redis — usa o próprio banco). |

> **Charset e collation:** todas as tabelas usam `utf8mb4` + `utf8mb4_unicode_ci` para suporte a emojis e acentos corretamente.

#### Como importar via phpMyAdmin (Hostinger)

1. Acesse o **hPanel** → **Databases → phpMyAdmin**
2. No menu lateral, selecione o banco de dados que você criou
3. Clique na aba **Importar** (Import)
4. Em *File to import*, clique em **Choose File** e selecione `php/schema_mysql.sql`
5. Certifique-se de que o charset está como **utf8** ou **utf8mb4**
6. Clique em **Go** (Executar)
7. Você deve ver uma mensagem de sucesso e as 17 tabelas listadas no menu lateral

#### Como importar via SSH (planos Business/Cloud)

```bash
mysql -u SEU_USUARIO -p SEU_BANCO < php/schema_mysql.sql
```

#### Re-executar / resetar o schema

O script começa com `DROP TABLE IF EXISTS` em ordem reversa de dependência, portanto é **seguro re-executar** — ele apaga e recria todas as tabelas. **Atenção: isso apaga todos os dados.**

---

### Conexão com o banco de dados

#### Localizar as credenciais no Hostinger (hPanel)

1. Acesse **hPanel → Databases → MySQL Databases**
2. Anote:
   - **Database name** (ex: `u123456789_mistico`)
   - **Username** (ex: `u123456789_user`)
   - **Host** — em shared hosting é **sempre `localhost`**
   - **Port** — padrão MySQL: **`3306`**
   - A senha você definiu ao criar o usuário (pode ser redefinida no painel)

> ⚠️ No Hostinger shared hosting o host é sempre `localhost`. Nunca use o IP externo do servidor para conexão MySQL local.

#### Configuração no `.env`

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_mistico
DB_USER=u123456789_user
DB_PASS=sua_senha_aqui
```

O arquivo `php/src/Database.php` monta o DSN PDO automaticamente:

```
mysql:host=localhost;port=3306;dbname=u123456789_mistico;charset=utf8mb4
```

#### Conexão com PostgreSQL (VPS Hostinger)

Se você tiver um VPS com PostgreSQL instalado:

```env
DB_DRIVER=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=nome_do_banco
DB_USER=postgres
DB_PASS=sua_senha
```

Use o schema da raiz do projeto (`schema.sql`) em vez do `schema_mysql.sql`.

#### Verificar a conexão

Depois de configurar o `.env`, acesse:

```
https://seu-dominio.com/api/health
```

Resposta esperada:
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "database": "connected",
    "php": "8.x.x",
    "timestamp": "2025-..."
  }
}
```

Se `"database"` aparecer como `"error"`, verifique as credenciais no `.env` e se o banco foi criado corretamente.

#### Troubleshooting de conexão

| Erro | Causa provável | Solução |
|---|---|---|
| `Access denied for user` | Usuário ou senha incorretos | Redefina a senha no hPanel → MySQL Databases |
| `Unknown database` | Nome do banco errado | Verifique `DB_NAME` — no Hostinger costuma ter prefixo (`u123456_`) |
| `Can't connect to MySQL server` | Host incorreto | Use `localhost` (não o IP do servidor) |
| `Table ... doesn't exist` | Schema não foi importado | Reimporte `php/schema_mysql.sql` via phpMyAdmin |
| `could not find driver` | Extensão PDO não habilitada | Contate o suporte Hostinger ou ative via `php.ini` |

---

### Modelo de Dados

#### Principais Tabelas
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

<a href="https://github.com/victordg0223">@victordg0223</a>
&nbsp;
<a href="https://github.com/oJuras">@ojuras</a>

## 📞 Suporte

email: miwoadm@gmail.com
whatsapp: (11)91199-3949
Para dúvidas ou sugestões, abra uma issue no repositório do GitHub.

---

✨ **Marketplace Místico** - Conectando o mundo espiritual através da tecnologia
