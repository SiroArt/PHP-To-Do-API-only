CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_jti CHAR(36) NOT NULL UNIQUE,
    token_hash VARCHAR(64) NOT NULL,
    family_id CHAR(36) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_jti (token_jti),
    INDEX idx_user_id (user_id),
    INDEX idx_family_id (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
