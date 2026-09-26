CREATE DATABASE IF NOT EXISTS xyzsniper_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE xyzsniper_db;

CREATE TABLE IF NOT EXISTS accounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  google_id VARCHAR(255) NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NULL,
  name VARCHAR(255) NOT NULL,
  avatar TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_accounts_email (email),
  UNIQUE KEY uq_accounts_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  logo TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_account_id (account_id),
  CONSTRAINT fk_projects_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS endpoints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  path TEXT NOT NULL,
  label VARCHAR(255) NULL,
  request_header LONGTEXT NULL,
  `get` TINYINT(1) NOT NULL DEFAULT 0,
  `post` TINYINT(1) NOT NULL DEFAULT 0,
  `put` TINYINT(1) NOT NULL DEFAULT 0,
  `patch` TINYINT(1) NOT NULL DEFAULT 0,
  `delete` TINYINT(1) NOT NULL DEFAULT 0,
  `head` TINYINT(1) NOT NULL DEFAULT 0,
  `option` TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_endpoints_project_id (project_id),
  CONSTRAINT fk_endpoints_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payloads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  endpoint_id INT UNSIGNED NOT NULL,
  request_body LONGTEXT NULL,
  query_params TEXT NULL,
  response_body LONGTEXT NULL,
  status_code INT NULL,
  http_method VARCHAR(16) NOT NULL DEFAULT 'GET',
  referer TEXT NULL,
  response_header LONGTEXT NULL,
  payload_hash VARCHAR(255) NULL,
  screenshot TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payloads_endpoint_id (endpoint_id),
  KEY idx_payloads_hash_endpoint (payload_hash, endpoint_id),
  CONSTRAINT fk_payloads_endpoint FOREIGN KEY (endpoint_id) REFERENCES endpoints (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extension_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id INT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  last_used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_extension_tokens_token (token),
  KEY idx_extension_tokens_account_id (account_id),
  CONSTRAINT fk_extension_tokens_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
