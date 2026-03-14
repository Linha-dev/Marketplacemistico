import { withCors } from '../backend/middleware.js';
import { sendError, sendSuccess } from '../backend/response.js';
import { routes } from '../backend/routes.js';

const handlerCache = new Map();

function normalizePath(pathname) {
  if (!pathname) return '/';
  const withoutTrailingSlash = pathname.replace(/\/+$/, '');
  return withoutTrailingSlash || '/';
}

function matchRoute(pattern, pathname) {
  const patternSegments = pattern.split('/').filter(Boolean);
  const pathSegments = pathname.split('/').filter(Boolean);

  if (patternSegments.length !== pathSegments.length) {
    return null;
  }

  const params = {};

  for (let i = 0; i < patternSegments.length; i += 1) {
    /* eslint-disable security/detect-object-injection */
    const patternSegment = patternSegments[i];
    const pathSegment = pathSegments[i];
    /* eslint-enable security/detect-object-injection */

    if (patternSegment.startsWith(':')) {
      params[patternSegment.slice(1)] = decodeURIComponent(pathSegment);
      continue;
    }

    if (patternSegment !== pathSegment) {
      return null;
    }
  }

  return params;
}

function addQueryValue(query, key, value) {
  /* eslint-disable security/detect-object-injection */
  if (query[key] === undefined) {
    query[key] = value;
    return;
  }

  if (Array.isArray(query[key])) {
    query[key].push(value);
    return;
  }

  query[key] = [query[key], value];
  /* eslint-enable security/detect-object-injection */
}

function buildQuery(url, existingQuery = {}, routeParams = {}, excludedKeys = new Set()) {
  const query = { ...existingQuery };

  for (const [key, value] of url.searchParams.entries()) {
    if (excludedKeys.has(key)) {
      continue;
    }
    addQueryValue(query, key, value);
  }

  return { ...query, ...routeParams };
}

async function loadHandler(route) {
  if (handlerCache.has(route.pattern)) {
    return handlerCache.get(route.pattern);
  }

  const module = await route.load();
  const resolvedHandler = module?.default || module;

  if (typeof resolvedHandler !== 'function') {
    throw new Error(`Handler invalido para rota ${route.pattern}`);
  }

  handlerCache.set(route.pattern, resolvedHandler);
  return resolvedHandler;
}

async function dispatch(req, res) {
  const requestUrl = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const internalRewritePath =
    requestUrl.searchParams.get('path') || requestUrl.searchParams.get('1');
  const isInternalRewriteRequest = normalizePath(requestUrl.pathname) === '/api/index.js';

  let pathname = normalizePath(requestUrl.pathname);
  if (isInternalRewriteRequest && internalRewritePath) {
    pathname = normalizePath(`/api/${internalRewritePath}`);
  }

  if (pathname === '/api') {
    return sendSuccess(res, {
      message: 'Marketplace API Gateway ativo',
      routes: routes.length,
      timestamp: new Date().toISOString()
    });
  }

  for (const route of routes) {
    const params = matchRoute(route.pattern, pathname);
    if (!params) {
      continue;
    }

    const internalQueryKeys = isInternalRewriteRequest
      ? new Set(['path', '1'])
      : new Set();

    req.query = buildQuery(requestUrl, req.query, params, internalQueryKeys);

    if (req.method === 'GET' && route.cacheControl) {
      res.setHeader('Cache-Control', route.cacheControl);
    }

    const handler = await loadHandler(route);
    return handler(req, res);
  }

  return sendError(res, 'NOT_FOUND', 'Rota da API nao encontrada', 404);
}

export default withCors(dispatch);
