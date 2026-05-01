import {
  countProductsBySeller,
  countPublishedProducts,
  createProduct,
  deleteProductBySeller,
  findProductById,
  findProductPublishState,
  findProductsBySeller,
  findPublishedProducts,
  findSellerByUserId,
  toggleProductPublish,
  updateProductBySeller
} from './repository.js';
import {
  sanitizeCatalogFilters,
  sanitizePagination,
  sanitizeProductId,
  sanitizeProductPayload
} from './schemas.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

function buildCatalogBaseQuery(filters) {
  let baseQuery = `
    FROM products p
    JOIN sellers s ON p.seller_id = s.id
    WHERE p.publicado = true
  `;
  const params = [];
  let paramCount = 1;

  if (filters.categoria && filters.categoria !== 'Todos') {
    baseQuery += ` AND p.categoria = $${paramCount}`;
    params.push(filters.categoria);
    paramCount += 1;
  }

  if (filters.sellerId) {
    baseQuery += ` AND s.id = $${paramCount}`;
    params.push(filters.sellerId);
    paramCount += 1;
  }

  if (filters.search) {
    baseQuery += ` AND (p.nome ILIKE $${paramCount} OR p.descricao ILIKE $${paramCount})`;
    params.push(`%${filters.search}%`);
    paramCount += 1;
  }

  return { baseQuery, params, paramCount };
}

async function resolveSellerId(userId) {
  const sellers = await findSellerByUserId(userId);
  if (sellers.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Vendedor nao encontrado');
  }
  return sellers[0].id;
}

export async function listCatalogProducts(query) {
  const filters = sanitizeCatalogFilters(query);
  const { page, limit, offset } = sanitizePagination(query);
  const { baseQuery, params, paramCount } = buildCatalogBaseQuery(filters);

  const total = await countPublishedProducts(baseQuery, params);
  const products = await findPublishedProducts(baseQuery, params, limit, offset, paramCount);

  return {
    products,
    pagination: {
      page,
      limit,
      total,
      totalPages: Math.ceil(total / limit)
    }
  };
}

export async function createCatalogProduct(userId, body) {
  const sellerId = await resolveSellerId(userId);
  const { sanitized, dimensionsValidation, imageValidation } = sanitizeProductPayload(body);

  if (!sanitized.nome || !sanitized.categoria || sanitized.preco === null) {
    throw createBusinessError('VALIDATION_ERROR', 'Campos obrigatorios faltando (nome, categoria, preco)');
  }

  if (!imageValidation.ok) {
    throw createBusinessError('VALIDATION_ERROR', imageValidation.reason);
  }

  if (!dimensionsValidation.ok) {
    throw createBusinessError('VALIDATION_ERROR', dimensionsValidation.reason);
  }

  if (sanitized.estoque === null || sanitized.estoque < 0) {
    sanitized.estoque = 0;
  }

  const d = dimensionsValidation.value;

  const created = await createProduct({
    sellerId,
    ...sanitized,
    ...d
  });

  return created[0];
}

export async function getProductById(rawId) {
  const productId = sanitizeProductId(rawId);
  if (!productId) {
    throw createBusinessError('INVALID_ID', 'ID invalido');
  }

  const products = await findProductById(productId);
  if (products.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Produto nao encontrado');
  }

  return products[0];
}

export async function updateCatalogProduct(userId, rawId, body) {
  const productId = sanitizeProductId(rawId);
  if (!productId) {
    throw createBusinessError('INVALID_ID', 'ID invalido');
  }

  const sellerId = await resolveSellerId(userId);
  const { sanitized, dimensionsValidation, imageValidation } = sanitizeProductPayload(body);

  if (!sanitized.nome || !sanitized.categoria || sanitized.preco === null) {
    throw createBusinessError('VALIDATION_ERROR', 'Campos obrigatorios faltando (nome, categoria, preco)');
  }

  if (!imageValidation.ok) {
    throw createBusinessError('VALIDATION_ERROR', imageValidation.reason);
  }

  if (!dimensionsValidation.ok) {
    throw createBusinessError('VALIDATION_ERROR', dimensionsValidation.reason);
  }

  if (sanitized.estoque === null || sanitized.estoque < 0) {
    sanitized.estoque = 0;
  }

  const updated = await updateProductBySeller({
    productId,
    sellerId,
    ...sanitized,
    ...dimensionsValidation.value
  });

  if (updated.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Produto nao encontrado ou sem permissao');
  }

  return updated[0];
}

export async function deleteCatalogProduct(userId, rawId) {
  const productId = sanitizeProductId(rawId);
  if (!productId) {
    throw createBusinessError('INVALID_ID', 'ID invalido');
  }

  const sellerId = await resolveSellerId(userId);
  const deleted = await deleteProductBySeller(productId, sellerId);

  if (deleted.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Produto nao encontrado ou sem permissao');
  }
}

export async function toggleCatalogPublish(userId, rawId) {
  const productId = sanitizeProductId(rawId);
  if (!productId) {
    throw createBusinessError('INVALID_ID', 'ID invalido');
  }

  const sellerId = await resolveSellerId(userId);
  const current = await findProductPublishState(productId, sellerId);

  if (current.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Produto nao encontrado ou sem permissao');
  }

  const novoEstado = !current[0].publicado;

  if (
    novoEstado &&
    (!current[0].weight_kg || !current[0].height_cm || !current[0].width_cm || !current[0].length_cm)
  ) {
    throw createBusinessError('VALIDATION_ERROR', 'Peso e dimensoes sao obrigatorios para publicar o produto');
  }

  const toggled = await toggleProductPublish(novoEstado, productId, sellerId);
  return toggled[0];
}

export async function listSellerOwnProducts(userId, query) {
  const sellerId = await resolveSellerId(userId);
  const { page, limit, offset } = sanitizePagination(query);

  const total = await countProductsBySeller(sellerId);
  const products = await findProductsBySeller(sellerId, limit, offset);

  return {
    products,
    pagination: {
      page,
      limit,
      total,
      totalPages: Math.ceil(total / limit)
    }
  };
}
