# Configuracao de Alertas Operacionais

## Objetivo
Padronizar os gatilhos de alerta para reduzir tempo de deteccao de falhas criticas.

## Endpoint de consulta
- `GET /api/observability/alerts`
- Autorizacao: usuario interno (`operator` ou `admin`) via JWT.
- Segredo opcional adicional: `ALERTS_SECRET` enviado em `x-alerts-secret`.

## Variaveis de ambiente
- `ALERT_WEBHOOK_FAILED_THRESHOLD` (default: `5`)
- `ALERT_WEBHOOK_FAILED_WINDOW_MINUTES` (default: `30`)
- `ALERT_WEBHOOK_STUCK_MINUTES` (default: `15`)
- `ALERT_RECONCILIATION_STALE_DAYS` (default: `0`)
- `ALERTS_SECRET` (opcional, recomendado em producao)

## Codigos de alerta
- `WEBHOOK_ERROR_SPIKE`: pico de falhas de webhook no periodo configurado.
- `WEBHOOK_QUEUE_STUCK`: eventos travados em `processing` acima do limite.
- `RECONCILIATION_NOT_RUN`: nenhuma conciliacao registrada.
- `RECONCILIATION_RUN_INCOMPLETE`: ultima conciliacao com status diferente de `completed`.
- `RECONCILIATION_STALE`: conciliacao atrasada alem do limite.
- `RECONCILIATION_ISSUES_DETECTED`: divergencias detectadas no relatorio diario.

## Simulacao rapida de disparo
1. Force erro de webhook EFI para gerar `failed` na `webhook_events`.
2. Consulte `GET /api/observability/alerts`.
3. Valide se o codigo esperado aparece no retorno com contexto suficiente para acao.
