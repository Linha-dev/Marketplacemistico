const required = [
  'DATABASE_URL',
  'JWT_SECRET',
  'ALLOWED_ORIGIN'
];

for (const key of required) {
  // eslint-disable-next-line security/detect-object-injection
  if (!process.env[key]) {
    throw new Error(`Variável de ambiente obrigatória nao configurada: ${key}`);
  }
}
