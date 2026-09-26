<?php

class ExtensionTokenModel extends BaseModel {
  protected $table = 'extension_tokens';

  // Menyimpan token baru untuk sebuah akun
  public function createToken($accountId, $token) {
    return $this->insert([
      'account_id' => $accountId,
      'token' => $token
    ]);
  }

  // Mengambil data token
  public function getByToken($token) {
    $this->db->query('SELECT * FROM extension_tokens WHERE token = :token AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP) LIMIT 1');
    $this->db->bind('token', $token);
    return $this->db->single();
  }

  // Mengupdate waktu terakhir digunakan (last_used_at)
  public function updateLastUsed($id) {
    $this->db->query("UPDATE {$this->table} SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id");
    $this->db->bind('id', $id);
    $this->db->exe();
    return $this->db->row();
  }
}
