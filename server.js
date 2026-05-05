import express from 'express';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import handler from './api/index.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const app = express();
const PORT = process.env.PORT || 8080;

app.use(express.json());
app.use(express.urlencoded({ extended: false }));

app.use((req, res, next) => {
  if (req.path.startsWith('/api')) {
    return handler(req, res).catch(next);
  }
  next();
});

app.use(express.static(join(__dirname, 'public')));

app.listen(PORT, () => {
  console.log(`Server listening on port ${PORT}`);
});
