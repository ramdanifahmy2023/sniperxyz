<?php

class ProjectModel extends BaseModel {
  protected $table = 'projects';

  public function getAllByAccountId($accountId) {
    $this->db->query('SELECT * FROM projects WHERE account_id = :account_id ORDER BY created_at DESC');
    $this->db->bind('account_id', $accountId);
    return $this->db->getAll();
  }

  public function getByIdAndAccountId($id, $accountId) {
    $this->db->query('SELECT * FROM projects WHERE id = :id AND account_id = :account_id LIMIT 1');
    $this->db->bind('id', $id);
    $this->db->bind('account_id', $accountId);
    return $this->db->single();
  }

  public function checkTitleExists($title, $accountId) {
    $this->db->query('SELECT id FROM projects WHERE title = :title AND account_id = :account_id LIMIT 1');
    $this->db->bind('title', $title);
    $this->db->bind('account_id', $accountId);
    $this->db->single();
    return $this->db->row() > 0;
  }

  public function checkDomainExists($domain, $accountId) {
    $this->db->query('SELECT id FROM projects WHERE domain = :domain AND account_id = :account_id LIMIT 1');
    $this->db->bind('domain', $domain);
    $this->db->bind('account_id', $accountId);
    $this->db->single();
    return $this->db->row() > 0;
  }
  
  public function createProject($data) {
    $this->insert($data);
    return $this->db->lastId();
  }

  public function setActiveProject($id, $accountId) {
    try {
      $this->db->begin();

      // Reset semua proyek menjadi tidak aktif
      $this->db->query('UPDATE projects SET is_active = "0" WHERE account_id = :account_id');
      $this->db->bind('account_id', $accountId);
      $this->db->exe();

      // Set proyek terpilih menjadi aktif
      $this->db->query('UPDATE projects SET is_active = "1" WHERE id = :id AND account_id = :account_id');
      $this->db->bind('id', $id);
      $this->db->bind('account_id', $accountId);
      $this->db->exe();
      
      $this->db->commit();
      return true;
    } catch (Exception $e) {
      $this->db->rollback();
      return false;
    }
  }

  public function getActiveProjectStats($accountId) {
    $this->db->query('
      SELECT p.*, 
             (SELECT COUNT(*) FROM endpoints WHERE project_id = p.id) as endpoints_count,
             (SELECT COUNT(*) FROM payloads WHERE endpoint_id IN (SELECT id FROM endpoints WHERE project_id = p.id)) as payloads_count
      FROM projects p
      WHERE p.account_id = :account_id AND p.is_active = "1"
      LIMIT 1
    ');
    $this->db->bind('account_id', $accountId);
    return $this->db->single();
  }

  public function getProjectChartData($projectId) {
    $this->db->query('
      SELECT DATE(created_at) as date, COUNT(*) as count 
      FROM payloads 
      WHERE endpoint_id IN (SELECT id FROM endpoints WHERE project_id = :project_id)
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 27 DAY)
      GROUP BY DATE(created_at)
      ORDER BY date ASC
    ');
    $this->db->bind('project_id', $projectId);
    $results = $this->db->getAll();

    $chartData = [];
    $maxCount = 0;
    
    // Initialize 28 days with 0
    for ($i = 27; $i >= 0; $i--) {
      $date = date('Y-m-d', strtotime("-$i days"));
      $chartData[$date] = 0;
    }

    foreach ($results as $row) {
      if (isset($chartData[$row['date']])) {
        $chartData[$row['date']] = (int)$row['count'];
        if ((int)$row['count'] > $maxCount) {
          $maxCount = (int)$row['count'];
        }
      }
    }

    return [
      'data' => $chartData,
      'max' => $maxCount > 0 ? $maxCount : 1
    ];
  }

  public function deleteProject($id, $accountId) {
    try {
      $this->db->begin();

      // Hapus payload yang terkait dengan endpoint dari proyek ini
      $this->db->query('DELETE FROM payloads WHERE endpoint_id IN (SELECT id FROM endpoints WHERE project_id = :project_id)');
      $this->db->bind('project_id', $id);
      $this->db->exe();

      // Hapus endpoint yang terkait dengan proyek ini
      $this->db->query('DELETE FROM endpoints WHERE project_id = :project_id');
      $this->db->bind('project_id', $id);
      $this->db->exe();

      // Hapus proyek utama
      $this->db->query('DELETE FROM projects WHERE id = :id AND account_id = :account_id');
      $this->db->bind('id', $id);
      $this->db->bind('account_id', $accountId);
      $this->db->exe();
      
      $deleted = $this->db->row() > 0;

      if ($deleted) {
        $this->db->commit();
        return true;
      } else {
        $this->db->rollback();
        return false;
      }
    } catch (Exception $e) {
      $this->db->rollback();
      return false;
    }
  }
}
