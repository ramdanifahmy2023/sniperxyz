<?php

class EndpointModel extends BaseModel {
  protected $table = 'endpoints';

  public function getByPathAndProjectId($path, $projectId) {
    $this->db->query('SELECT * FROM endpoints WHERE path = :path AND project_id = :project_id LIMIT 1');
    $this->db->bind('path', $path);
    $this->db->bind('project_id', $projectId);
    return $this->db->single();
  }

  public function getAllByProjectId($projectId) {
    $this->db->query('
        SELECT e.*, 
               (SELECT COUNT(id) FROM payloads p WHERE p.endpoint_id = e.id) as payload_count 
        FROM endpoints e 
        WHERE e.project_id = :project_id 
        ORDER BY e.created_at DESC
    ');
    $this->db->bind('project_id', $projectId);
    return $this->db->getAll();
  }

  public function getPaginatedByProjectId($projectId, $limit, $offset, $referer = null) {
    $query = '
        SELECT e.*, 
               (SELECT COUNT(id) FROM payloads p WHERE p.endpoint_id = e.id) as payload_count 
        FROM endpoints e 
    ';
    if ($referer) {
        $query .= ' JOIN payloads p_ref ON e.id = p_ref.endpoint_id ';
    }
    $query .= ' WHERE e.project_id = :project_id ';
    if ($referer) {
        $query .= ' AND SUBSTRING_INDEX(p_ref.referer, "?", 1) = :referer ';
        $query .= ' GROUP BY e.id ';
    }
    $query .= ' ORDER BY e.created_at DESC LIMIT :limit OFFSET :offset ';
    
    $this->db->query($query);
    $this->db->bind('project_id', $projectId);
    if ($referer) {
        $this->db->bind('referer', $referer);
    }
    $this->db->bind('limit', (int)$limit, PDO::PARAM_INT);
    $this->db->bind('offset', (int)$offset, PDO::PARAM_INT);
    return $this->db->getAll();
  }

  public function getCountByProjectId($projectId, $referer = null) {
    $query = 'SELECT COUNT(DISTINCT e.id) as total FROM endpoints e ';
    if ($referer) {
        $query .= ' JOIN payloads p_ref ON e.id = p_ref.endpoint_id ';
    }
    $query .= ' WHERE e.project_id = :project_id ';
    if ($referer) {
        $query .= ' AND SUBSTRING_INDEX(p_ref.referer, "?", 1) = :referer ';
    }
    
    $this->db->query($query);
    $this->db->bind('project_id', $projectId);
    if ($referer) {
        $this->db->bind('referer', $referer);
    }
    $result = $this->db->single();
    return $result ? (int)$result['total'] : 0;
  }

  public function searchEndpointsByKeyword($projectId, $keyword, $referer = null) {
    $query = '
        SELECT e.*, 
               (SELECT COUNT(id) FROM payloads p2 WHERE p2.endpoint_id = e.id) as payload_count 
        FROM endpoints e 
        LEFT JOIN payloads p ON e.id = p.endpoint_id
        WHERE e.project_id = :project_id 
          AND (e.path LIKE :kw OR e.label LIKE :kw OR p.response_body LIKE :kw OR p.request_body LIKE :kw OR e.request_header LIKE :kw)
    ';
    
    if ($referer) {
        $query .= ' AND SUBSTRING_INDEX(p.referer, "?", 1) = :referer ';
    }
    
    $query .= ' GROUP BY e.id ORDER BY e.created_at DESC ';
    
    $this->db->query($query);
    $this->db->bind('project_id', $projectId);
    $this->db->bind('kw', '%' . $keyword . '%');
    if ($referer) {
        $this->db->bind('referer', $referer);
    }
    return $this->db->getAll();
  }

  public function createEndpoint($data) {
    $this->insert($data);
    return $this->db->lastId();
  }

  public function updateEndpoint($id, $data) {
    return $this->update($id, $data);
  }

  public function clearByProjectId($projectId) {
    // Hapus payloads terlebih dahulu
    $this->db->query('DELETE FROM payloads WHERE endpoint_id IN (SELECT id FROM endpoints WHERE project_id = :project_id)');
    $this->db->bind('project_id', $projectId);
    $this->db->exe();

    // Hapus endpoints
    $this->db->query('DELETE FROM endpoints WHERE project_id = :project_id');
    $this->db->bind('project_id', $projectId);
    $this->db->exe();
    
    return true;
  }
}
