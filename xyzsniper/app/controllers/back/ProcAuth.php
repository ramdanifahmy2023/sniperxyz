<?php

class ProcAuth extends Controller {
  public static function csrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
  }
  public static function requireCsrf() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
      http_response_code(403); exit('CSRF token tidak valid.');
    }
  }
  public static function hasAuthenticatedSession() {
    return session_status() === PHP_SESSION_ACTIVE
      && ($_SESSION['is_authenticated'] ?? false) === true
      && is_array($_SESSION['account'] ?? null)
      && !empty($_SESSION['account']['id']);
  }

  public function index() {
    header('Location: ' . burl . '/auth');
    exit;
  }

  public function login() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      header('Location: ' . burl . '/auth');
      exit;
    }
    self::requireCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
      $this->fail('Email dan password wajib diisi.');
    }

    try {
      $accountModel = $this->m('AccountModel');
      $account = $accountModel->getByEmail($email);
    } catch (Throwable $exception) {
      error_log($exception->getMessage());
      $this->fail('Terjadi kesalahan pada server.');
    }

    if (!$account) {
      $this->fail('Alamat email belum terdaftar.');
    }

    $accountModel = $this->m('AccountModel');
    if (!$accountModel->authenticate($email, $password)) {
      if (empty($account['password'])) {
        $this->fail('Akun ini belum memiliki kata sandi, silakan login menggunakan Google.');
      }
      $this->fail('Kata sandi yang Anda masukkan salah.');
    }

    $account = $accountModel->getByEmail($email);
    session_regenerate_id(true);
    $_SESSION['account'] = $account;
    $_SESSION['is_authenticated'] = true;

    header('Location: ' . burl . '/panel');
    exit;
  }

  public function logout() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: ' . burl . '/auth');
    exit;
  }

  public function google() {
    if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
      $this->fail('Login Google belum dikonfigurasi.');
    }

    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;

    $query = http_build_query([
      'client_id' => GOOGLE_CLIENT_ID,
      'redirect_uri' => GOOGLE_REDIRECT_URI,
      'response_type' => 'code',
      'scope' => 'openid email profile',
      'state' => $state,
      'prompt' => 'select_account'
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    exit;
  }

  public function googleCallback() {
    if (!isset($_GET['state']) || !hash_equals($_SESSION['google_oauth_state'] ?? '', $_GET['state'])) {
      $this->fail('Sesi login Google tidak valid.');
    }

    unset($_SESSION['google_oauth_state']);

    if (isset($_GET['error']) || empty($_GET['code'])) {
      $this->fail('Login Google dibatalkan.');
    }

    try {
      $token = $this->requestGoogleToken($_GET['code']);
      $profile = $this->requestGoogleProfile($token['access_token']);
      $accountModel = $this->m('AccountModel');

      if (self::hasAuthenticatedSession()) {
        $accountModel->update($_SESSION['account']['id'], [
          'google_id' => $profile['sub']
        ]);
        $_SESSION['account']['google_id'] = $profile['sub'];
        $_SESSION['account']['google_email'] = $profile['email'];

        header('Location: ' . burl . '/panel/account');
        exit;
      }

      $account = $accountModel->saveGoogleAccount([
        'google_id' => $profile['sub'],
        'email' => $profile['email'],
        'name' => $profile['name'] ?? ''
      ]);

      if ($account && !empty($profile['picture'])) {
        try {
          $localAvatarPath = $this->saveGoogleAvatar($profile['picture'], $account['id']);
          if ($localAvatarPath !== null) {
            $account = $accountModel->updateAvatarPath($account['id'], $localAvatarPath);
          }
        } catch (Throwable $avatarException) {
          error_log('Google avatar skipped: ' . $avatarException->getMessage());
        }
      }
    } catch (Throwable $exception) {
      error_log($exception->getMessage());
      $this->fail('Login Google gagal diproses.');
    }

    if (!$account) {
      $this->fail('Data akun Google tidak dapat disimpan.');
    }

    session_regenerate_id(true);
    $_SESSION['account'] = $account;
    $_SESSION['is_authenticated'] = true;

    header('Location: ' . burl . '/panel');
    exit;
  }

  private function requestGoogleToken($code) {
    $curl = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($curl, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
      ]),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status !== 200 || $error !== '') {
      throw new RuntimeException('Google token request failed: ' . $error);
    }

    $token = json_decode($response, true);

    if (empty($token['access_token'])) {
      throw new RuntimeException('Google access token missing.');
    }

    return $token;
  }

  private function requestGoogleProfile($accessToken) {
    $curl = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
    curl_setopt_array($curl, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken]
    ]);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status !== 200 || $error !== '') {
      throw new RuntimeException('Google profile request failed: ' . $error);
    }

    $profile = json_decode($response, true);

    if (empty($profile['sub']) || empty($profile['email']) || empty($profile['email_verified'])) {
      throw new RuntimeException('Google profile is incomplete.');
    }

    return $profile;
  }

  private function saveGoogleAvatar($avatarUrl, $accountId) {
    $parsedUrl = parse_url($avatarUrl);
    if (($parsedUrl['scheme'] ?? '') !== 'https' || empty($parsedUrl['host'])) {
      throw new RuntimeException('Google avatar URL is invalid.');
    }

    $curl = curl_init($avatarUrl);
    curl_setopt_array($curl, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 3,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_MAXFILESIZE => 5 * 1024 * 1024,
      CURLOPT_USERAGENT => app_name . ' avatar downloader'
    ]);
    $imageData = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $contentType = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
    $error = curl_error($curl);
    curl_close($curl);

    if ($imageData === false || $error !== '' || $status !== 200 || $imageData === '') {
      throw new RuntimeException('Google avatar download failed: ' . $error);
    }

    if (strlen($imageData) > 5 * 1024 * 1024) {
      throw new RuntimeException('Google avatar is too large.');
    }

    $extension = $this->getImageExtension($contentType, $imageData);
    if ($extension === null) {
      throw new RuntimeException('Google avatar format is not supported.');
    }

    $avatarDirectory = dirname(__DIR__, 3) . '/public/assets/images/avatars/' . (int) $accountId;
    if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
      throw new RuntimeException('Avatar directory could not be created.');
    }

    $avatarFile = $avatarDirectory . '/avatar.' . $extension;
    $temporaryFile = tempnam($avatarDirectory, 'avatar_');
    if ($temporaryFile === false || file_put_contents($temporaryFile, $imageData, LOCK_EX) === false) {
      throw new RuntimeException('Google avatar could not be saved.');
    }

    if (!rename($temporaryFile, $avatarFile)) {
      @unlink($temporaryFile);
      throw new RuntimeException('Google avatar could not be replaced.');
    }

    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $oldExtension) {
      $oldFile = $avatarDirectory . '/avatar.' . $oldExtension;
      if ($oldFile !== $avatarFile && is_file($oldFile)) {
        @unlink($oldFile);
      }
    }

    return images . '/avatars/' . (int) $accountId . '/avatar.' . $extension;
  }

  private function getImageExtension($contentType, $imageData) {
    $extensions = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/gif' => 'gif'
    ];

    $contentType = trim(explode(';', $contentType)[0]);
    if (isset($extensions[$contentType])) {
      return $extensions[$contentType];
    }

    $imageInfo = @getimagesizefromstring($imageData);
    $mimeType = $imageInfo['mime'] ?? '';
    return $extensions[$mimeType] ?? null;
  }

  private function fail($message) {
    Flasher::setFlash('Gagal', $message, 'error');
    header('Location: ' . burl . '/auth');
    exit;
  }

  public function unlinkGoogle() {
    if (!self::hasAuthenticatedSession()) {
      header('Location: ' . burl . '/auth');
      exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
      header('Location: ' . burl . '/panel/account');
      exit;
    }
    self::requireCsrf();

    // Pastikan pengguna sudah mengatur password manual sebelum memutuskan tautan
    $pwd = $_SESSION['account']['password'] ?? null;
    $has_manual_password = !empty($pwd);

    if (!$has_manual_password) {
      Flasher::setFlash('Gagal', 'Wajib memiliki kata sandi dulu sebelum memutuskan tautan Google.', 'error');
      header('Location: ' . burl . '/panel/account');
      exit;
    }

    $accountModel = $this->m('AccountModel');
    $accountModel->update($_SESSION['account']['id'], ['google_id' => null]);
    $_SESSION['account']['google_id'] = null;
    unset($_SESSION['account']['google_email']);

    header('Location: ' . burl . '/panel/account');
    exit;
  }
}
