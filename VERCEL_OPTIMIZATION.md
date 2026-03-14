# Vercel API Refactor

## Objetivo
Reduzir complexidade operacional e consumo de compute na Vercel mantendo as regras de negocio atuais.

## O que mudou
- Todas as rotas `/api/*` agora passam por uma unica funcao: `api/index.js`.
- A logica da API foi movida para `backend/` (nao exposto como funcoes da Vercel).
- O roteamento interno foi centralizado em `backend/routes.js`.
- Rotas publicas de leitura recebem cache no edge (`Cache-Control`) para reduzir hits no origin:
  - `/api/health`
  - `/api/products`
  - `/api/products/:id`
  - `/api/sellers/:id`
- `vercel.json` foi simplificado para:
  - uma configuracao de funcao (`api/index.js`)
  - dois rewrites de API (`/api` e `/api/(.*)`)
  - rewrites enxutos para frontend estatico

## Beneficios praticos
- Menos pontos para manter em deploy e debug.
- Menor risco de drift entre rewrites e arquivos.
- Melhor reaproveitamento de codigo e cache de handlers em runtime.
- Menos chamadas ao backend para endpoints publicos por causa de cache no edge.

## Como adicionar nova rota agora
1. Crie/ajuste o handler em `backend/...`.
2. Registre a rota no array de `backend/routes.js`.
3. Deploy.

Nao e mais necessario mexer em varios rewrites para cada endpoint.
