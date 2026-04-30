# Checklist de Refinamento para MVP — Marketplace Místico

> Auditoria realizada em 29/04/2026. Cada item tem arquivo e linha onde o problema foi identificado.
> Organizado por severidade: 🔴 bloqueador de deploy · 🟡 importante · 🟢 melhoria futura.

---

## 🔴 BLOQUEADORES — Não publicar sem resolver

### 1. Console.log expondo internals do backend

O backend tem ~29 ocorrências de `console.log`/`console.error` que podem vazar queries SQL, emails de usuários, stack traces e detalhes de configuração nos logs da Vercel (visíveis no dashboard e potencialmente em integrações externas).

| Arquivo | Problema |
|---|---|
| `backend/db.js` linhas 37–39 | Loga a query SQL completa + parâmetros — pode expor dados sensíveis |
| `backend/auth/login.js` linhas 14, 25, 35, 40–50, 72, 91 | Loga email, status de login e detalhes de falha |
| `backend/auth-middleware.js` linha 12 | Loga "ERRO CRITICO: JWT_SECRET nao configurada" — revela configuração interna |
| `backend/auth/callback/google.js` linhas 42, 125 | Loga erros de OAuth com detalhes sensíveis |
| `public/app.js` (20+ ocorrências) | Console.log no cliente — visível para qualquer usuário no DevTools |

**O que fazer:**
- Remover todos os `console.log` e `console.error` diretos do backend antes do deploy.
- O único canal de log deve ser `backend/observability/logger.js` com mensagens sanitizadas.
- Em `backend/db.js`, substituir por: `console.error('Database error:', error.message)` — sem query, sem params.
- No frontend (`public/app.js`), remover todos ou envolver em `if (window.DEBUG_MODE) { ... }`.

---

### 2. CORS aberto por padrão

**Arquivo:** `backend/middleware.js` linha 20

```js
// ATUAL — problema
const allowedOrigin = process.env.ALLOWED_ORIGIN || '*';
```

Se a variável `ALLOWED_ORIGIN` não estiver configurada na Vercel, o servidor aceita requisições de qualquer origem, incluindo sites maliciosos que queiram chamar a API em nome do usuário logado.

**O que fazer:**
```js
// CORRETO
const allowedOrigin = process.env.ALLOWED_ORIGIN;
if (!allowedOrigin) throw new Error('ALLOWED_ORIGIN não configurada');
```

Garantir que `ALLOWED_ORIGIN=https://quintalmistico.com.br` está nas variáveis de produção na Vercel.

---

### 3. Rate limiting inexistente

Nenhum endpoint tem proteção contra requisições em massa. Os mais críticos:

| Endpoint | Risco |
|---|---|
| `POST /api/auth/login` | Força bruta de senha — sem limite de tentativas |
| `POST /api/auth/register` | Spam de criação de contas |
| `POST /api/orders` | Flood de pedidos falsos |
| `GET /api/products?search=` | Stress no banco com buscas repetitivas |
| `POST /api/payments/create` | Abuso de tentativas de pagamento |

**O que fazer:**

Instalar `express-rate-limit`:
```bash
npm install express-rate-limit
```

Aplicar nos endpoints críticos em `api/index.js` ou no middleware global:
```js
import rateLimit from 'express-rate-limit';

const authLimiter = rateLimit({
  windowMs: 5 * 60 * 1000, // 5 minutos
  max: 5,                   // 5 tentativas
  message: { error: 'Muitas tentativas. Aguarde alguns minutos.' }
});
```

> **Atenção Vercel:** rate limit em memória não funciona entre múltiplas instâncias serverless. Para MVP, o limite por IP já ajuda bastante. Para produção real, usar Vercel KV como store compartilhado.

---

### 4. XSS no frontend — innerHTML sem escape

**Arquivo:** `public/app.js`

O frontend injeta dados vindos da API diretamente no HTML via `innerHTML` e template literals sem sanitização. Se a API retornar um valor como `<img src=x onerror=alert(document.cookie)>`, ele é executado no navegador do usuário.

Ocorrências principais:
- Linhas 1108–1121: `${product.nome}` e `${product.descricao}` em cards de produto
- Linhas 1271–1285: `${item.nome}` e `${item.nome_loja}` no carrinho
- Linha 707: `${message}` em mensagens de erro
- Linha 81: `<img src="${product.imagem_url}">` sem validação

**O que fazer:**

Adicionar uma função de escape e usá-la em todo lugar que renderiza dados da API:
```js
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Em vez de: `<h3>${product.nome}</h3>`
// Usar:      `<h3>${escapeHtml(product.nome)}</h3>`
```

Aplicar em **todos** os campos vindos da API antes de colocar no innerHTML.

---

### 5. Webhooks sem validação obrigatória de assinatura

**Arquivo:** `backend/modules/webhooks/core/controller.js` linhas 30–35 e 88–94

```js
// ATUAL — problema
const webhookSecret = process.env.EFI_WEBHOOK_SECRET;
if (webhookSecret) {  // ← se não configurado, aceita tudo
  const receivedSecret = sanitizeWebhookSecretHeader(req.headers);
  if (receivedSecret !== webhookSecret) { ... }
}
```

Se `EFI_WEBHOOK_SECRET` e `MELHOR_ENVIO_WEBHOOK_SECRET` não estiverem configuradas, qualquer pessoa pode chamar o endpoint de webhook e simular pagamentos confirmados ou atualizações de frete.

**O que fazer:**
```js
// CORRETO — rejeitar se secret não configurado
const webhookSecret = process.env.EFI_WEBHOOK_SECRET;
if (!webhookSecret) {
  throw new Error('EFI_WEBHOOK_SECRET é obrigatória');
}
const receivedSecret = sanitizeWebhookSecretHeader(req.headers);
if (receivedSecret !== webhookSecret) {
  return sendError(res, 'UNAUTHORIZED', 'Webhook não autorizado', 401);
}
```

Mesmo fix para Melhor Envio. Garantir que as secrets estão configuradas na Vercel antes do deploy.

---

### 6. JWT com expiração de 7 dias sem refresh token

**Arquivos:** `backend/auth/login.js` linha 58, `backend/auth/register.js` linha 134

Token com 7 dias significa que se um token vazar (XSS, log, etc.), o atacante tem acesso por uma semana inteira sem que o usuário possa revogar.

**O que fazer para MVP:**
- Reduzir para `expiresIn: '2h'` como mínimo aceitável.
- Implementar `POST /api/auth/refresh` que emite novo token (usando um refresh token de longa duração armazenado no banco).
- Sem refresh token: avisar o usuário que vai precisar logar de novo a cada 2 horas (aceitável para MVP).

---

### 7. Stack traces chegando ao cliente nos logs

**Arquivo:** `backend/observability/logger.js` linha 21

```js
// ATUAL — problema
error: {
  message: error?.message,
  stack: error?.stack || null  // ← expõe caminhos internos do servidor
}
```

Stack traces contêm caminhos de arquivo do servidor (ex: `/var/task/backend/db.js:37`), nomes de funções internas e às vezes valores de variáveis. Isso é útil para debug mas não deve ir para logs de produção acessíveis externamente.

**O que fazer:**
```js
// CORRETO
error: {
  message: error?.message,
  code: error?.code || null
  // remover stack
}
```

---

### 8. Headers de segurança incompletos

**Arquivo:** `backend/middleware.js`

Já implementados corretamente: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`.

Faltam:
```js
// Adicionar em backend/middleware.js
res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; img-src 'self' https: data:");
res.setHeader('X-Permitted-Cross-Domain-Policies', 'none');
```

> O HSTS (Strict-Transport-Security) é especialmente importante — garante que navegadores só acessem o site via HTTPS, mesmo que o usuário tente HTTP.

---

## 🟡 IMPORTANTES — Resolver antes de crescer

### 9. Token JWT no localStorage (vulnerável a XSS)

**Arquivo:** `public/app.js` linhas 792–794

```js
localStorage.setItem('authToken', authToken);
localStorage.setItem('currentUser', JSON.stringify(userData));
```

`localStorage` é acessível por qualquer JavaScript da página. Se o site sofrer XSS (ver item 4), o token pode ser roubado. O padrão mais seguro é usar **HttpOnly cookies**, que o JavaScript não consegue ler.

**O que fazer:**
- Curto prazo: resolver o XSS (item 4) mitiga o risco do localStorage.
- Médio prazo: migrar para HttpOnly cookies configurados pelo backend:
  ```js
  res.setHeader('Set-Cookie', `token=${jwt}; HttpOnly; Secure; SameSite=Strict; Path=/`);
  ```

---

### 10. Sanitização de inputs incompleta em alguns módulos

`backend/sanitize.js` existe e está bem implementado, mas não é aplicado em todos os lugares:

| Endpoint | Campo faltando sanitização |
|---|---|
| `backend/users/addresses/` | `cep`, `rua`, `numero`, `complemento`, `cidade`, `estado` |
| `GET /api/products?search=` | Sem limite de tamanho — aceita strings de qualquer tamanho |
| `backend/sanitize.js` linha 100 | `nome_loja` sem limite de caracteres definido (sugere 255) |

**O que fazer:**
- Aplicar `sanitizeString()` em todos os campos de endereço antes do INSERT.
- Limitar `search` a 200 caracteres: `if (search.length > 200) return sendError(...)`.
- Definir `maxLength: 255` para `nome_loja`.

---

### 11. Imagem de produto sem validação de URL

**Arquivo:** `backend/modules/products/catalog/service.js`

O campo `imagem_url` aceita qualquer string. Um vendedor malicioso pode colocar uma URL de rastreamento, um script ou uma URL interna da rede.

**O que fazer:**

Adicionar em `backend/sanitize.js`:
```js
export function validateImageUrl(url) {
  if (!url) return { ok: true, value: '' };
  if (!url.startsWith('https://')) return { ok: false, reason: 'URL deve ser HTTPS' };
  try {
    new URL(url); // valida formato
  } catch {
    return { ok: false, reason: 'URL inválida' };
  }
  return { ok: true, value: url };
}
```

---

### 12. Variáveis de ambiente sem validação de startup

Não há verificação de variáveis obrigatórias no momento que o servidor inicia. Se uma variável estiver faltando, o erro só aparece quando o endpoint é chamado, não na inicialização.

**O que fazer:**

Criar `backend/startup-check.js`:
```js
const required = ['DATABASE_URL', 'JWT_SECRET', 'ALLOWED_ORIGIN'];
for (const key of required) {
  if (!process.env[key]) {
    throw new Error(`Variável de ambiente obrigatória não configurada: ${key}`);
  }
}
```

Importar e executar no topo de `api/index.js`.

---

### 13. Sem proteção CSRF em operações de estado

Não há tokens CSRF. Como o frontend usa `localStorage` (não cookies) para o token JWT, o risco de CSRF clássico é menor — mas operações sensíveis como `POST /api/orders` e `POST /api/payments/create` poderiam se beneficiar de um token de dupla submissão.

**O que fazer para MVP:**
- O uso de `Authorization: Bearer <token>` no header já mitiga CSRF clássico (cookies não são enviados automaticamente).
- Garantir que **nenhum** endpoint aceite o token JWT via cookie ou query string — apenas header `Authorization`.

---

### 14. Idempotência em pagamentos

**Arquivo:** `backend/payments/`

Se o usuário clicar duas vezes em "Pagar", ou se houver retry do frontend por timeout, dois pedidos de cobrança podem ser criados.

**O que fazer:**
- Gerar um `idempotency_key` único no frontend (UUID v4) ao montar o formulário de pagamento.
- Enviar no header `Idempotency-Key` da requisição.
- O backend verifica se já existe cobrança com essa chave antes de criar uma nova.

---

### 15. Dependências com vulnerabilidades conhecidas

**O que fazer:**
```bash
npm audit
npm audit fix
```

Revisar o relatório e atualizar pacotes com vulnerabilidades críticas ou altas antes do deploy de produção.

---

## 🟢 MELHORIAS FUTURAS — Após MVP

### 16. Assinatura HMAC para webhooks

O método atual compara um secret em plain text no header. O padrão mais seguro (usado por Stripe, GitHub) é HMAC-SHA256 do body:

```js
import crypto from 'crypto';
const signature = req.headers['x-efi-signature'];
const hash = crypto.createHmac('sha256', webhookSecret)
  .update(JSON.stringify(req.body))
  .digest('hex');
if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(hash))) {
  return sendError(res, 'UNAUTHORIZED', '', 401);
}
```

### 17. Replay attack em webhooks

Adicionar validação de timestamp no webhook: se o evento tem mais de 5 minutos, rejeitar. Evita que alguém capture e reenvie um webhook antigo.

### 18. Refresh tokens com rotação

Implementar refresh tokens de uso único armazenados no banco (`refresh_tokens` table), com rotação automática a cada uso. Isso permite revogar sessões individuais.

### 19. Rate limiting distribuído com Vercel KV

O rate limiting em memória não funciona quando a Vercel sobe múltiplas instâncias da função. Para produção com tráfego real, usar Vercel KV (Redis) como store do rate limiter.

### 20. Verificação de email no cadastro

Atualmente qualquer email é aceito sem verificação. Adicionar um passo de confirmação por email reduz spam e garante que o usuário tem acesso à conta.

### 21. Mascaramento de dados sensíveis nos logs

Implementar mascaramento de email nos logs de observabilidade:
```js
// john.doe@gmail.com → j***@gmail.com
function maskEmail(email) {
  const [user, domain] = email.split('@');
  return `${user[0]}***@${domain}`;
}
```

### 22. Versionamento de API

Adicionar prefixo `/api/v1/` para permitir mudanças breaking no futuro sem derrubar clientes antigos.

---

## Checklist Pré-Deploy de Produção

```
Bloqueadores
[ ] Remover todos console.log do backend (29 ocorrências)
[ ] Remover console.log do public/app.js
[ ] Configurar ALLOWED_ORIGIN como obrigatório (não aceitar '*')
[ ] Implementar rate limiting em /auth/login e /auth/register
[ ] Tornar EFI_WEBHOOK_SECRET e MELHOR_ENVIO_WEBHOOK_SECRET obrigatórias
[ ] Adicionar escapeHtml() em todos os innerHTML do frontend
[ ] Reduzir expiração do JWT de 7d para 2h
[ ] Remover stack traces do logger.js
[ ] Adicionar headers HSTS e Content-Security-Policy

Importantes
[ ] Sanitizar campos de endereço antes do INSERT
[ ] Adicionar limite de 200 chars no campo search
[ ] Validar imagem_url (HTTPS obrigatório)
[ ] Criar startup-check.js para variáveis obrigatórias
[ ] Rodar npm audit e corrigir vulnerabilidades críticas
[ ] Confirmar que token JWT nunca é aceito via cookie ou query string

Verificação final
[ ] Testar login com credenciais erradas — mensagem não revela se é email ou senha
[ ] Testar endpoint de webhook sem secret configurado — deve retornar 401
[ ] Abrir DevTools no browser — nenhum console.log deve aparecer em produção
[ ] Inspecionar headers HTTP da resposta — HSTS e CSP presentes
[ ] Rodar npm audit — zero vulnerabilidades críticas
```
