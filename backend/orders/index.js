import { withCors } from '../middleware.js';
import { requireAuth } from '../auth-middleware.js';
import { ordersIndexController } from '../modules/orders/core/controller.js';

export default withCors(requireAuth(ordersIndexController));
