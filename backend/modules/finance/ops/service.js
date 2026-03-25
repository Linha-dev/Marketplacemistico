import {
  canViewOrderLedger,
  countManualPayouts,
  findLatestPaymentByOrderId,
  findManualPayoutForUpdate,
  insertManualPayoutAction,
  listManualPayouts,
  runInTransaction,
  updateManualPayout
} from './repository.js';
import {
  ACTION_TRANSITIONS,
  sanitizeManualPayoutActionPayload,
  sanitizeManualPayoutListQuery,
  sanitizeOrderId,
  sanitizeRunDate
} from './schemas.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function getOrderLedgerForUser(userId, rawOrderId) {
  const orderId = sanitizeOrderId(rawOrderId);
  if (!orderId) {
    throw createBusinessError('VALIDATION_ERROR', 'orderId invalido');
  }

  const permissionRows = await canViewOrderLedger(orderId, userId);

  if (permissionRows.length === 0) {
    throw createBusinessError('FORBIDDEN', 'Sem permissao para visualizar ledger do pedido');
  }

  const { getOrderLedgerSummary } = await import('../../../services/finance/ledger-service.js');
  const summary = await getOrderLedgerSummary({ orderId });
  return { ledger: summary };
}

export async function executeDailyReconciliation(body = {}, query = {}) {
  const runDate = sanitizeRunDate(body.run_date || query.run_date || '');
  const { runDailyReconciliation } = await import('../../../services/finance/reconciliation-service.js');
  const report = await runDailyReconciliation({ runDate });

  return {
    report: {
      run_id: report.run_id,
      run_date: report.run_date,
      summary: report.summary,
      issues_preview: report.issues.slice(0, 100)
    }
  };
}

export async function getManualPayoutList(query = {}) {
  const { status, page, limit, offset } = sanitizeManualPayoutListQuery(query);
  const total = await countManualPayouts(status);
  const payouts = await listManualPayouts(status, limit, offset);

  return {
    payouts,
    pagination: {
      page,
      limit,
      total,
      totalPages: Math.max(1, Math.ceil(total / limit))
    }
  };
}

export async function executeManualPayoutAction(userId, query = {}, body = {}) {
  const payload = sanitizeManualPayoutActionPayload(query, body);
  const { payoutId, action, reason, proofUrl, externalReference } = payload;

  if (!payoutId) {
    throw createBusinessError('VALIDATION_ERROR', 'id do repasse invalido');
  }

  const transition = action === 'approve'
    ? ACTION_TRANSITIONS.approve
    : action === 'reject'
      ? ACTION_TRANSITIONS.reject
      : action === 'pay'
        ? ACTION_TRANSITIONS.pay
        : null;

  if (!transition) {
    throw createBusinessError('VALIDATION_ERROR', 'action deve ser approve, reject ou pay');
  }

  if (action === 'reject' && !reason) {
    throw createBusinessError('VALIDATION_ERROR', 'reason obrigatorio para rejeicao');
  }

  if (action === 'pay' && !externalReference && !proofUrl) {
    throw createBusinessError('VALIDATION_ERROR', 'external_reference ou proof_url obrigatorio para marcar como pago');
  }

  const { recordManualPayoutLedgerEntry } = await import('../../../services/finance/ledger-service.js');
  const { recordAuditLog } = await import('../../../services/audit/audit-service.js');

  return runInTransaction(async (tx) => {
    const payoutResult = await findManualPayoutForUpdate(tx, payoutId);

    if (payoutResult.rows.length === 0) {
      throw createBusinessError('NOT_FOUND', 'Repasse manual nao encontrado');
    }

    const payout = payoutResult.rows[0];
    if (!transition.from.has(payout.status)) {
      throw createBusinessError('INVALID_TRANSITION', `Transicao invalida: ${payout.status} -> ${transition.to}`);
    }

    const updateResult = await updateManualPayout(tx, {
      payoutId,
      toStatus: transition.to,
      reason,
      proofUrl,
      externalReference
    });

    const updatedPayout = updateResult.rows[0];

    const actionLogResult = await insertManualPayoutAction(tx, {
      payoutId,
      action,
      previousStatus: payout.status,
      newStatus: transition.to,
      reason,
      proofUrl,
      externalReference,
      actorUserId: userId
    });

    if (action === 'pay') {
      const paymentRows = await findLatestPaymentByOrderId(tx, payout.order_id);

      await recordManualPayoutLedgerEntry({
        db: tx,
        orderId: payout.order_id,
        paymentId: paymentRows.rows[0]?.id || null,
        manualPayoutId: payout.id,
        amount: Number(payout.amount || 0)
      });
    }

    await recordAuditLog({
      db: tx,
      actorUserId: userId,
      action: `manual_payout.${action}`,
      resourceType: 'manual_payout',
      resourceId: payout.id,
      before: {
        status: payout.status,
        external_reference: payout.external_reference,
        proof_url: payout.proof_url
      },
      after: {
        status: updatedPayout.status,
        external_reference: updatedPayout.external_reference,
        proof_url: updatedPayout.proof_url
      },
      metadata: {
        reason: reason || null,
        action_log_id: actionLogResult.rows[0]?.id || null
      }
    });

    return {
      payout: updatedPayout,
      actionLog: actionLogResult.rows[0]
    };
  });
}
