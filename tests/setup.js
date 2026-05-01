// Test environment variables — values are non-sensitive placeholders for unit/integration tests only
process.env.ALLOWED_ORIGIN = 'http://localhost:3000';
process.env.JWT_SECRET = 'test-jwt-secret-for-tests-only';
process.env.DATABASE_URL = 'postgresql://test:test@localhost/testdb';
process.env.EFI_WEBHOOK_SECRET = 'test-efi-webhook-secret';
process.env.MELHOR_ENVIO_WEBHOOK_SECRET = 'test-melhor-envio-webhook-secret';
