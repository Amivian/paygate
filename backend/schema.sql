-- Payment Platform Database Schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS payment_platform
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE payment_platform;

-- ============================================
-- Transactions Table
-- Core table for tracking all payment attempts.
-- `reference` is the idempotency key.
-- ============================================
CREATE TABLE IF NOT EXISTS transactions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference       VARCHAR(100)  NOT NULL UNIQUE,
  email           VARCHAR(255)  NOT NULL,
  full_name       VARCHAR(255)  DEFAULT NULL,
  amount          INT UNSIGNED  NOT NULL COMMENT 'Amount in kobo (smallest currency unit)',
  currency        VARCHAR(3)    NOT NULL DEFAULT 'NGN',
  status          ENUM('pending', 'success', 'failed', 'abandoned') NOT NULL DEFAULT 'pending',
  paystack_id     BIGINT UNSIGNED DEFAULT NULL,
  authorization_url TEXT         DEFAULT NULL,
  access_code     VARCHAR(100)  DEFAULT NULL,
  channel         VARCHAR(50)   DEFAULT NULL COMMENT 'card, bank, ussd, etc.',
  ip_address      VARCHAR(45)   DEFAULT NULL,
  paid_at         DATETIME      DEFAULT NULL,
  metadata        JSON          DEFAULT NULL,
  retry_count     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  webhook_processed TINYINT(1)  NOT NULL DEFAULT 0,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_email (email),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Payment Logs Table
-- Audit trail for every Paystack API call attempt.
-- Used for debugging retries and tracing failures.
-- ============================================
CREATE TABLE IF NOT EXISTS payment_logs (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference       VARCHAR(100)  NOT NULL,
  action          VARCHAR(50)   NOT NULL COMMENT 'initialize, verify, list, webhook',
  status          VARCHAR(20)   NOT NULL COMMENT 'success, failed, retry',
  request_body    JSON          DEFAULT NULL,
  response_body   JSON          DEFAULT NULL,
  error_message   TEXT          DEFAULT NULL,
  attempt         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_reference (reference),
  INDEX idx_action (action),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
