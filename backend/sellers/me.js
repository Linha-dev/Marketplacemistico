import { withCors } from '../middleware.js';
import { requireVendedor } from '../auth-middleware.js';
import sellersMeController from '../modules/sellers/me/controller.js';

export default withCors(requireVendedor(sellersMeController));
