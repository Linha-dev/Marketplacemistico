-- Initialization script for PostgreSQL on VPS
-- This runs automatically when the container is first created.

-- Create the application database (if not using the default POSTGRES_DB)
-- The database is already created via POSTGRES_DB env var in docker-compose.

-- Recommended PostgreSQL tuning for a small VPS (2-4 GB RAM)
ALTER SYSTEM SET shared_buffers = '256MB';
ALTER SYSTEM SET effective_cache_size = '768MB';
ALTER SYSTEM SET maintenance_work_mem = '128MB';
ALTER SYSTEM SET work_mem = '4MB';
ALTER SYSTEM SET max_connections = 50;
ALTER SYSTEM SET random_page_cost = 1.1;
ALTER SYSTEM SET log_min_duration_statement = 500;
ALTER SYSTEM SET log_statement = 'ddl';
ALTER SYSTEM SET log_lock_waits = 'on';

-- Extensions commonly used
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
