import { withCors } from '../../middleware.js';
import { requireVendedor } from '../../auth-middleware.js';
import { productPublishController } from '../../modules/products/catalog/controller.js';

export default withCors(requireVendedor(productPublishController));
