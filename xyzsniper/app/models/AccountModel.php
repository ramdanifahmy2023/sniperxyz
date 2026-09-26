<?php

class AccountModel extends BaseModel {
  protected $table = 'accounts';

  public function authenticate($email, $password) {
    $this->db->query('SELECT * FROM accounts WHERE email = :email LIMIT 1');
    $this->db->bind('email', $email);
    $account = $this->db->single();

    if (!$account || !array_key_exists('password', $account)) {
      return false;
    }

    if (empty($account['password'])) return false;
    if (password_verify($password, $account['password'])) return $account;
    if (hash_equals((string) $account['password'], (string) $password)) {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $this->update($account['id'], ['password' => $hash]);
      $account['password'] = $hash;
      return $account;
    }
    return false;
  }

  public function getByGoogleId($googleId) {
    $this->db->query('SELECT * FROM accounts WHERE google_id = :google_id LIMIT 1');
    $this->db->bind('google_id', $googleId);
    return $this->db->single();
  }

  public function getByEmail($email) {
    $this->db->query('SELECT * FROM accounts WHERE email = :email LIMIT 1');
    $this->db->bind('email', $email);
    return $this->db->single();
  }

  public function saveGoogleAccount($profile) {
    $account = $this->getByGoogleId($profile['google_id']);

    if ($account) {
      $this->update($account['id'], [
        'email' => $profile['email'],
        'name' => $profile['name']
      ]);
      return $this->getByGoogleId($profile['google_id']);
    }

    $account = $this->getByEmail($profile['email']);

    if ($account) {
      $this->update($account['id'], [
        'google_id' => $profile['google_id'],
        'name' => $profile['name']
      ]);
      return $this->getByGoogleId($profile['google_id']);
    }

    $this->insert([
      'google_id' => $profile['google_id'],
      'email' => $profile['email'],
      'password' => null,
      'name' => $profile['name'],
      'avatar' => null
    ]);

    return $this->getByGoogleId($profile['google_id']);
  }

  public function updateAvatarPath($accountId, $avatarPath) {
    $this->update($accountId, [
      'avatar' => $avatarPath
    ]);

    $this->db->query('SELECT * FROM accounts WHERE id = :id LIMIT 1');
    $this->db->bind('id', $accountId);
    return $this->db->single();
  }
}
