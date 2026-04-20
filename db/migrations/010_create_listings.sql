-- Secondary marketplace listings.
-- Replaces the listing_status enum flag on qd_tokens as the primary source
-- of truth for whether and how an NFT is listed.
--
-- qd_tokens.listing_status is still updated as a denormalised cache so the
-- existing API and admin views don't need immediate changes.

CREATE TABLE IF NOT EXISTS qd_listings (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Asset reference
    nft_id              BIGINT UNSIGNED NOT NULL,           -- FK to qd_tokens.id
    rarefolio_token_id  VARCHAR(32)     NOT NULL,           -- denormalised for easy lookups

    -- Seller
    seller_user_id      BIGINT UNSIGNED NULL,               -- FK to qd_users.id (NULL = legacy/unclaimed)
    seller_wallet_id    BIGINT UNSIGNED NULL,               -- FK to qd_wallets.id
    seller_addr         VARCHAR(128)    NOT NULL,           -- bech32 of the seller at listing time

    -- Pricing
    sale_format         ENUM('fixed','auction','offer_only') NOT NULL DEFAULT 'fixed',
    asking_price_lovelace BIGINT UNSIGNED NULL,             -- price in lovelace (1 ADA = 1_000_000)
    reserve_price_lovelace BIGINT UNSIGNED NULL,            -- reserve for auctions
    currency            VARCHAR(16)     NOT NULL DEFAULT 'ADA',

    -- Timing
    starts_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at             DATETIME        NULL,               -- NULL = no expiry (fixed price)

    -- On-chain references
    listing_tx_hash     CHAR(64)        NULL,               -- escrow / lock tx (if applicable)
    cancel_tx_hash      CHAR(64)        NULL,

    -- Lifecycle
    status              ENUM('active','sold','canceled','expired') NOT NULL DEFAULT 'active',
    settled_order_id    BIGINT UNSIGNED NULL,               -- FK to qd_orders.id once settled
    canceled_at         DATETIME        NULL,
    settled_at          DATETIME        NULL,

    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Only one active listing per NFT at a time
    UNIQUE KEY uq_active_listing (nft_id, status),
    KEY idx_token_id   (rarefolio_token_id),
    KEY idx_seller     (seller_user_id),
    KEY idx_status     (status),
    KEY idx_format     (sale_format),
    KEY idx_ends_at    (ends_at),
    KEY idx_price      (asking_price_lovelace),

    CONSTRAINT fk_listings_nft
        FOREIGN KEY (nft_id) REFERENCES qd_tokens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
