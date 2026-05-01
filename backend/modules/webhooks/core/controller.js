import { sendError, sendSuccess } from '../../../response.js';
import { requireWebhookOpsSecret } from '../../../webhooks/efi/_ops-auth.js';
import { sanitizeReprocessPayload, sanitizeWebhookSecretHeader } from './schemas.js';
import {
  processEfiWebhook,
  processMelhorEnvioWebhook,
  reprocessEfiEvent,
  retryDueEfiFailedEvents
} from './service.js';

function statusForCode(code) {
  if (code === 'UNAUTHORIZED') return 401;
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'VALIDATION_ERROR') return 400;
  return 500;
}

function handleError(res, error, fallbackMessage) {
  const code = error.code || 'INTERNAL_ERROR';
  return sendError(res, code, error.message || fallbackMessage, statusForCode(code));
}

export async function efiWebhookController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const webhookSecret = process.env.EFI_WEBHOOK_SECRET;
    if (!webhookSecret) {
      throw new Error('EFI_WEBHOOK_SECRET não configurada');
    }
    const receivedSecret = sanitizeWebhookSecretHeader(req.headers);
    if (receivedSecret !== webhookSecret) {
      return sendError(res, 'UNAUTHORIZED', 'Webhook sem autorizacao', 401);
    }

    const payload = await processEfiWebhook(req.headers, req.body, req.correlationId || null);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar webhook EFI');
  }
}

export async function efiRetryWebhookController(req, res) {
  if (req.method !== 'POST') {
    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  }

  const auth = requireWebhookOpsSecret(req, res);
  if (!auth.ok) {
    return undefined;
  }

  try {
    const payload = await retryDueEfiFailedEvents(req.body);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao reprocessar fila de webhook EFI');
  }
}

export async function efiReprocessWebhookController(req, res) {
  if (req.method !== 'POST') {
    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  }

  const auth = requireWebhookOpsSecret(req, res);
  if (!auth.ok) {
    return undefined;
  }

  try {
    const { eventId, force } = sanitizeReprocessPayload(req.body, req.query);
    const payload = await reprocessEfiEvent(eventId, force);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao reprocessar webhook EFI');
  }
}

export async function melhorEnvioWebhookController(req, res) {
  try {
    if (req.method !== 'POST') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const webhookSecret = process.env.MELHOR_ENVIO_WEBHOOK_SECRET;
    if (!webhookSecret) {
      throw new Error('MELHOR_ENVIO_WEBHOOK_SECRET não configurada');
    }
    const receivedSecret = sanitizeWebhookSecretHeader(req.headers);
    if (receivedSecret !== webhookSecret) {
      return sendError(res, 'UNAUTHORIZED', 'Webhook sem autorizacao', 401);
    }

    const payload = await processMelhorEnvioWebhook(req.body);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar webhook de envio');
  }
}
