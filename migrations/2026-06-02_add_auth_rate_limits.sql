CREATE TABLE IF NOT EXISTS auth_rate_limits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action VARCHAR(50) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    first_attempt_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    locked_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY auth_rate_limits_action_subject_ip_unique (action, subject_hash, ip_hash),
    KEY auth_rate_limits_locked_until_idx (locked_until),
    KEY auth_rate_limits_last_attempt_idx (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
