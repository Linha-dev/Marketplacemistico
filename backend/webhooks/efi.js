import { withCors } from '../middleware.js';
import { efiWebhookController } from '../modules/webhooks/core/controller.js';

export default withCors(efiWebhookController);
