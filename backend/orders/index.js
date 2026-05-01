import { withCors } from '../middleware.js';
import { requireAuth } from '../auth-middleware.js';
import { ordersIndexController } from '../modules/orders/core/controller.js';
import { createRateLimit } from '../rate-limit.js';

const ordersLimiter = createRateLimit({
  windowMs: 60 * 1000,
  max: 20,
  message: 'Muitas requisicoes. Aguarde alguns minutos.'
});

export default withCors(requireAuth(ordersLimiter(ordersIndexController)));
