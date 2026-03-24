import { withCors } from '../middleware.js';
import { requireAuth } from '../auth-middleware.js';
import { createPaymentController } from '../modules/payments/core/controller.js';

export default withCors(requireAuth(createPaymentController));
