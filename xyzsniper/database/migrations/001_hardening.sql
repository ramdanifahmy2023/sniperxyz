-- Run after reviewing duplicate counts; each delete keeps the newest row.
START TRANSACTION;
DELETE e1 FROM endpoints e1 JOIN endpoints e2
  ON e1.project_id=e2.project_id AND e1.path=e2.path AND e1.id<e2.id;
DELETE p1 FROM payloads p1 JOIN payloads p2
  ON p1.endpoint_id=p2.endpoint_id AND p1.payload_hash IS NOT NULL
 AND p1.payload_hash<>'' AND p1.payload_hash=p2.payload_hash AND p1.id<p2.id;
COMMIT;
ALTER TABLE endpoints ADD UNIQUE KEY uq_endpoints_project_path (project_id, path(191));
ALTER TABLE payloads ADD UNIQUE KEY uq_payloads_endpoint_hash (endpoint_id, payload_hash(191));
-- Normalize existing domains manually or with the application normalizer before
-- enforcing this check; new writes must store host[:port] only.
