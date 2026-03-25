import { sendError, sendSuccess } from '../../../response.js';
import {
  createOrder,
  createPostSale,
  getOrderById,
  listOrdersForBuyer,
  patchOrderStatus
} from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'FORBIDDEN') return 403;
  if ([
    'VALIDATION_ERROR',
    'INVALID_ID',
    'CANCEL_NOT_ALLOWED',
    'RETURN_NOT_ALLOWED',
    'INVALID_PAYMENT_STATUS',
    'NO_REFUNDABLE_BALANCE',
    'MULTI_SELLER_NOT_ALLOWED',
    'PRODUCT_UNAVAILABLE',
    'INSUFFICIENT_STOCK'
  ].includes(code)) return 400;
  return 500;
}

function handleError(res, error, fallbackMessage) {
  const code = error.code || 'INTERNAL_ERROR';
  return sendError(res, code, error.message || fallbackMessage, statusForCode(code));
}

export async function ordersIndexController(req, res) {
  try {
    if (req.method === 'GET') {
      const payload = await listOrdersForBuyer(req.user.id, req.query);
      return sendSuccess(res, payload);
    }

    if (req.method === 'POST') {
      const order = await createOrder(req.user.id, req.body);
      return sendSuccess(res, { order }, 201);
    }

    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar pedidos');
  }
}

export async function orderByIdController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Método não permitido', 405);
    }

    const order = await getOrderById(req.user.id, req.query.id);
    return sendSuccess(res, { order });
  } catch (error) {
    return handleError(res, error, 'Erro ao buscar pedido');
  }
}

export async function orderStatusController(req, res) {
  try {
    if (req.method !== 'PATCH') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const order = await patchOrderStatus(req.user.id, req.query.id, req.body);
    return sendSuccess(res, { order });
  } catch (error) {
    return handleError(res, error, 'Erro ao atualizar status do pedido');
  }
}

export async function orderPostSaleController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await createPostSale(req.user.id, req.query.id, req.body);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar operacao de pos-venda');
  }
}
