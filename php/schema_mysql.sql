-- ============================================================
-- Marketplace Místico — Schema MySQL / MariaDB
-- Compatível com Hostinger shared hosting (MySQL 5.7+/8.0)
-- Para PostgreSQL (VPS), use schema.sql na raiz do projeto.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

DROP TABLE IF EXISTS shipment_events;
DROP TABLE IF EXISTS shipments;
DROP TABLE IF EXISTS webhook_events;
DROP TABLE IF EXISTS refunds;
DROP TABLE IF EXISTS manual_payouts;
DROP TABLE IF EXISTS payment_splits;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS finance_ledger;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS shipping_quotes;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS seller_shipping_profiles;
DROP TABLE IF EXISTS seller_billing_profiles;
DROP TABLE IF EXISTS addresses;
DROP TABLE IF EXISTS sellers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS rate_limits;

SET foreign_key_checks = 1;

-- ------------------------------------------------------------

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tipo            VARCHAR(20) NOT NULL,
    nome            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) UNIQUE NOT NULL,
    senha_hash      VARCHAR(255),
    google_id       VARCHAR(255) UNIQUE,
    telefone        VARCHAR(20) UNIQUE,
    cpf_cnpj        VARCHAR(20) UNIQUE,
    tipo_documento  VARCHAR(10),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email   (email),
    INDEX idx_users_phone   (telefone),
    INDEX idx_users_document(cpf_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sellers (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNIQUE NOT NULL,
    nome_loja               VARCHAR(255) NOT NULL,
    categoria               VARCHAR(100),
    descricao_loja          TEXT,
    logo_url                VARCHAR(500),
    avaliacao_media         DECIMAL(3,2) DEFAULT 0,
    total_vendas            INT DEFAULT 0,
    is_efi_connected        TINYINT(1) NOT NULL DEFAULT 0,
    efi_payee_code          VARCHAR(255),
    payout_mode             VARCHAR(30) NOT NULL DEFAULT 'manual',
    commission_rate         DECIMAL(8,4) NOT NULL DEFAULT 0.12,
    manual_payout_fee_rate  DECIMAL(8,4) NOT NULL DEFAULT 0.00,
    payout_delay_days       INT NOT NULL DEFAULT 2,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice case-insensitive para nome da loja (MySQL usa collation)
CREATE UNIQUE INDEX idx_sellers_nome_loja ON sellers(nome_loja);

CREATE TABLE seller_billing_profiles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    seller_id    INT NOT NULL UNIQUE,
    legal_name   VARCHAR(255),
    cpf_cnpj     VARCHAR(20),
    bank_name    VARCHAR(120),
    bank_agency  VARCHAR(50),
    bank_account VARCHAR(50),
    pix_key      VARCHAR(255),
    pix_key_type VARCHAR(50),
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_shipping_profiles (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    seller_id        INT NOT NULL UNIQUE,
    from_postal_code VARCHAR(12) NOT NULL,
    from_address_line VARCHAR(255),
    from_number      VARCHAR(50),
    from_district    VARCHAR(120),
    from_city        VARCHAR(120),
    from_state       VARCHAR(2),
    from_country     VARCHAR(2) DEFAULT 'BR',
    contact_name     VARCHAR(255),
    contact_phone    VARCHAR(30),
    document_type    VARCHAR(20),
    document_number  VARCHAR(20),
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE addresses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    cep         VARCHAR(9),
    rua         VARCHAR(255),
    numero      VARCHAR(20),
    complemento VARCHAR(255),
    bairro      VARCHAR(100),
    cidade      VARCHAR(100),
    estado      VARCHAR(2),
    is_default  TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    seller_id       INT NOT NULL,
    nome            VARCHAR(255) NOT NULL,
    categoria       VARCHAR(100) NOT NULL,
    descricao       TEXT,
    preco           DECIMAL(10,2) NOT NULL,
    estoque         INT DEFAULT 0,
    imagem_url      VARCHAR(500),
    publicado       TINYINT(1) DEFAULT 0,
    weight_kg       DECIMAL(10,3),
    height_cm       DECIMAL(10,2),
    width_cm        DECIMAL(10,2),
    length_cm       DECIMAL(10,2),
    insurance_value DECIMAL(10,2) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_seller   (seller_id),
    INDEX idx_products_categoria(categoria),
    INDEX idx_products_publicado(publicado),
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_quotes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    cart_id          VARCHAR(100),
    buyer_id         INT,
    seller_id        INT NOT NULL,
    service_id       VARCHAR(100) NOT NULL,
    service_name     VARCHAR(255) NOT NULL,
    carrier_name     VARCHAR(255),
    price            DECIMAL(10,2) NOT NULL,
    custom_price     DECIMAL(10,2),
    delivery_time    INT,
    raw_response_json JSON,
    expires_at       DATETIME,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shipping_quotes_cart  (cart_id),
    INDEX idx_shipping_quotes_seller(seller_id),
    FOREIGN KEY (buyer_id)  REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES sellers(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id                              INT AUTO_INCREMENT PRIMARY KEY,
    comprador_id                    INT NOT NULL,
    vendedor_id                     INT NOT NULL,
    total                           DECIMAL(10,2) NOT NULL,
    items_subtotal                  DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping_total                  DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_total                  DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total                     DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status                  VARCHAR(50) NOT NULL DEFAULT 'pending',
    shipping_status                 VARCHAR(50) NOT NULL DEFAULT 'pending',
    selected_shipping_quote_id      INT,
    shipping_address_snapshot_json  JSON,
    billing_address_snapshot_json   JSON,
    status                          VARCHAR(50) DEFAULT 'pendente',
    created_at                      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_comprador(comprador_id),
    INDEX idx_orders_vendedor (vendedor_id),
    FOREIGN KEY (comprador_id)               REFERENCES users(id),
    FOREIGN KEY (vendedor_id)                REFERENCES sellers(id),
    FOREIGN KEY (selected_shipping_quote_id) REFERENCES shipping_quotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    order_id                INT NOT NULL,
    seller_id               INT NOT NULL,
    product_id              INT NOT NULL,
    quantidade              INT NOT NULL,
    preco_unitario          DECIMAL(10,2) NOT NULL,
    unit_price              DECIMAL(10,2),
    name_snapshot           VARCHAR(255),
    weight_snapshot         DECIMAL(10,3),
    dimension_snapshot_json JSON,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (seller_id)  REFERENCES sellers(id)  ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    order_id           INT,
    provider           VARCHAR(50) NOT NULL,
    provider_charge_id VARCHAR(255),
    payment_method     VARCHAR(30) NOT NULL,
    status             VARCHAR(50) NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    raw_response_json  JSON,
    paid_at            DATETIME,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_order_id     (order_id),
    INDEX idx_payments_provider_charge(provider, provider_charge_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refunds (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    payment_id           INT NOT NULL,
    order_id             INT,
    provider             VARCHAR(50) NOT NULL,
    provider_refund_id   VARCHAR(255),
    amount               DECIMAL(10,2) NOT NULL,
    reason               VARCHAR(255),
    status               VARCHAR(30) NOT NULL DEFAULT 'pending',
    raw_response_json    JSON,
    requested_by_user_id INT,
    processed_at         DATETIME,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_splits (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    payment_id               INT NOT NULL,
    seller_id                INT NOT NULL,
    split_mode               VARCHAR(30) NOT NULL,
    gross_amount             DECIMAL(10,2) NOT NULL,
    platform_fee_amount      DECIMAL(10,2) NOT NULL,
    gateway_fee_amount       DECIMAL(10,2) DEFAULT 0,
    operational_fee_amount   DECIMAL(10,2) DEFAULT 0,
    seller_net_amount        DECIMAL(10,2) NOT NULL,
    efi_payee_code_snapshot  VARCHAR(255),
    status                   VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id)  REFERENCES sellers(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finance_ledger (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT,
    payment_id  INT,
    entry_type  VARCHAR(50) NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE manual_payouts (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    seller_id          INT NOT NULL,
    order_id           INT NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    fee_amount         DECIMAL(10,2) NOT NULL DEFAULT 0,
    status             VARCHAR(30) NOT NULL DEFAULT 'pending',
    scheduled_for      DATETIME,
    paid_at            DATETIME,
    external_reference VARCHAR(255),
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_events (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    provider     VARCHAR(50) NOT NULL,
    event_type   VARCHAR(100),
    external_id  VARCHAR(255),
    payload_json JSON NOT NULL,
    status       VARCHAR(30) NOT NULL DEFAULT 'received',
    processed_at DATETIME,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_events_unique(provider, external_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipments (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    order_id                  INT NOT NULL,
    seller_id                 INT NOT NULL,
    provider                  VARCHAR(50) NOT NULL,
    status                    VARCHAR(50) NOT NULL DEFAULT 'pending',
    selected_service_id       VARCHAR(100),
    selected_service_name     VARCHAR(255),
    carrier_name              VARCHAR(255),
    melhor_envio_shipment_id  VARCHAR(255),
    tracking_code             VARCHAR(255),
    label_url                 TEXT,
    protocol_json             JSON,
    created_at                DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shipments_order_id(order_id),
    FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipment_events (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id         INT NOT NULL,
    event_name          VARCHAR(100) NOT NULL,
    event_payload_json  JSON NOT NULL,
    occurred_at         DATETIME,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de rate limiting (criada automaticamente pela aplicação, mas pode ser pré-criada aqui)
CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key       VARCHAR(255) NOT NULL PRIMARY KEY,
    count_val      INT NOT NULL DEFAULT 1,
    window_start_ms BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
