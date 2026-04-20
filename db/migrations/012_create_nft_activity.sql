-- Append-only NFT provenance log.
-- Every significant event in an NFT's lifecycle is recorded here.
-- This table is never updated, only inserted into.
--
-- event_type values:
--   mint       — token confirmed on chain
--   transfer   — wallet-to-wallet transfer (not through marketplace)
--   sale       — secondary market sale settled through RareFolio
--   gift       — gift redemption transfer
--   list       — NFT listed on the marketplace
--   delist     — listing canceled or expired

CREATE TABLE IF NOT EXISTS qd_nft_activity (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    nft_id              BIGINT UNSIGNED NOT NULL,           -- FK to qd_tokens.id
    rarefolio_token_id  VARCHAR(32)     NOT NULL,           -- denormalised

    event_type          ENUM('mint','transfer','sale','gift','list','delist')
                            NOT NULL,

    -- Parties (NULL for events that don't involve both sides)
    from_addr           VARCHAR(128)    NULL,               -- previous owner / seller
    to_addr             VARCHAR(128)    NULL,               -- new owner / buyer

    -- Financials (populated for sale events)
    sale_amount_lovelace      BIGINT UNSIGNED NULL,
    platform_fee_lovelace     BIGINT UNSIGNED NULL,
    creator_royalty_lovelace  BIGINT UNSIGNED NULL,

    -- References
    tx_hash             CHAR(64)        NULL,               -- on-chain tx
    block_height        BIGINT UNSIGNED NULL,
    listing_id          BIGINT UNSIGNED NULL,               -- FK to qd_listings.id
    order_id            BIGINT UNSIGNED NULL,               -- FK to qd_orders.id

    -- Human-readable context (e.g. "Sold via RareFolio for 150 ADA")
    note                VARCHAR(255)    NULL,

    event_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_nft        (nft_id),
    KEY idx_token_id   (rarefolio_token_id),
    KEY idx_event_type (event_type),
    KEY idx_tx_hash    (tx_hash),
    KEY idx_event_at   (event_at),
    KEY idx_from_addr  (from_addr),
    KEY idx_to_addr    (to_addr),

    CONSTRAINT fk_activity_nft
        FOREIGN KEY (nft_id) REFERENCES qd_tokens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
