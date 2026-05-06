import express from 'express';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { runStatusCheck } from './backend/observability/status-checker.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const app = express();
const PORT = process.env.PORT || 8080;

app.use(express.json());
app.use(express.urlencoded({ extended: false }));

let handler = null;
try {
  const apiModule = await import('./api/index.js');
  handler = apiModule.default;
} catch (err) {
  console.error('API handler failed to load (missing env vars?):', err.message);
}

app.use((req, res, next) => {
  if (req.path.startsWith('/api')) {
    if (!handler) {
      res.status(503).json({ error: 'API unavailable - required environment variables may be missing' });
      return;
    }
    return handler(req, res).catch(next);
  }
  next();
});

app.use(express.static(join(__dirname, 'public')));

// SPA fallback: serve index.html for any route not matched by a static file
app.get('*', (_req, res) => {
  res.sendFile(join(__dirname, 'public', 'index.html'), (err) => {
    if (err) {
      res.status(500).end();
    }
  });
});

const THIRTY_MINUTES = 30 * 60 * 1000;

app.listen(PORT, () => {
  console.log(`Server listening on port ${PORT}`);
  // run once on startup then every 30 min
  setTimeout(runStatusCheck, 10_000);
  setInterval(runStatusCheck, THIRTY_MINUTES);
});
