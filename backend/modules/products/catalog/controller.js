import { sendError, sendSuccess } from '../../../response.js';
import {
  createCatalogProduct,
  deleteCatalogProduct,
  getProductById,
  listCatalogProducts,
  listSellerOwnProducts,
  toggleCatalogPublish,
  updateCatalogProduct
} from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'INVALID_ID' || code === 'VALIDATION_ERROR') return 400;
  return 500;
}

function handleError(res, error, fallbackMessage) {
  const code = error.code || 'INTERNAL_ERROR';
  return sendError(res, code, error.message || fallbackMessage, statusForCode(code));
}

export async function productsIndexController(req, res) {
  try {
    if (req.method === 'GET') {
      const payload = await listCatalogProducts(req.query);
      return sendSuccess(res, payload);
    }

    if (req.method === 'POST') {
      const product = await createCatalogProduct(req.user.id, req.body);
      return sendSuccess(res, { product }, 201);
    }

    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar produtos');
  }
}

export async function productsByIdController(req, res) {
  try {
    if (req.method === 'GET') {
      const product = await getProductById(req.query.id);
      return sendSuccess(res, { product });
    }

    if (req.method === 'PUT') {
      const product = await updateCatalogProduct(req.user.id, req.query.id, req.body);
      return sendSuccess(res, { product });
    }

    if (req.method === 'DELETE') {
      await deleteCatalogProduct(req.user.id, req.query.id);
      return sendSuccess(res, { message: 'Produto deletado' });
    }

    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  } catch (error) {
    return handleError(res, error, 'Erro ao processar produto');
  }
}

export async function productPublishController(req, res) {
  try {
    if (req.method !== 'PATCH') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const product = await toggleCatalogPublish(req.user.id, req.query.id);
    return sendSuccess(res, { product });
  } catch (error) {
    return handleError(res, error, 'Erro ao atualizar publicacao');
  }
}

export async function sellerOwnProductsController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Método não permitido', 405);
    }

    const payload = await listSellerOwnProducts(req.user.id, req.query);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao buscar produtos do vendedor');
  }
}
