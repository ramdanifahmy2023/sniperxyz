<?php

class BaseModel {
  // Database Instance
  protected $db;

  // Table Name
  protected $table = '';

  // Constructor
  public function __construct() {
    $this->db = new Database();
  }

  // Get All Records
  public function getAll() {
    $this->db->query("SELECT * FROM {$this->table}");
    return $this->db->getAll();
  }

  // Get Single Record By Column
  public function getBy($column, $value) {
    $this->db->query("SELECT * FROM {$this->table} WHERE {$column} = :{$column}");
    $this->db->bind($column, $value);
    return $this->db->single();
  }

  // Get All Records By Column
  public function getAllBy($column, $value) {
    $this->db->query("SELECT * FROM {$this->table} WHERE {$column} = :{$column}");
    $this->db->bind($column, $value);
    return $this->db->getAll();
  }

  // Insert Record
  public function insert($data = []) {
    $columns = '`' . implode('`, `', array_keys($data)) . '`';
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $this->db->query("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})");
    
    foreach ($data as $key => $value) {
      $this->db->bind($key, $value);
    }
    
    $this->db->exe();
    return $this->db->row();
  }

  // Update Record
  public function update($id, $data = []) {
    $fields = [];
    foreach ($data as $key => $value) {
      $fields[] = "`{$key}` = :{$key}";
    }
    $setString = implode(', ', $fields);

    $this->db->query("UPDATE {$this->table} SET {$setString} WHERE id = :id");
    
    $this->db->bind('id', $id);
    foreach ($data as $key => $value) {
      $this->db->bind($key, $value);
    }

    $this->db->exe();
    return $this->db->row();
  }

  // Delete Record
  public function delete($id) {
    $this->db->query("DELETE FROM {$this->table} WHERE id = :id");
    $this->db->bind('id', $id);
    
    $this->db->exe();
    return $this->db->row();
  }

  // Get Records With Multiple Conditions
  public function getWhere($conditions = []) {
    $whereParts = [];
    foreach ($conditions as $key => $value) {
      $whereParts[] = "{$key} = :{$key}";
    }
    $whereString = implode(' AND ', $whereParts);
    
    $this->db->query("SELECT * FROM {$this->table} WHERE {$whereString}");
    foreach ($conditions as $key => $value) {
      $this->db->bind($key, $value);
    }
    return $this->db->getAll();
  }

  // Search Records
  public function search($column, $keyword) {
    $this->db->query("SELECT * FROM {$this->table} WHERE {$column} LIKE :keyword");
    $this->db->bind('keyword', "%{$keyword}%");
    return $this->db->getAll();
  }

  // Count Total Records
  public function count() {
    $this->db->query("SELECT COUNT(*) as total FROM {$this->table}");
    return $this->db->single()['total'];
  }
}
