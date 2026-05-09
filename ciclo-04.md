# Ciclo 04 — Preparacao de Deploy e Primeiros Testes em Producao

> Objetivo macro: colocar a aplicacao em producao com seguranca e observabilidade, iniciando com rollout controlado e capacidade de rollback rapido.

## Decisao de stack de deploy (proposta)
## Banco: Neon
- **Faz sentido** para Postgres serverless, bom para ambiente com workloads variaveis.
- Recomendacoes:
  - habilitar connection pooling;
  - separar branch/projeto de banco para staging e prod;
  - politica de backup e restore testada.

## Backend: Fly.io
- **Faz sentido** para backend Node com controle maior de runtime/region.
- Recomendacoes:
  - usar 2 instancias minimas (high availability basica);
  - configurar health check e auto-restart;
  - definir regiao proxima do publico principal.

## Frontend: Cloudflare (Pages)
- **Faz sentido** para frontend estatico com CDN global.
- Recomendacoes:
  - cache por rota/asset;
  - invalidacao por deploy;
  - variaveis de ambiente separadas por stage.

## Veredito da arquitetura proposta
**Neon + Fly.io + Cloudflare e uma combinacao valida** para o seu caso. 

Stack escolhida: Fly.io (backend) + Cloudflare Pages (frontend) + Neon (banco).

---

## Sprints do Ciclo 04

## Sprint 01 — Infra baseline (staging + prod)
**Entregaveis**
- Contas/projetos separados: staging e producao.
- Provisionamento:
  - Neon (2 branches: staging/prod)
  - Fly app(s) backend staging/prod
  - Cloudflare Pages staging/prod
- DNS e TLS configurados.
- Segredos por ambiente cadastrados.

**Especificacoes tecnicas minimas**
- Variaveis obrigatorias:
  - `DATABASE_URL`, `JWT_SECRET`, `ALLOWED_ORIGIN`
  - `EFI_*`, `MELHOR_ENVIO_*`
  - `FINANCE_OPS_SECRET`, `METRICS_SECRET`, `ALERTS_SECRET`
- Politica de segredo: rotacao trimestral + mascaramento em logs.

**Teste da sprint**
- `GET /api/health` em staging/prod.
- Validacao CORS com dominio do frontend.
- Teste de conexao DB + migracao em staging.

---

## Sprint 02 — Pipeline CI/CD e governanca de release
**Entregaveis**
- Pipeline com gates:
  - lint
  - test:unit
  - test:integration
  - test:e2e (release)
- Deploy automatico para staging em merge para `develop`.
- Deploy manual aprovado para producao em merge `develop -> main`.
- Checklist de release integrado ao PR template.

**Teste da sprint**
- Simulacao de PR com falha de teste bloqueando merge.
- Simulacao de release com aprovacao manual.
- Confirmacao de artefato/versionamento no deploy.

---

## Sprint 03 — Observabilidade e operacao em producao
**Entregaveis**
- Dashboard basico:
  - latencia p95
  - erro por endpoint
  - throughput
  - alertas de webhook/reconciliacao
- Alertas conectados ao canal operacional (Slack/Discord/email).
- Runbook validado com exercicio de incidente (game day leve).

**Teste da sprint**
- Disparo controlado de alerta (webhook failed spike).
- Simulacao de reconciliacao atrasada e validacao do alerta.
- Verificacao de correlation-id ponta a ponta.

---

## Sprint 04 — Hardening de producao + rollout controlado
**Entregaveis**
- WAF/rate-limit (na borda e/ou backend).
- Politica de timeout/retry por integracao externa.
- Plano de rollback (app + migracoes).
- Rollout por ondas:
  - onda 1: trafego interno
  - onda 2: 5-10% usuarios
  - onda 3: 100% se metricas saudaveis

**Teste da sprint**
- Smoke + load basico em staging e pre-prod.
- Drill de rollback completo.
- Check de SLO inicial (ex: 99.5% uptime mensal).

---

## Checklist de publicacao (resumo executavel)
1. Migracoes aplicadas sem erro (`db:migrate`).
2. Variaveis de ambiente revisadas por ambiente.
3. Endpoints internos protegidos (RBAC + secrets).
4. Smoke test aprovado.
5. Load test dentro do limite de erro.
6. Alertas ativos com responsavel de plantao.
7. Plano de rollback testado nas ultimas 2 semanas.
8. Go-live aprovado por tecnico + produto.

---

## Custos e operacao (direcao)
- **Neon:** custo por armazenamento/compute e conexoes; monitorar picos de conexao.
- **Fly.io:** custo por maquina/escala; comecar pequeno e subir por metrica.
- **Cloudflare:** excelente custo-beneficio para frontend estatico + CDN.

## Risco principal do Ciclo 04
Entrar em producao sem observabilidade operacional madura.

## Mitigacao principal
Nao liberar 100% do trafego sem:
- alertas validados,
- runbook ensaiado,
- rollback comprovado.
