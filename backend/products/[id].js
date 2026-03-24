import { withCors } from '../middleware.js';
import { requireVendedor } from '../auth-middleware.js';
import { productsByIdController } from '../modules/products/catalog/controller.js';

async function handler(req, res) {
  if (req.method === 'PUT' || req.method === 'DELETE') {
    return requireVendedor(productsByIdController)(req, res);
  }

  return productsByIdController(req, res);
}

export default withCors(handler);
