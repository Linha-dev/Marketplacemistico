# Arquitetura Modular do Backend (estado atual)

## Objetivo
Documentar a arquitetura-alvo aplicada no ciclo 03, para manter padrao unico de implementacao e onboarding rapido.

## Estrutura padrao por dominio
Cada dominio deve seguir o formato:

- `controller.js` — camada HTTP (req/res, status code, serializacao)
- `service.js` — regras de negocio e orquestracao
- `repository.js` — acesso a banco/queries/transacoes
- `schemas.js` — sanitizacao e validacao de entrada

## Dominios migrados
- `backend/modules/users/profile`
- `backend/modules/sellers/me`
- `backend/modules/sellers/public-profile`
- `backend/modules/products/catalog`
- `backend/modules/orders/core`
- `backend/modules/payments/core`
- `backend/modules/webhooks/core`
- `backend/modules/finance/ops`
- `backend/modules/observability/ops`

## Contratos de rota preservados
As rotas publicas e internas continuam sob os mesmos caminhos em `backend/*`, com wrappers finos para os controllers modulares.

## Regras de implementacao
1. Controller nao executa SQL.
2. Repository nao contem regra de negocio de fluxo.
3. Service nao manipula `res`/`req` diretamente.
4. Validacao de payload fica em `schemas`.
5. Erros de negocio devem ter `error.code` consistente.

## Erros e status HTTP
- `VALIDATION_ERROR` -> `400`
- `NOT_FOUND` -> `404`
- `FORBIDDEN` -> `403`
- `UNAUTHORIZED` -> `401`
- `METHOD_NOT_ALLOWED` -> `405`
- fallback -> `500`

## Checklist de revisao tecnica (PR)
- [ ] Endpoint manteve contrato existente
- [ ] Controller sem SQL
- [ ] Service com testes unitarios/integração cobrindo regras
- [ ] Repository com queries parametrizadas
- [ ] `npm test -- --runInBand` verde
- [ ] Logs/metricas operacionais preservados (quando aplicavel)
