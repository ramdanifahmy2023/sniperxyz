<?php

require_once __DIR__ . '/ProcAuth.php';

class ProcAccounts extends Controller {
  public function __construct() {
    if (!ProcAuth::hasAuthenticatedSession()) {
      header('Location: ' . burl . '/auth');
      exit;
    }
  }

  public function index() {
    header('Location: ' . burl . '/panel/account');
    exit;
  }

  public function updatePassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . burl . '/panel/account');
      exit;
    }
    ProcAuth::requireCsrf();

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
      Flasher::setFlash('Gagal', 'Kata sandi baru minimal 6 karakter.', 'error');
      header('Location: ' . burl . '/panel/account');
      exit;
    }

    if ($newPassword !== $confirmPassword) {
      Flasher::setFlash('Gagal', 'Konfirmasi kata sandi tidak cocok.', 'error');
      header('Location: ' . burl . '/panel/account');
      exit;
    }

    $plainPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $accountId = $_SESSION['account']['id'];
    
    $accountModel = $this->m('AccountModel');
    if ($accountModel->update($accountId, ['password' => $plainPassword])) {
      $_SESSION['account']['password'] = $plainPassword;
      Flasher::setFlash('Berhasil', 'Kata sandi berhasil diperbarui.', 'success');
    } else {
      Flasher::setFlash('Gagal', 'Terjadi kesalahan saat memperbarui kata sandi.', 'error');
    }

    header('Location: ' . burl . '/panel/account');
    exit;
  }
  public function updateEmail() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . burl . '/panel/account'); exit;
    }
    ProcAuth::requireCsrf();
    $email = strtolower(trim($_POST['new_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      Flasher::setFlash('Gagal', 'Format email tidak valid.', 'error');
      header('Location: ' . burl . '/panel/account'); exit;
    }
    $accountModel = $this->m('AccountModel');
    $existing = $accountModel->getByEmail($email);
    if ($existing && (int) $existing['id'] !== (int) $_SESSION['account']['id']) {
      Flasher::setFlash('Gagal', 'Email sudah digunakan akun lain.', 'error');
      header('Location: ' . burl . '/panel/account'); exit;
    }
    if ($accountModel->update($_SESSION['account']['id'], ['email' => $email])) {
      $_SESSION['account']['email'] = $email;
      Flasher::setFlash('Berhasil', 'Email berhasil diperbarui.', 'success');
    } else {
      Flasher::setFlash('Gagal', 'Email gagal diperbarui.', 'error');
    }
    header('Location: ' . burl . '/panel/account'); exit;
  }
  public function getPasscode() {
    header('Content-Type: application/json');
    $code = Passcode::generate($_SESSION['account']['id']);
    echo json_encode(['passcode' => $code]);
    exit;
  }
}
