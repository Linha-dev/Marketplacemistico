# Reorganizacao de Arquitetura - Guia de Decisao (2026-03-24)

## Contexto
O projeto cresceu com muitos endpoints e fluxos operacionais (pedidos, pagamentos, webhooks, conciliacao, observabilidade). A percepcao de complexidade e real e esperada neste estagio.

## Estado atual (objetivo)
- Roteamento centralizado por padroes em `backend/routes.js`.
- Handlers HTTP separados por dominio em `backend/*`.
- Regras de negocio importantes ja extraidas para `backend/services/*` (payments, shipping, finance, audit, webhooks).
- Ja existe disciplina de CI/processo para proteger release.

Conclusao: o codigo **ja esta parcialmente no espirito MVC em camadas**, mas sem uma convencao unica para todos os modulos.

## Vale migrar para MVC agora?
### Recomendacao curta
**Nao fazer migracao "big-bang" para MVC agora.**

### Por que
1. Alto risco de regressao em modulos sensiveis (pagamento, webhook e financeiro).
2. O projeto ja possui servicos e separacao parcial; o maior ganho vem de **padronizar o que ja existe**, nao de reescrever tudo.
3. O momento operacional sugere priorizar fechamento de pendencias de produto/go-live antes de reestrutura profunda.

## Estrategia recomendada: "MVC incremental por dominio"
Aplicar uma estrutura-alvo por contexto (orders, payments, webhooks, finance, sellers, users):

- `backend/modules/<dominio>/controller.js`  -> HTTP/req/res
- `backend/modules/<dominio>/service.js`     -> regras de negocio
- `backend/modules/<dominio>/repository.js`  -> acesso a banco
- `backend/modules/<dominio>/schemas.js`     -> validacoes/DTOs

Regra operacional:
- Handler atual vira "controller" fino.
- SQL sai gradualmente de handlers para repository.
- Orquestracao fica no service.
- Mudanca sempre acompanhada de teste do modulo.

## Sequencia pratica (sem travar entrega)
### Fase 0 (curta, 1-2 dias)
- Definir padrao de pasta/nomes para um dominio piloto.
- Adicionar um documento de convencoes de camada.

### Fase 1 (piloto, 1 sprint)
- Migrar **users/profile** e **sellers/me** (baixo risco relativo e dor real no fluxo de produto).
- Entregar sem alterar contrato de API.

### Fase 2 (core comercial, 1-2 sprints)
- Migrar `orders` e `products` para mesmo padrao.
- Garantir cobertura de regressao via testes de integracao ja existentes.

### Fase 3 (sensivel, 1-2 sprints)
- Migrar `payments`, `webhooks` e `finance` por fatias pequenas, com feature flags quando necessario.

## Criticos de sucesso
- Zero quebra de contrato publico de API.
- Testes unitarios/integracao verdes em toda migracao.
- Reducao de "SQL no handler" e aumento de reuso em service/repository.
- Tempo medio de onboarding e manutencao menor (meta qualitativa da equipe).

## Decisao recomendada para agora
1. **Sim** para reorganizacao arquitetural.
2. **Nao** para reescrita total imediata.
3. Comecar por piloto em perfil de usuario/vendedor e, em paralelo, fechar pendencias operacionais do ciclo 2.
