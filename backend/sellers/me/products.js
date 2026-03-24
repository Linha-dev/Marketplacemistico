import { withCors } from '../../middleware.js';
import { requireVendedor } from '../../auth-middleware.js';
import { sellerOwnProductsController } from '../../modules/products/catalog/controller.js';

export default withCors(requireVendedor(sellerOwnProductsController));
