ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL AFTER timezone;

UPDATE users
SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP())
WHERE email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email_verification_tokens_token_hash_unique (token_hash),
    KEY email_verification_tokens_user_id_idx (user_id),
    CONSTRAINT email_verification_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
