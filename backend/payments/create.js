import { withCors } from '../middleware.js';
import { requireAuth } from '../auth-middleware.js';
import { createPaymentController } from '../modules/payments/core/controller.js';
import { createRateLimit } from '../rate-limit.js';

const paymentsLimiter = createRateLimit({
  windowMs: 5 * 60 * 1000,
  max: 10,
  message: 'Muitas tentativas de pagamento. Aguarde alguns minutos.'
});

export default withCors(requireAuth(paymentsLimiter(createPaymentController)));
