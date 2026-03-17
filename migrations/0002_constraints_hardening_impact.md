# Impacto em Dados Legados - 0002_constraints_hardening

Esta migracao endurece validacoes para pagamentos, pedidos e envios.

## Mudancas com impacto potencial

- `orders.status` passa a ser `NOT NULL`.
- `CHECK` em `orders.status`, `orders.payment_status`, `orders.shipping_status`.
- `CHECK` de totais nao negativos em `orders`.
- `CHECK` em `payments.status`, `payments.amount` e presenca de `order_id`.
- `CHECK` em `shipments.status` e `shipments.provider`.
- `CHECK` em `shipping_quotes`, `order_items`, `payment_splits`, `manual_payouts`.
- Indice unico parcial em `payments(provider, provider_charge_id)`.
- Indice unico parcial em `webhook_events(provider, external_id, event_type)`.
- Indice unico parcial em `shipments(melhor_envio_shipment_id)`.

## Tratamento de legado incluido na migracao

- Status vazios/nulos sao normalizados para defaults (`pending`/`pendente`).
- Duplicidades em `payments.provider_charge_id` sao mantidas, mas linhas antigas perdem `provider_charge_id` para permitir unicidade futura.
- Duplicidades em `shipments.melhor_envio_shipment_id` sao mantidas, mas linhas antigas perdem `melhor_envio_shipment_id`.
- Duplicidades em `webhook_events` sao removidas mantendo o registro mais recente.

## Queries de verificacao pos-migracao

```sql
-- Pagamentos com status fora do padrao
SELECT id, status FROM payments
WHERE status NOT IN ('pending', 'approved', 'failed', 'refunded', 'partially_refunded', 'cancelled');

-- Pedidos com status fora do padrao
SELECT id, status, payment_status, shipping_status FROM orders
WHERE status NOT IN ('pendente', 'confirmado', 'enviado', 'entregue', 'cancelado')
   OR payment_status NOT IN ('pending', 'approved', 'failed', 'refunded', 'partially_refunded', 'cancelled')
   OR shipping_status NOT IN ('pending', 'label_generated', 'posted', 'in_transit', 'delivered', 'cancelled', 'returned');

-- Duplicidade residual de provider_charge_id (deve ser 0)
SELECT provider, provider_charge_id, COUNT(*)
FROM payments
WHERE provider_charge_id IS NOT NULL
GROUP BY provider, provider_charge_id
HAVING COUNT(*) > 1;
```

## Acao recomendada de operacao

- Rodar `npm run db:migrate` em homologacao antes de producao.
- Executar as queries acima e salvar evidencias.
- Se houver dados inconsistentes, corrigir antes de validar constraints (`VALIDATE CONSTRAINT`) em janela controlada.
