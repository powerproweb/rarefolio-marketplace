-- Collector / buyer / artist user accounts.
-- Wallet connections are stored separately in qd_wallets (migration 009).

CREATE TABLE IF NOT EXISTS qd_users (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username            VARCHAR(64)     NULL,               -- chosen display handle
    email               VARCHAR(191)    NULL,
    email_verified_at   DATETIME        NULL,
    password_hash       VARCHAR(255)    NULL,               -- bcrypt / argon2; NULL = wallet-only auth

    display_name        VARCHAR(128)    NULL,
    avatar_url          VARCHAR(512)    NULL,
    bio                 TEXT            NULL,

    role                ENUM('collector','artist','curator','admin') NOT NULL DEFAULT 'collector',
    status              ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',

    -- 2FA (future)
    totp_secret         VARCHAR(64)     NULL,
    totp_enabled        TINYINT(1)      NOT NULL DEFAULT 0,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email    (email),
    KEY idx_role   (role),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
