ALTER TABLE users
    ADD COLUMN pending_email VARCHAR(255) DEFAULT NULL AFTER email_verified_at;

CREATE INDEX users_pending_email_idx ON users (pending_email);
