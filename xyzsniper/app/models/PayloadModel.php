<?php

class PayloadModel extends BaseModel {
  protected $table = 'payloads';

  public function checkHashExists($hash) {
    $this->db->query('SELECT id FROM payloads WHERE payload_hash = :hash LIMIT 1');
    $this->db->bind('hash', $hash);
    return $this->db->single() !== false;
  }

  public function getAllByEndpointId($endpointId) {
    $this->db->query('SELECT * FROM payloads WHERE endpoint_id = :endpoint_id ORDER BY created_at DESC');
    $this->db->bind('endpoint_id', $endpointId);
    return $this->db->getAll();
  }

  public function getPaginatedByEndpointId($endpointId, $limit, $offset) {
    $this->db->query('SELECT * FROM payloads WHERE endpoint_id = :endpoint_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
    $this->db->bind('endpoint_id', $endpointId);
    $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
    $this->db->bind('offset', (int)$offset, PDO::PARAM_INT);
    return $this->db->getAll();
  }

  public function getDistinctBaseReferers($projectId) {
    $this->db->query("
        SELECT DISTINCT SUBSTRING_INDEX(p.referer, '?', 1) as base_referer
        FROM payloads p
        JOIN endpoints e ON p.endpoint_id = e.id
        WHERE e.project_id = :project_id AND p.referer IS NOT NULL AND p.referer != ''
        ORDER BY base_referer ASC
    ");
    $this->db->bind('project_id', $projectId);
    return $this->db->getAll();
  }

  public function getCountByEndpointId($endpointId) {
    $this->db->query('SELECT COUNT(id) as total FROM payloads WHERE endpoint_id = :endpoint_id');
    $this->db->bind('endpoint_id', $endpointId);
    $result = $this->db->single();
    return $result ? (int)$result['total'] : 0;
  }

  public function createPayload($data) {
    $existing = null;
    if (!empty($data['event_id'])) {
      $this->db->query('SELECT id FROM payloads WHERE event_id=:event_id LIMIT 1');
      $this->db->bind('event_id', $data['event_id']);
      $existing = $this->db->single();
    }
    if (!$existing && !empty($data['payload_hash']) && isset($data['endpoint_id'])) {
      $this->db->query('SELECT id FROM payloads WHERE payload_hash=:hash AND endpoint_id=:endpoint_id LIMIT 1');
      $this->db->bind('hash', $data['payload_hash']);
      $this->db->bind('endpoint_id', $data['endpoint_id']);
      $existing = $this->db->single();
    }
    if ($existing) {
      $id = (int) $existing['id'];
      $data['last_seen'] = date('Y-m-d H:i:s');
      unset($data['first_seen'], $data['hit_count'], $data['created_at']);
      if (empty($data['screenshot'])) unset($data['screenshot']);
      $fields = [];
      foreach ($data as $key => $value) $fields[] = "`{$key}`=:{$key}";
      $fields[] = '`hit_count`=`hit_count`+1';
      $this->db->query('UPDATE payloads SET ' . implode(', ', $fields) . ' WHERE id=:id');
      $this->db->bind('id', $id, PDO::PARAM_INT);
      foreach ($data as $key => $value) $this->db->bind($key, $value);
      $this->db->exe();
      return $id;
    }

    if (empty($data['first_seen'])) $data['first_seen'] = date('Y-m-d H:i:s');
    if (empty($data['last_seen'])) $data['last_seen'] = $data['first_seen'];
    if (!isset($data['hit_count'])) $data['hit_count'] = 1;
    $this->insert($data);
    return $this->db->lastId();
  }
}
