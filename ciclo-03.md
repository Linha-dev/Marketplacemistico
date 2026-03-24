# Ciclo 03 — Reorganizacao de Arquitetura (MVC incremental por dominio)

> Objetivo macro: reduzir complexidade sem travar entrega, migrando gradualmente para um padrao unico de camadas (`controller/service/repository/schemas`) e preservando contratos da API.

## Premissas do ciclo
- Sem reescrita total (big-bang) para evitar regressao em fluxos criticos.
- Migracao por fatias verticais (dominio por dominio) com rollback simples.
- Cada sprint fecha com entregavel funcional + cobertura de testes.
- CI obrigatorio: lint + unit + integration (e e2e quando aplicavel).

## Definicao de pronto (DoD) por sprint
1. Contrato HTTP preservado (ou mudanca versionada/documentada).
2. Cobertura de testes da fatia migrada verde.
3. Logs e mensagens de erro equivalentes/compat.
4. Checklist de regressao da sprint executado.
5. Evidencia no PR (comandos, resultados e impacto).

---

## Sprint 01 — Fundacao arquitetural + piloto de perfil
**Contexto**
- Criar esqueleto de modulo e convencoes para todo o repo.
- Iniciar com dominio de menor risco operacional: `users/profile` e `sellers/me`.

**Entregaveis**
- Estrutura base:
  - `backend/modules/users/{controller,service,repository,schemas}.js`
  - `backend/modules/sellers/{controller,service,repository,schemas}.js`
- Adaptacao dos handlers atuais para controllers finos.
- Regras de negocio centralizadas em service.
- SQL movido para repository desses 2 dominios.
- Documento de convencoes (naming, fronteiras de camada, tratamento de erro).

**Teste da sprint**
- Unit: services e validators/schemas de users/sellers.
- Integration: `GET/PUT /api/users/profile`, `GET/PUT /api/sellers/me`.
- Regressao manual rapida no frontend para edicao de perfil.

**Criterio de saida**
- Frontend de perfil consegue operar com API sem alteracao de contrato.

---

## Sprint 02 — Dominio de catalogo (products + sellers listing)
**Contexto**
- Consolidar padrao em area de alto uso e baixo risco financeiro direto.

**Entregaveis**
- Migracao de:
  - `products/index`, `products/[id]`, `products/[id]/publish`
  - `sellers/[id]`, `sellers/me/products`
- Padronizacao de validacao de payload/filtros em `schemas`.
- Repository para consultas de listagem com pagina/filtros.

**Teste da sprint**
- Unit: regras de publicacao/ownership/validacao.
- Integration: CRUD/listagem/publicacao de produtos.
- Teste de cache-control e resposta publica (quando aplicavel).

**Criterio de saida**
- Mesmos endpoints, menor acoplamento handler-SQL e sem quebra de listagem.

---

## Sprint 03 — Dominio comercial (orders)
**Contexto**
- Migrar `orders` com foco em transacoes e consistencia de estoque.

**Entregaveis**
- Migracao de:
  - `orders/index`, `orders/[id]`, `orders/[id]/status`, `orders/[id]/post-sale`
- Camada `service` com orquestracao transacional explicita.
- Repository com consultas/updates de pedido e itens.
- Padronizacao de maquina de estados de pedido.

**Teste da sprint**
- Integration forte para criacao, status e pos-venda.
- Testes de concorrencia de estoque (sem overselling).
- Auditoria de transicao de status.

**Criterio de saida**
- Fluxos de pedido estaveis e sem regressao de concorrencia.

---

## Sprint 04 — Dominio de pagamentos (sensivel)
**Contexto**
- Migracao cuidadosa de `payments/create` e `payments/refund`.

**Entregaveis**
- Controllers finos para pagamentos.
- Services encapsulando maquina de estado e refund.
- Repository para persistencia de pagamento/refund.
- Padrao unico para erros de negocio x erro tecnico.

**Teste da sprint**
- Unit: transicoes permitidas/bloqueadas.
- Integration: create payment, refund parcial/total e limites.
- Casos de falha de provider e idempotencia basica.

**Criterio de saida**
- Sem regressao financeira e sem alteracao indevida do contrato.

---

## Sprint 05 — Webhooks + fila de retry/reprocess (sensivel)
**Contexto**
- Migrar parte assincrona e de confiabilidade operacional.

**Entregaveis**
- Migracao de:
  - `webhooks/efi`, `webhooks/efi/retry`, `webhooks/efi/reprocess`
  - `webhooks/melhor-envio`
- Separacao clara entre parser, deduplicacao/idempotencia e efeitos colaterais.
- Repository para `webhook_events` e locks/retry.

**Teste da sprint**
- Integration: eventos duplicados, retries, replay manual, fila travada.
- Testes de idempotencia com repeticao do mesmo evento.
- Testes de seguranca dos endpoints ops.

**Criterio de saida**
- Processamento webhook deterministico com retry previsivel.

---

## Sprint 06 — Finance + reconciliacao + operacao interna
**Contexto**
- Consolidar dominio financeiro/ops com baixo risco de regressao.

**Entregaveis**
- Migracao de:
  - `finance/ledger/[orderId]`, `finance/reconciliation/daily`
  - `manual-payouts`, `observability/alerts`, `observability/metrics`
- Repository financeiro padronizado.
- Service unico para relatorio diario e anomalias.

**Teste da sprint**
- Integration: ledger, reconciliacao, payout manual.
- Unit: regras de classificacao de issues e calculos.
- Smoke de endpoints internos com auth/RBAC.

**Criterio de saida**
- Relatorios financeiros e operacionais equivalentes ao baseline atual.

---

## Sprint 07 — Hardening final + limpeza de legado
**Contexto**
- Remover duplicacoes antigas e consolidar padrao em todo backend.

**Entregaveis**
- Remocao de codigo legado substituido.
- Ajuste final de imports/roteamento para novos modulos.
- Documentacao final de arquitetura e mapa de dominios.
- Playbook de manutencao para novos endpoints no padrao.

**Teste da sprint**
- Suite completa (unit + integration + e2e quando houver).
- Smoke geral em ambiente de homologacao.
- Load basico nos endpoints mais criticos.

**Criterio de saida**
- Backend padronizado em MVC incremental, com baseline de regressao controlado.

---

## Riscos e mitigacoes do ciclo
- **Risco:** regressao silenciosa em fluxos financeiros.
  - **Mitigacao:** migrar pagos/webhooks so apos pilotos e com testes de contrato.
- **Risco:** ciclo longo demais.
  - **Mitigacao:** sprints curtas com valor entregavel e checkpoints de go/no-go.
- **Risco:** equipe perder contexto na mudanca.
  - **Mitigacao:** convencoes obrigatorias + templates de modulo + PR checklist.

## Meta de resultado do Ciclo 03
- Arquitetura previsivel por dominio.
- Menor acoplamento entre HTTP, regra e SQL.
- Facilidade real para continuar evolucao do produto sem aumentar caos estrutural.

## Progresso registrado
- Sprint 1 iniciada e aplicada em `users/profile` e `sellers/me`.
- Sprint 2 iniciada com migracao de `products/*`, `sellers/me/products` e `sellers/[id]` para modulo por camadas.
- Sprint 3 iniciada com migracao de `orders/*` para modulo por camadas.
- Sprint 4 iniciada com migracao de `payments/create` e `payments/refund` para modulo por camadas.
- Sprint 5 iniciada com migracao de `webhooks/efi`, `webhooks/efi/retry`, `webhooks/efi/reprocess` e `webhooks/melhor-envio` para modulo por camadas.
- Sprint 6 iniciada com migracao de `finance/ledger`, `finance/reconciliation/daily`, `manual-payouts/*` e `observability/{alerts,metrics}` para modulo por camadas.

- Sprint 7 iniciada com hardening final: consolidacao de padrao, playbook de novos endpoints e documentacao de arquitetura modular.
- Checklist de encerramento gerado em `docs/ops/checklist-encerramento-ciclo-03.md`.
