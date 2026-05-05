import pg from 'pg';

const { Pool } = pg;
let pool;

function getPool() {
  const url = process.env.DATABASE_URL;
  if (!url) throw new Error('DATABASE_URL nao configurada nas variaveis de ambiente');
  if (!pool) pool = new Pool({ connectionString: url });
  return pool;
}

export async function query(text, params = []) {
  try {
    const result = await getPool().query(text, params);
    return result.rows;
  } catch (error) {
    console.error('Database error:', error.message);
    throw error;
  }
}

export async function withTransaction(callback) {
  const client = await getPool().connect();
  try {
    await client.query('BEGIN');
    const result = await callback(client);
    await client.query('COMMIT');
    return result;
  } catch (error) {
    try {
      await client.query('ROLLBACK');
    } catch (rollbackError) {
      console.error('Rollback error:', rollbackError.message);
    }
    throw error;
  } finally {
    client.release();
  }
}
