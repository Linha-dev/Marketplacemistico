import { withCors } from '../middleware.js';
import { requireAuth } from '../auth-middleware.js';
import { createRefundController } from '../modules/payments/core/controller.js';

export default withCors(requireAuth(createRefundController));
