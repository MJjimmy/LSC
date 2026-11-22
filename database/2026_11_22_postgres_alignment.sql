-- LSC — Postgres alignment migration (2026-11-22)
--
-- Run this against a Postgres database that was created from an earlier
-- database/init-postgres.sql (the one with `boolean` columns and no
-- oauth_identities / app_auth_codes / email_verification_tokens tables).
-- Fresh installs do NOT need it: database/init-postgres.sql already matches.
--
--   psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f database/2026_11_22_postgres_alignment.sql
--
-- Every statement is idempotent, and the file makes no data-destructive
-- changes: boolean 1/0 values are preserved exactly.

BEGIN;

-- 1. Booleans become smallint 0/1, which is what the application reads and
--    writes (and what the fixtures it is tested against use).
DO $$
DECLARE
    target record;
    default_value integer;
BEGIN
    FOR target IN
        SELECT * FROM (VALUES
            ('users', 'is_active', 1),
            ('users', 'notify_email', 1),
            ('users', 'notify_sms', 0),
            ('study_groups', 'is_private', 0),
            ('notifications', 'is_read', 0)
        ) AS t(table_name, column_name, fallback)
    LOOP
        IF EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = target.table_name
              AND column_name = target.column_name
              AND data_type = 'boolean'
        ) THEN
            default_value := target.fallback;
            EXECUTE format('ALTER TABLE %I ALTER COLUMN %I DROP DEFAULT', target.table_name, target.column_name);
            EXECUTE format(
                'ALTER TABLE %I ALTER COLUMN %I TYPE smallint USING (CASE WHEN %I THEN 1 ELSE 0 END)',
                target.table_name, target.column_name, target.column_name
            );
            EXECUTE format('ALTER TABLE %I ALTER COLUMN %I SET DEFAULT %s', target.table_name, target.column_name, default_value);
        END IF;
    END LOOP;
END $$;

-- 2. Range checks for the converted columns.
DO $$
DECLARE
    target record;
BEGIN
    FOR target IN
        SELECT * FROM (VALUES
            ('users', 'is_active'),
            ('users', 'notify_email'),
            ('users', 'notify_sms'),
            ('study_groups', 'is_private'),
            ('notifications', 'is_read')
        ) AS t(table_name, column_name)
    LOOP
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint
            WHERE conrelid = target.table_name::regclass
              AND contype = 'c'
              AND pg_get_constraintdef(oid) LIKE '%' || target.column_name || '%'
        ) THEN
            EXECUTE format(
                'ALTER TABLE %I ADD CONSTRAINT %I CHECK (%I IN (0,1))',
                target.table_name, target.table_name || '_' || target.column_name || '_bool', target.column_name
            );
        END IF;
    END LOOP;
END $$;

-- 3. Objects added after the first Postgres export.
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    token_hash char(64) NOT NULL,
    user_id integer NOT NULL REFERENCES users(user_id),
    email_hash char(64) NOT NULL,
    expires_at bigint NOT NULL,
    used_at timestamp NULL DEFAULT NULL,
    CONSTRAINT pk_email_verification_tokens PRIMARY KEY (token_hash)
);
CREATE INDEX IF NOT EXISTS idx_email_verification_user ON email_verification_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_email_verification_expiry ON email_verification_tokens(expires_at);

CREATE TABLE IF NOT EXISTS oauth_identities (
    provider varchar(32) NOT NULL,
    subject varchar(255) NOT NULL,
    user_id integer NOT NULL REFERENCES users(user_id),
    CONSTRAINT uq_oauth_provider_subject UNIQUE (provider, subject),
    CONSTRAINT uq_oauth_user_provider UNIQUE (user_id, provider)
);

CREATE TABLE IF NOT EXISTS app_auth_codes (
    code_hash char(64) NOT NULL,
    challenge char(64) NOT NULL,
    user_id integer NOT NULL REFERENCES users(user_id),
    expires_at bigint NOT NULL,
    CONSTRAINT pk_app_auth_codes PRIMARY KEY (code_hash)
);
CREATE INDEX IF NOT EXISTS idx_app_auth_expiry ON app_auth_codes(expires_at);

ALTER TABLE notification_queue ADD COLUMN IF NOT EXISTS provider_message_id varchar(255) NULL DEFAULT NULL;

COMMIT;
