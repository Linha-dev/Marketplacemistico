import { neon } from '@neondatabase/serverless';

let sql;

export function getDb() {
  if (!sql) {
    const databaseUrl = process.env.DATABASE_URL;
    if (!databaseUrl) {
      throw new Error('DATABASE_URL não configurada nas variáveis de ambiente');
    }
    sql = neon(databaseUrl);
  }
  return sql;
}

export async function query(text, params = []) {
  const sql = getDb();
  try {
    const result = await sql(text, params);
    return result;
  } catch (error) {
    console.error('❌ Database query error:', error);
    console.error('Query:', text);
    console.error('Params:', params);
    throw error;
  }
}
