<?php

/**
 * Writes a capture batch with one database connection and one transaction.
 * The API prepares and validates the project routing before calling this model.
 */
class CaptureModel extends BaseModel {
  public function saveBatch($accountId, array $items) {
    $results = [];
    $this->db->begin();

    try {
      foreach ($items as $item) {
        $results[] = $this->saveItem((int) $accountId, $item);
      }
      $this->db->commit();
      return $results;
    } catch (Throwable $exception) {
      $this->db->rollback();
      throw $exception;
    }
  }

  private function saveItem($accountId, array $item) {
    $projectId = (int) ($item['project_id'] ?? 0);
    $path = (string) ($item['path'] ?? '/');
    $methods = $item['methods'] ?? [];
    $requestHeaders = $item['request_headers'] ?? [];
    $requestHeaderJson = is_string($requestHeaders) ? $requestHeaders : json_encode($requestHeaders, JSON_UNESCAPED_SLASHES);

    $this->db->query('SELECT id FROM projects WHERE id=:project_id AND account_id=:account_id LIMIT 1 FOR UPDATE');
    $this->db->bind('project_id', $projectId);
    $this->db->bind('account_id', $accountId);
    if (!$this->db->single()) {
      throw new RuntimeException('Project capture tidak dimiliki akun token.');
    }

    $this->db->query('INSERT INTO endpoints (project_id,path,origin,endpoint_key,request_header,`get`,post,put,patch,`delete`,head,`option`)
      VALUES (:project_id,:path,:origin,:endpoint_key,:request_header,:get,:post,:put,:patch,:delete,:head,:option)
      ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), request_header=IF(VALUES(request_header) <> "", VALUES(request_header), request_header),
      `get`=GREATEST(`get`,VALUES(`get`)), post=GREATEST(post,VALUES(post)), put=GREATEST(put,VALUES(put)),
      patch=GREATEST(patch,VALUES(patch)), `delete`=GREATEST(`delete`,VALUES(`delete`)), head=GREATEST(head,VALUES(head)),
      `option`=GREATEST(`option`,VALUES(`option`))');
    $this->db->bind('project_id', $projectId);
    $this->db->bind('path', $path);
    $this->db->bind('origin', $item['origin'] ?? '');
    $this->db->bind('endpoint_key', $item['endpoint_key'] ?? hash('sha256', $path));
    $this->db->bind('request_header', $requestHeaderJson ?: '');
    foreach (['get','post','put','patch','delete','head','option'] as $method) {
      $this->db->bind($method, !empty($methods[$method]) ? 1 : 0, PDO::PARAM_INT);
    }
    $this->db->exe();
    $endpointId = (int) $this->db->lastId();

    if ($endpointId < 1) {
      throw new RuntimeException('Endpoint capture gagal dibuat.');
    }

    $sessionId = trim((string) ($item['session_id'] ?? ''));
    if ($sessionId !== '') {
      $this->db->query('INSERT INTO capture_sessions (session_id,account_id,project_id,title,status,started_at,last_seen_at)
        VALUES (:session_id,:account_id,:project_id,:title,"active",COALESCE(:started_at,NOW()),NOW())
        ON DUPLICATE KEY UPDATE last_seen_at=NOW()');
      $this->db->bind('session_id', substr($sessionId, 0, 64));
      $this->db->bind('account_id', $accountId);
      $this->db->bind('project_id', $projectId);
      $this->db->bind('title', substr((string) ($item['session_title'] ?? ''), 0, 255));
      $this->db->bind('started_at', !empty($item['session_started_at']) ? $item['session_started_at'] : null);
      $this->db->exe();
    }

    $eventId = trim((string) ($item['event_id'] ?? ''));
    $payloadHash = trim((string) ($item['payload_hash'] ?? ''));
    $existingId = null;
    if ($eventId !== '') {
      $this->db->query('SELECT p.id,e.project_id FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id
        WHERE p.event_id=:event_id AND pr.account_id=:account_id LIMIT 1');
      $this->db->bind('event_id', substr($eventId, 0, 64));
      $this->db->bind('account_id', $accountId);
      $existing = $this->db->single();
      if ($existing && (int)$existing['project_id'] !== $projectId) return ['event_id'=>$eventId,'status'=>'rejected','reason'=>'event_id sudah dipakai pada proyek lain.'];
      $existingId = $existing ? (int) $existing['id'] : null;
    }
    if ($existingId !== null) return ['event_id' => $eventId, 'payload_id' => $existingId, 'status' => 'duplicate'];

    $now = date('Y-m-d H:i:s');
    $data = [
      'endpoint_id' => $endpointId,
      'account_id' => $accountId,
      'request_header' => $requestHeaderJson,
      'occurred_at' => $item['occurred_at'] ?? null,
      'meta_json' => $item['meta_json'] ?? null,
      'capture_version' => $item['capture_version'] ?? 2,
      'event_id' => $eventId !== '' ? substr($eventId, 0, 64) : null,
      'session_id' => $sessionId !== '' ? substr($sessionId, 0, 64) : null,
      'sequence_no' => isset($item['sequence_no']) ? (int) $item['sequence_no'] : null,
      'tab_id' => substr((string) ($item['tab_id'] ?? ''), 0, 128) ?: null,
      'frame_id' => substr((string) ($item['frame_id'] ?? ''), 0, 128) ?: null,
      'navigation_id' => substr((string) ($item['navigation_id'] ?? ''), 0, 128) ?: null,
      'source_type' => substr((string) ($item['source_type'] ?? ''), 0, 16) ?: null,
      'request_url' => $item['request_url'] ?? null,
      'host' => substr((string) ($item['host'] ?? ''), 0, 255) ?: null,
      'content_type' => substr((string) ($item['content_type'] ?? ''), 0, 255) ?: null,
      'request_size' => isset($item['request_size']) ? (int) $item['request_size'] : null,
      'response_size' => isset($item['response_size']) ? (int) $item['response_size'] : null,
      'truncated' => !empty($item['truncated']) ? 1 : 0,
      'duration_ms' => isset($item['duration_ms']) ? (int) $item['duration_ms'] : null,
      'request_body' => $item['request_body'] ?? null,
      'query_params' => $item['query_params'] ?? '',
      'response_body' => $item['response_body'] ?? null,
      'status_code' => isset($item['status_code']) ? (int) $item['status_code'] : null,
      'http_method' => strtoupper(substr((string) ($item['http_method'] ?? 'GET'), 0, 16)),
      'referer' => $item['referer'] ?? null,
      'response_header' => is_string($item['response_headers'] ?? null) ? $item['response_headers'] : json_encode($item['response_headers'] ?? [], JSON_UNESCAPED_SLASHES),
      'payload_hash' => $payloadHash !== '' ? $payloadHash : null,
      'screenshot' => $item['screenshot'] ?? null,
      'initiator' => $item['initiator'] ?? null
    ];

    $data['first_seen'] = $now;
    $data['last_seen'] = $now;
    $data['hit_count'] = 1;
    $columns = array_keys($data);
    $this->db->query('INSERT INTO payloads (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')');
    $this->bindPayload($data);
    $this->db->exe();
    return ['event_id' => $eventId, 'payload_id' => (int) $this->db->lastId(), 'status' => 'stored'];
  }

  private function bindPayload(array $data) {
    foreach ($data as $key => $value) {
      $type = is_int($value) ? PDO::PARAM_INT : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR);
      $this->db->bind($key, $value, $type);
    }
  }
}
