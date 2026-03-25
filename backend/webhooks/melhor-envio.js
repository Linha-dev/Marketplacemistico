import { withCors } from '../middleware.js';
import { melhorEnvioWebhookController } from '../modules/webhooks/core/controller.js';
import { normalizeShippingStatus } from '../modules/webhooks/core/schemas.js';

export { normalizeShippingStatus };

export default withCors(melhorEnvioWebhookController);
