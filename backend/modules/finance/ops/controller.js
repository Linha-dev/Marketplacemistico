import { sendError, sendSuccess } from '../../../response.js';
import {
  executeDailyReconciliation,
  executeManualPayoutAction,
  getManualPayoutList,
  getOrderLedgerForUser
} from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'FORBIDDEN') return 403;
  if (code === 'VALIDATION_ERROR' || code === 'INVALID_TRANSITION') return 400;
  return 500;
}

function handleError(res, error, fallbackMessage) {
  const code = error.code || 'INTERNAL_ERROR';
  return sendError(res, code, error.message || fallbackMessage, statusForCode(code));
}

export async function financeLedgerController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await getOrderLedgerForUser(req.user.id, req.query?.orderId);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao consultar ledger financeiro');
  }
}

export async function financeReconciliationDailyController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await executeDailyReconciliation(req.body, req.query);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao executar conciliacao diaria');
  }
}

export async function manualPayoutsListController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await getManualPayoutList(req.query);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao listar repasses manuais');
  }
}

export async function manualPayoutActionController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await executeManualPayoutAction(req.user.id, req.query, req.body);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao executar operacao de repasse manual');
  }
}
