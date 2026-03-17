# Runbook de Incidentes - Ciclo 2

## Escopo
Este runbook cobre incidentes de webhook EFI, conciliacao diaria e fila operacional.

## Dados minimos antes de agir
- Correlation id da requisicao afetada.
- Codigo do alerta (`WEBHOOK_*` ou `RECONCILIATION_*`).
- Janela de tempo do incidente.
- Impacto observado (pedidos, pagamentos ou repasses).

## Fluxo de resposta
1. Confirmar alerta via `GET /api/observability/alerts`.
2. Coletar evidencias em `webhook_events`, `payments`, `orders` e `reconciliation_runs`.
3. Executar mitigacao correspondente.
4. Registrar acao no canal interno e abrir post-mortem se severidade `high`.

## Procedimentos por alerta

### WEBHOOK_ERROR_SPIKE
1. Consultar eventos recentes em `webhook_events` com `status = failed`.
2. Verificar `last_error` e agrupar por causa raiz.
3. Executar retry controlado:
   - `POST /api/webhooks/efi/retry` com `x-webhook-ops-secret`.
4. Monitorar reducao do erro por 30 minutos.

### WEBHOOK_QUEUE_STUCK
1. Listar eventos `processing` com `locked_at` antigo.
2. Validar se ha deploy em andamento ou degradacao de banco.
3. Rodar replay manual por evento:
   - `POST /api/webhooks/efi/reprocess`.
4. Se continuar travando, pausar entrada de eventos e escalar para plataforma.

### RECONCILIATION_NOT_RUN
1. Verificar agenda do job diario.
2. Executar conciliacao manual:
   - `POST /api/finance/reconciliation/daily`.
3. Validar registro em `reconciliation_runs` com `status = completed`.

### RECONCILIATION_RUN_INCOMPLETE
1. Abrir ultimo `reconciliation_runs`.
2. Corrigir dependencia indisponivel (banco, timeout, credencial).
3. Reexecutar `POST /api/finance/reconciliation/daily`.

### RECONCILIATION_STALE
1. Confirmar ultima data executada.
2. Reexecutar conciliacao para a data pendente.
3. Ajustar automacao para evitar nova lacuna.

### RECONCILIATION_ISSUES_DETECTED
1. Consultar `reconciliation_issues` por severidade.
2. Priorizar `high` e tratar cada desvio.
3. Registrar plano de correcao e prazo por tipo de divergencia.

## Criterio de encerramento
- Alerta deixa de aparecer na consulta operacional.
- Impacto ao cliente foi mitigado.
- Acao registrada com responsavel e horario.
