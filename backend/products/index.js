import { withCors } from '../middleware.js';
import { requireVendedor } from '../auth-middleware.js';
import { productsIndexController } from '../modules/products/catalog/controller.js';

async function handler(req, res) {
  if (req.method === 'POST') {
    return requireVendedor(productsIndexController)(req, res);
  }

  return productsIndexController(req, res);
}

export default withCors(handler);
