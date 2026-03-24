# Playbook — Criacao de Novos Endpoints no Padrao Modular

## 1) Escolha do dominio
- Reutilize dominio existente em `backend/modules/<dominio>` quando fizer sentido.
- Crie novo dominio apenas quando houver fronteira de negocio clara.

## 2) Implementacao (ordem recomendada)
1. `schemas.js` com sanitizacao/validacao.
2. `repository.js` com queries e adaptadores de transacao.
3. `service.js` com regras e orquestracao.
4. `controller.js` com mapeamento HTTP e erros.
5. Wrapper em `backend/...` mantendo middleware e rota.

## 3) Padrao de erro
- Lance erros de negocio no service com `error.code`.
- Controller traduz `error.code` para status HTTP.
- Mensagens devem ser objetivas e sem vazar detalhes sensiveis.

## 4) Seguranca minima
- Endpoints internos: `requireInternalRole` + segredo operacional quando aplicavel.
- Endpoints de usuario: `requireAuth`/`requireVendedor` conforme escopo.
- Nunca aceitar SQL dinamico sem parametros.

## 5) Observabilidade
- Preserve logs e metricas existentes em fluxos sensiveis.
- Em endpoints de operacao, inclua contexto minimo para troubleshooting.

## 6) Validacao final
Executar:

```bash
npm test -- --runInBand
npm run lint
```

## 7) Criterio de aceite
- Sem quebra de contrato da API.
- Testes verdes.
- Documentacao de impacto no PR.
