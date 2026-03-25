import { withCors } from '../../middleware.js';
import { efiRetryWebhookController } from '../../modules/webhooks/core/controller.js';

export default withCors(efiRetryWebhookController);
