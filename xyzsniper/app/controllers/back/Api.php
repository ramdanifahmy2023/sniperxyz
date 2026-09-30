<?php

class Api extends Controller {
  private const MAX_REQUEST_BYTES = 12 * 1024 * 1024;
  private const MAX_BODY_BYTES = 4 * 1024 * 1024;

  private function readJsonInput() {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > self::MAX_REQUEST_BYTES) {
      http_response_code(413);
      echo json_encode(['status' => 'error', 'message' => 'Payload terlalu besar.']);
      exit;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['status' => 'error', 'message' => 'JSON tidak valid.']);
      exit;
    }
    return $data;
  }

  private function enforceBodyLimit($value) {
    $encoded = is_string($value) ? $value : json_encode($value);
    if (is_string($encoded) && strlen($encoded) > self::MAX_BODY_BYTES) {
      http_response_code(413);
      echo json_encode(['status' => 'error', 'message' => 'Isi request atau response terlalu besar.']);
      exit;
    }
    return $value;
  }
  public function __construct() {
    // API Controller initialization
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
  }

  public function index() {
    header('Content-Type: application/json');
    echo json_encode([
      'status' => 'success',
      'message' => 'API Endpoint is ready.'
    ]);
    exit;
  }

  private function normalizeProjectHost($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (!preg_match('#^https?://#i', $value)) {
      $value = 'http://' . $value;
    }
    $parts = parse_url($value);
    if (!$parts || empty($parts['host'])) return '';
    $host = strtolower(rtrim($parts['host'], '.'));
    return $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
  }

  private function projectHostScore($payloadHost, $projectHost) {
    if ($payloadHost === '' || $projectHost === '') return -1;
    if ($payloadHost === $projectHost) return 100000 + strlen($projectHost);
    if (str_ends_with($payloadHost, '.' . $projectHost)) {
      return 1000 + strlen($projectHost);
    }
    return -1;
  }

  public function auth() {
    header('Content-Type: application/json');

    // Handle OPTIONS request for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }

    $input = $this->readJsonInput();
    
    // 1. Cek Token dari Header atau Input
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    $token = null;
    
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        }
    }
    
    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        $token = $input['token'] ?? $_POST['token'] ?? null;
    }

    $accountId = null;
    $apiToken = null;
    $tokenModel = $this->m('ExtensionTokenModel');

    if ($token) {
        // --- LOGIKA LOGIN DENGAN TOKEN ---
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Token tidak valid.'
            ]);
            exit;
        }

        $accountId = $tokenData['account_id'];
        $apiToken = $token;
        
        // Update waktu terakhir digunakan
        $tokenModel->updateLastUsed($tokenData['id']);
        
    } else {
        // --- LOGIKA LOGIN DENGAN PASSCODE ---
        $passcode = $input['passcode'] ?? $_POST['passcode'] ?? null;

        if (!$passcode) {
          echo json_encode([
            'status' => 'error',
            'message' => 'Token atau Passcode diperlukan.'
          ]);
          exit;
        }

        // Verifikasi passcode untuk mendapatkan accountId
        $accountId = Passcode::verify($passcode);

        if (!$accountId) {
          echo json_encode([
            'status' => 'error',
            'message' => 'Passcode tidak valid atau sudah kedaluwarsa.'
          ]);
          exit;
        }

        // Buat API Token baru
        $apiToken = bin2hex(random_bytes(32));
        $tokenModel->createToken($accountId, $apiToken);
    }

    // Dapatkan data akun
    $accountModel = $this->m('AccountModel');
    $account = $accountModel->getBy('id', $accountId);

    if (!$account) {
      echo json_encode([
        'status' => 'error',
        'message' => 'Akun tidak ditemukan.'
      ]);
      exit;
    }

    // Dapatkan data proyek aktif
    $projectModel = $this->m('ProjectModel');
    $activeProject = $projectModel->getActiveProjectStats($accountId);

    // Siapkan data respons sesuai format
    $responseData = [
      'token' => $apiToken,
      'user' => [
        'name' => $account['name'],
        'email' => $account['email'],
        'avatar' => $account['avatar']
      ],
      'project' => null
    ];

    if ($activeProject) {
      $responseData['project'] = [
        'id' => $activeProject['id'],
        'title' => $activeProject['title'],
        'domain' => $activeProject['domain']
      ];
    }

    echo json_encode([
      'status' => 'success',
      'data' => $responseData
    ]);
    exit;
  }
  public function capture() {
    $this->captureBulk(true);
  }

  public function captureBulk($single = false) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $match)) {
      http_response_code(401); echo json_encode(['status'=>'error','message'=>'Token diperlukan.']); exit;
    }
    $token = $this->m('ExtensionTokenModel')->getByToken($match[1]);
    if (!$token) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Token tidak valid.']); exit; }
    require_once __DIR__ . '/../../../core/CaptureInput.php';
    $input = $this->readJsonInput();
    $payloads = $single === true ? [$input] : ($input['payloads'] ?? []);
    if (!is_array($payloads) || !$payloads || count($payloads) > 100) {
      http_response_code(422); echo json_encode(['status'=>'error','message'=>'Kirim 1 sampai 100 event.']); exit;
    }
    $projects = $this->m('ProjectModel')->getAllByAccountId($token['account_id']);
    $items = []; $results = [];
    foreach ($payloads as $candidate) {
      try {
        if (!is_array($candidate)) throw new InvalidArgumentException('Event harus berupa object.');
        $items[] = CaptureInput::normalize($candidate, $projects);
      } catch (Throwable $error) {
        $results[] = ['event_id'=>is_array($candidate) ? ($candidate['event_id'] ?? $candidate['eventId'] ?? null) : null,
          'status'=>'rejected','reason'=>$error->getMessage()];
      }
    }
    try {
      $results = array_merge($results, $this->m('CaptureModel')->saveBatch((int) $token['account_id'], $items));
    } catch (Throwable $error) {
      error_log('Capture batch: ' . $error->getMessage());
      http_response_code(503); echo json_encode(['status'=>'error','message'=>'Penyimpanan gagal. Pastikan migrasi capture v2 sudah dijalankan.']); exit;
    }
    echo json_encode(['status'=>'success','data'=>['results'=>$results]], JSON_UNESCAPED_UNICODE);
    exit;
  }

  public function sessionEnd() {
    header('Content-Type: application/json');
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', trim($authHeader), $matches)) {
      http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan.']); exit;
    }
    $tokenData = $this->m('ExtensionTokenModel')->getByToken($matches[1]);
    if (!$tokenData) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Token tidak valid.']); exit; }
    $input = $this->readJsonInput();
    $sessionId = trim((string) ($input['sessionId'] ?? $input['session_id'] ?? ''));
    if ($sessionId === '') { http_response_code(422); echo json_encode(['status' => 'error', 'message' => 'session_id wajib diisi.']); exit; }
    $db = new Database();
    $db->query('UPDATE capture_sessions SET status="ended", ended_at=NOW(), last_seen_at=NOW() WHERE account_id=:account_id AND session_id=:session_id');
    $db->bind('account_id', (int) $tokenData['account_id']);
    $db->bind('session_id', substr($sessionId, 0, 64));
    $db->exe();
    echo json_encode(['status' => 'success', 'updated' => $db->row()]);
    exit;
  }
}
