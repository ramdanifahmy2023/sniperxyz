-- Capture sessions and event metadata for browser workflow auditing.
CREATE TABLE IF NOT EXISTS capture_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id VARCHAR(64) NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_capture_session (account_id, session_id, project_id),
  KEY idx_capture_sessions_project_time (project_id, started_at),
  CONSTRAINT fk_capture_sessions_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_capture_sessions_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payloads ADD COLUMN event_id VARCHAR(64) NULL AFTER id;
ALTER TABLE payloads ADD COLUMN session_id VARCHAR(64) NULL AFTER event_id;
ALTER TABLE payloads ADD COLUMN sequence_no BIGINT UNSIGNED NULL AFTER session_id;
ALTER TABLE payloads ADD COLUMN tab_id VARCHAR(128) NULL AFTER sequence_no;
ALTER TABLE payloads ADD COLUMN frame_id VARCHAR(128) NULL AFTER tab_id;
ALTER TABLE payloads ADD COLUMN navigation_id VARCHAR(128) NULL AFTER frame_id;
ALTER TABLE payloads ADD COLUMN source_type VARCHAR(16) NULL AFTER navigation_id;
ALTER TABLE payloads ADD COLUMN request_url TEXT NULL AFTER source_type;
ALTER TABLE payloads ADD COLUMN host VARCHAR(255) NULL AFTER request_url;
ALTER TABLE payloads ADD COLUMN content_type VARCHAR(255) NULL AFTER host;
ALTER TABLE payloads ADD COLUMN request_size INT UNSIGNED NULL AFTER content_type;
ALTER TABLE payloads ADD COLUMN response_size INT UNSIGNED NULL AFTER request_size;
ALTER TABLE payloads ADD COLUMN truncated TINYINT(1) NOT NULL DEFAULT 0 AFTER response_size;
ALTER TABLE payloads ADD COLUMN duration_ms INT UNSIGNED NULL AFTER truncated;
ALTER TABLE payloads ADD COLUMN hit_count BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER duration_ms;
ALTER TABLE payloads ADD COLUMN first_seen DATETIME NULL AFTER hit_count;
ALTER TABLE payloads ADD COLUMN last_seen DATETIME NULL AFTER first_seen;
ALTER TABLE payloads ADD COLUMN initiator TEXT NULL AFTER last_seen;
ALTER TABLE payloads ADD UNIQUE KEY uq_payload_event_id (event_id);
ALTER TABLE payloads ADD KEY idx_payloads_session_sequence (session_id, sequence_no);
ALTER TABLE payloads ADD KEY idx_payloads_endpoint_last_seen (endpoint_id, last_seen);
ALTER TABLE payloads ADD KEY idx_payloads_host_method (host, http_method);
