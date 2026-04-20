-- Order and settlement records.
-- One order is created when a buyer agrees to purchase a listed NFT.
-- The order tracks the full lifecycle from creation through on-chain settlement.

CREATE TABLE IF NOT EXISTS qd_orders (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- References
    listing_id          BIGINT UNSIGNED NOT NULL,           -- FK to qd_listings.id
    nft_id              BIGINT UNSIGNED NOT NULL,           -- FK to qd_tokens.id (denormalised)
    rarefolio_token_id  VARCHAR(32)     NOT NULL,

    -- Parties
    buyer_user_id       BIGINT UNSIGNED NULL,               -- FK to qd_users.id
    buyer_addr          VARCHAR(128)    NOT NULL,
    seller_addr         VARCHAR(128)    NOT NULL,

    -- Financials (all in lovelace; 1 ADA = 1_000_000 lovelace)
    sale_amount_lovelace      BIGINT UNSIGNED NOT NULL,
    platform_fee_lovelace     BIGINT UNSIGNED NOT NULL,     -- 2.5% of sale_amount
    creator_royalty_lovelace  BIGINT UNSIGNED NOT NULL,     -- 8% of sale_amount
    seller_net_lovelace       BIGINT UNSIGNED NOT NULL,     -- sale - platform - royalty
    creator_addr              VARCHAR(128)    NOT NULL,
    platform_addr             VARCHAR(128)    NOT NULL,

    -- On-chain
    order_tx_hash       CHAR(64)        NULL,               -- settlement tx hash
    block_height        BIGINT UNSIGNED NULL,

    -- Lifecycle: pending -> signed -> submitted -> settled / failed / refunded
    status              ENUM('pending','signed','submitted','settled','failed','refunded')
                            NOT NULL DEFAULT 'pending',
    failure_reason      TEXT            NULL,
    refund_tx_hash      CHAR(64)        NULL,

    -- Timestamps
    settled_at          DATETIME        NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_listing    (listing_id),
    KEY idx_nft        (nft_id),
    KEY idx_buyer      (buyer_user_id),
    KEY idx_status     (status),
    KEY idx_tx_hash    (order_tx_hash),

    CONSTRAINT fk_orders_listing
        FOREIGN KEY (listing_id) REFERENCES qd_listings(id),
    CONSTRAINT fk_orders_nft
        FOREIGN KEY (nft_id) REFERENCES qd_tokens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
