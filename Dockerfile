# ── Stage 1: install production dependencies ──
FROM node:22-slim AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

# ── Stage 2: production image ──
FROM node:22-slim AS runtime
LABEL maintainer="Quintal Mistico <miwoadm@gmail.com>"

# Security: run as non-root
RUN groupadd --gid 1001 appgroup \
 && useradd  --uid 1001 --gid appgroup --shell /bin/sh --create-home appuser

WORKDIR /app

COPY --from=deps /app/node_modules ./node_modules
COPY package.json ./
COPY server.js ./
COPY backend/ ./backend/
COPY migrations/ ./migrations/
COPY scripts/migrate.js ./scripts/migrate.js

# The public/ dir is only needed if you want the backend to serve static
# files as fallback. On the VPS, Nginx handles static assets or they live
# on Cloudflare Pages. Uncomment the line below if you need the fallback:
# COPY public/ ./public/

ENV NODE_ENV=production
ENV PORT=8080

# Health-check used by Docker / docker-compose / orchestrators
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD node -e "fetch('http://localhost:8080/api/health').then(r=>{if(!r.ok)throw r.status}).catch(()=>process.exit(1))"

EXPOSE 8080

USER appuser

CMD ["node", "server.js"]
