-- Connected wallets for each user.
-- A user can have multiple wallets; one is the primary payout wallet.

CREATE TABLE IF NOT EXISTS qd_wallets (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             BIGINT UNSIGNED NOT NULL,           -- FK to qd_users.id

    network             ENUM('mainnet','preprod','preview') NOT NULL DEFAULT 'mainnet',
    payment_addr        VARCHAR(128)    NOT NULL,           -- bech32 payment address
    stake_addr          VARCHAR(128)    NULL,               -- bech32 stake address
    ada_handle          VARCHAR(64)     NULL,               -- $handle (without $)

    is_primary          TINYINT(1)      NOT NULL DEFAULT 0, -- primary payout wallet
    verified            TINYINT(1)      NOT NULL DEFAULT 0, -- ownership proved by signing a nonce
    verified_at         DATETIME        NULL,

    -- CIP-30 wallet name reported by the browser extension (informational)
    wallet_provider     VARCHAR(64)     NULL,               -- nami / eternl / lace / etc.

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_network_addr (network, payment_addr),
    KEY idx_user       (user_id),
    KEY idx_stake      (stake_addr),
    KEY idx_handle     (ada_handle),

    CONSTRAINT fk_wallets_user
        FOREIGN KEY (user_id) REFERENCES qd_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
