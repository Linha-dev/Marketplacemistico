import {
  claimDueFailedEvents,
  claimWebhookEventById,
  claimWebhookEventByKey,
  extractEfiEvent,
  isRetryableWebhookError,
  markWebhookFailure,
  markWebhookIgnored,
  processEfiWebhookEvent
} from '../../../services/webhooks/efi-webhook-processor.js';
import { incrementMetric } from '../../../observability/metrics-store.js';
import { logError, logInfo } from '../../../observability/logger.js';
import { sanitizeString } from '../../../sanitize.js';
import { extractMelhorEnvioPayload, sanitizeRetryLimit } from './schemas.js';
import {
  findShipmentByExternalId,
  insertShipmentEvent,
  updateOrderShippingStatus,
  updateShipmentTracking
} from './repository.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function processEfiWebhook(headers, body, correlationId = null) {
  const payload = body || {};
  const event = extractEfiEvent(payload);
  incrementMetric('webhooks.efi.received.total');

  if (!event.providerChargeId) {
    throw createBusinessError('VALIDATION_ERROR', 'provider_charge_id ausente');
  }

  try {
    const claim = await claimWebhookEventByKey({
      provider: 'efi',
      providerChargeId: event.providerChargeId,
      eventType: event.eventType,
      payload,
      lockOwner: 'efi_webhook'
    });

    if (!claim.claimed) {
      if (claim.reason === 'processing') {
        incrementMetric('webhooks.efi.in_progress.total');
        return { message: 'Evento em processamento' };
      }
      incrementMetric('webhooks.efi.duplicate.total');
      return { message: 'Evento ja processado' };
    }

    const result = await processEfiWebhookEvent({ eventId: claim.event.id, payload });
    incrementMetric('webhooks.efi.processed.total');
    logInfo('webhooks.efi.processed', {
      correlation_id: correlationId,
      provider_charge_id: event.providerChargeId,
      event_type: event.eventType
    });
    return result;
  } catch (error) {
    const eventType = sanitizeString(event.eventType || 'payment_status_changed');

    if (error.code === 'INVALID_PAYMENT_STATUS_TRANSITION') {
      const claim = await claimWebhookEventByKey({
        provider: 'efi',
        providerChargeId: event.providerChargeId,
        eventType,
        payload,
        lockOwner: 'efi_webhook_invalid_transition'
      });

      if (claim.claimed) {
        await markWebhookIgnored({ eventId: claim.event.id, reason: error.message });
      }
      incrementMetric('webhooks.efi.ignored.total');

      return {
        processed: false,
        reason: error.code,
        message: error.message
      };
    }

    const claim = await claimWebhookEventByKey({
      provider: 'efi',
      providerChargeId: event.providerChargeId,
      eventType,
      payload,
      lockOwner: 'efi_webhook_failure'
    });

    if (claim.claimed) {
      const failureResult = await markWebhookFailure({
        eventId: claim.event.id,
        errorCode: error.code || 'PROCESSING_ERROR',
        errorMessage: error.message
      });

      if (isRetryableWebhookError(error)) {
        incrementMetric('webhooks.efi.retry_queued.total');
        return {
          processed: false,
          queuedForRetry: true,
          reason: error.code,
          retryCount: failureResult?.retry_count,
          maxRetries: failureResult?.max_retries,
          nextRetryAt: failureResult?.next_retry_at || null
        };
      }
    }

    incrementMetric('webhooks.efi.error.total');
    logError('webhooks.efi.error', error, {
      correlation_id: correlationId,
      provider_charge_id: event.providerChargeId,
      event_type: event.eventType
    });
    throw createBusinessError('INTERNAL_ERROR', 'Erro ao processar webhook EFI');
  }
}

export async function retryDueEfiFailedEvents(body = {}) {
  const limit = sanitizeRetryLimit(body);
  const claimed = await claimDueFailedEvents({ limit, lockOwner: 'efi_retry_job' });

  const summary = {
    claimed: claimed.length,
    processed: 0,
    failed: 0,
    ignored: 0,
    retryQueued: 0
  };

  for (const event of claimed) {
    try {
      await processEfiWebhookEvent({ eventId: event.id, payload: event.payload_json });
      summary.processed += 1;
    } catch (error) {
      if (error.code === 'INVALID_PAYMENT_STATUS_TRANSITION') {
        await markWebhookIgnored({ eventId: event.id, reason: error.message });
        summary.ignored += 1;
        continue;
      }

      const failure = await markWebhookFailure({
        eventId: event.id,
        errorCode: error.code || 'PROCESSING_ERROR',
        errorMessage: error.message
      });

      if (isRetryableWebhookError(error) && failure?.status === 'failed') {
        summary.retryQueued += 1;
      } else {
        summary.failed += 1;
      }
    }
  }

  return { queue: summary };
}

export async function reprocessEfiEvent(eventId, force) {
  if (!eventId) {
    throw createBusinessError('VALIDATION_ERROR', 'event_id obrigatorio');
  }

  const claim = await claimWebhookEventById({ eventId, force, lockOwner: 'manual_reprocess' });
  if (!claim.claimed) {
    if (claim.reason === 'not_found') {
      throw createBusinessError('NOT_FOUND', 'Evento de webhook nao encontrado');
    }

    return {
      processed: false,
      message: 'Evento nao elegivel para reprocessamento',
      status: claim.reason
    };
  }

  try {
    const result = await processEfiWebhookEvent({ eventId, payload: claim.event.payload_json });
    return { processed: true, event_id: eventId, result };
  } catch (error) {
    if (error.code === 'INVALID_PAYMENT_STATUS_TRANSITION') {
      await markWebhookIgnored({ eventId, reason: error.message });
      return { processed: false, reason: error.code, message: error.message };
    }

    const failure = await markWebhookFailure({
      eventId,
      errorCode: error.code || 'PROCESSING_ERROR',
      errorMessage: error.message
    });

    return {
      processed: false,
      reason: error.code || 'PROCESSING_ERROR',
      retryable: isRetryableWebhookError(error),
      status: failure?.status || 'failed',
      retryCount: failure?.retry_count,
      maxRetries: failure?.max_retries,
      nextRetryAt: failure?.next_retry_at || null
    };
  }
}

export async function processMelhorEnvioWebhook(body = {}) {
  const payload = body || {};
  const { shipmentExternalId, eventType, normalizedStatus, trackingCode } = extractMelhorEnvioPayload(payload);

  if (!shipmentExternalId) {
    throw createBusinessError('VALIDATION_ERROR', 'shipment_id externo ausente');
  }

  const shipments = await findShipmentByExternalId(shipmentExternalId);

  if (shipments.length === 0) {
    return { message: 'Shipment nao localizado, evento registrado para auditoria' };
  }

  const shipment = shipments[0];

  await insertShipmentEvent(shipment.id, eventType, payload);
  await updateShipmentTracking(shipment.id, trackingCode, normalizedStatus);
  await updateOrderShippingStatus(shipment.order_id, normalizedStatus);

  return { processed: true, shipmentId: shipment.id };
}
