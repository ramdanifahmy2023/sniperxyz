-- Run once after taking a database backup. Safe to rerun after checking index names.
START TRANSACTION;
CREATE TEMPORARY TABLE endpoint_merge AS
SELECT e.id AS duplicate_id, MIN(c.id) AS canonical_id
FROM endpoints e
JOIN endpoints c ON c.project_id = e.project_id AND c.path = e.path
GROUP BY e.id
HAVING e.id <> MIN(c.id);
UPDATE payloads p JOIN endpoint_merge m ON m.duplicate_id = p.endpoint_id
SET p.endpoint_id = m.canonical_id;
DELETE e FROM endpoints e JOIN endpoint_merge m ON m.duplicate_id = e.id;
DROP TEMPORARY TABLE endpoint_merge;
CREATE TEMPORARY TABLE payload_merge AS
SELECT p.id AS duplicate_id FROM payloads p
JOIN payloads keep ON keep.endpoint_id = p.endpoint_id
  AND keep.payload_hash = p.payload_hash
  AND keep.payload_hash IS NOT NULL AND keep.payload_hash <> ''
  AND keep.id < p.id;
DELETE p FROM payloads p JOIN payload_merge m ON m.duplicate_id = p.id;
DROP TEMPORARY TABLE payload_merge;
COMMIT;
ALTER TABLE endpoints ADD UNIQUE KEY uq_endpoints_project_path (project_id, path(191));
ALTER TABLE payloads ADD UNIQUE KEY uq_payloads_endpoint_hash (endpoint_id, payload_hash(191));
