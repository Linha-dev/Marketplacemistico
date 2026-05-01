const required = [
  'DATABASE_URL',
  'JWT_SECRET',
  'ALLOWED_ORIGIN',
  'EFI_WEBHOOK_SECRET',
  'MELHOR_ENVIO_WEBHOOK_SECRET'
];

for (const key of required) {
  // eslint-disable-next-line security/detect-object-injection
  if (!process.env[key]) {
    throw new Error(`Variável de ambiente obrigatória nao configurada: ${key}`);
  }
}
