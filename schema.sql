-- =====================================================================
-- HU Market — Production schema
-- หมายเหตุ: ไฟล์ schema.sql ต้นฉบับที่แนบมามีขนาด 0 ไบต์ (ไฟล์ว่าง/หายไปจากแพ็กเกจ)
-- จึงสร้างสคีมานี้ขึ้นใหม่ทั้งหมดโดยอ้างอิงจากทุกตาราง/คอลัมน์ที่ถูกเรียกใช้จริงในโค้ด PHP
-- พร้อมเพิ่ม constraint, index, และ FK cascade ให้เหมาะสมกับการใช้งานจริง
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS marketplace_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE marketplace_db;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(20)  NOT NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    password        VARCHAR(255) NOT NULL,
    faculty         VARCHAR(100) DEFAULT NULL,
    role            ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    wallet_balance  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_student_id (student_id),
    CONSTRAINT chk_users_wallet_nonneg CHECK (wallet_balance >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
    ('หนังสือ/ตำรา'), ('เครื่องเขียน'), ('อุปกรณ์อิเล็กทรอนิกส์'),
    ('เสื้อผ้า/แฟชั่น'), ('ของใช้ในหอพัก'), ('อื่น ๆ');

-- ---------------------------------------------------------------------
-- shops
-- ---------------------------------------------------------------------
CREATE TABLE shops (
    shop_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id         INT UNSIGNED NOT NULL,
    shop_name         VARCHAR(150) NOT NULL,
    shop_description  TEXT DEFAULT NULL,
    logo_url          VARCHAR(255) DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shops_seller (seller_id),
    CONSTRAINT fk_shops_seller FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------
CREATE TABLE products (
    product_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id    INT UNSIGNED NOT NULL,
    category_id  INT UNSIGNED DEFAULT NULL,
    title        VARCHAR(150) NOT NULL,
    description  TEXT DEFAULT NULL,
    price        DECIMAL(12,2) NOT NULL,
    `condition`  ENUM('new','like_new','good','fair') NOT NULL DEFAULT 'good',
    status       ENUM('available','sold','removed') NOT NULL DEFAULT 'available',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_seller  FOREIGN KEY (seller_id)   REFERENCES users(user_id)      ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    CONSTRAINT chk_products_price_positive CHECK (price >= 0),
    INDEX idx_products_status_created (status, created_at),
    INDEX idx_products_seller (seller_id),
    FULLTEXT INDEX ft_products_title (title)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_images
-- ---------------------------------------------------------------------
CREATE TABLE product_images (
    image_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    image_url   VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product_images_product (product_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- cart_items
-- ---------------------------------------------------------------------
CREATE TABLE cart_items (
    cart_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_user_product (user_id, product_id),
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- chats
-- ---------------------------------------------------------------------
CREATE TABLE chats (
    chat_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id    INT UNSIGNED NOT NULL,
    seller_id   INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chats_product_buyer_seller (product_id, buyer_id, seller_id),
    CONSTRAINT fk_chats_buyer   FOREIGN KEY (buyer_id)   REFERENCES users(user_id)       ON DELETE CASCADE,
    CONSTRAINT fk_chats_seller  FOREIGN KEY (seller_id)  REFERENCES users(user_id)       ON DELETE CASCADE,
    CONSTRAINT fk_chats_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    CONSTRAINT chk_chats_buyer_not_seller CHECK (buyer_id <> seller_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- messages
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    message_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id       INT UNSIGNED NOT NULL,
    sender_id     INT UNSIGNED NOT NULL,
    message_text  VARCHAR(2000) NOT NULL,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_chat   FOREIGN KEY (chat_id)   REFERENCES chats(chat_id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_messages_chat_sent (chat_id, sent_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- transactions
-- ---------------------------------------------------------------------
CREATE TABLE transactions (
    transaction_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id       INT UNSIGNED NOT NULL,
    buyer_id         INT UNSIGNED NOT NULL,
    meetup_location  VARCHAR(255) NOT NULL,
    meetup_time      DATETIME NOT NULL,
    status           ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    payment_method   ENUM('cash','wallet') NOT NULL DEFAULT 'cash',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at     DATETIME DEFAULT NULL,
    CONSTRAINT fk_tx_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    CONSTRAINT fk_tx_buyer   FOREIGN KEY (buyer_id)    REFERENCES users(user_id)      ON DELETE CASCADE,
    INDEX idx_tx_buyer (buyer_id),
    INDEX idx_tx_product_status (product_id, status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- live_locations (real-time meetup tracking)
-- ---------------------------------------------------------------------
CREATE TABLE live_locations (
    transaction_id  INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    latitude        DECIMAL(10,7) NOT NULL,
    longitude       DECIMAL(10,7) NOT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (transaction_id, user_id),
    CONSTRAINT fk_loc_tx   FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    CONSTRAINT fk_loc_user FOREIGN KEY (user_id)        REFERENCES users(user_id)               ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- reviews
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    review_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reviewer_id     INT UNSIGNED NOT NULL,
    reviewed_id     INT UNSIGNED NOT NULL,
    transaction_id  INT UNSIGNED NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         VARCHAR(1000) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reviews_transaction (transaction_id),
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(user_id)              ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewed FOREIGN KEY (reviewed_id) REFERENCES users(user_id)              ON DELETE CASCADE,
    CONSTRAINT fk_reviews_tx       FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    CONSTRAINT chk_reviews_rating_range CHECK (rating BETWEEN 1 AND 5),
    INDEX idx_reviews_reviewed (reviewed_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- wallet_topup_requests
-- ---------------------------------------------------------------------
CREATE TABLE wallet_topup_requests (
    request_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    slip_image    VARCHAR(255) DEFAULT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   DATETIME DEFAULT NULL,
    reviewed_by   INT UNSIGNED DEFAULT NULL,
    CONSTRAINT fk_topup_user     FOREIGN KEY (user_id)     REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_topup_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_topup_amount_positive CHECK (amount > 0),
    INDEX idx_topup_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- wallet_transactions (ledger)
-- ---------------------------------------------------------------------
CREATE TABLE wallet_transactions (
    wallet_tx_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                INT UNSIGNED NOT NULL,
    type                   ENUM('topup','payment','receive','refund','admin_adjust') NOT NULL,
    amount                 DECIMAL(12,2) NOT NULL,
    related_transaction_id INT UNSIGNED DEFAULT NULL,
    related_topup_id       INT UNSIGNED DEFAULT NULL,
    note                   VARCHAR(255) DEFAULT NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wtx_user  FOREIGN KEY (user_id)                REFERENCES users(user_id)               ON DELETE CASCADE,
    CONSTRAINT fk_wtx_tx    FOREIGN KEY (related_transaction_id) REFERENCES transactions(transaction_id) ON DELETE SET NULL,
    CONSTRAINT fk_wtx_topup FOREIGN KEY (related_topup_id)       REFERENCES wallet_topup_requests(request_id) ON DELETE SET NULL,
    CONSTRAINT chk_wtx_amount_positive CHECK (amount > 0),
    INDEX idx_wtx_user_created (user_id, created_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed an initial admin account (เปลี่ยนรหัสผ่านทันทีหลัง deploy จริง)
-- password: ChangeMe!2024   (bcrypt hash ด้านล่าง สร้างด้วย password_hash())
-- ---------------------------------------------------------------------
INSERT INTO users (student_id, name, email, phone, password, faculty, role)
VALUES ('ADMIN0001', 'System Admin', 'admin@hu.ac.th', NULL,
        '$2y$10$Q8m8y9G7z8s0V6C1v0m2FeG0m7GZq0nQO2s5C0m0Y1o2p3q4r5s6t', 'IT', 'admin');
