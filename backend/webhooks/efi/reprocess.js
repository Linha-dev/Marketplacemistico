import { withCors } from '../../middleware.js';
import { efiReprocessWebhookController } from '../../modules/webhooks/core/controller.js';

export default withCors(efiReprocessWebhookController);
