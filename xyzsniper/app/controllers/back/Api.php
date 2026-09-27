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
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }

    // 1. Validasi Token Auth
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
    }
    
    if (!$token) {
        echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan.']);
        exit;
    }

    $tokenModel = $this->m('ExtensionTokenModel');
    $tokenData = $tokenModel->getByToken($token);
    
    if (!$tokenData) {
        echo json_encode(['status' => 'error', 'message' => 'Token tidak valid.']);
        exit;
    }

    $accountId = $tokenData['account_id'];

    // 2. Dapatkan Project Aktif
    $projectModel = $this->m('ProjectModel');
    // Ambil project aktif dari token/akun ini
    // Memanfaatkan method existing atau ambil list lalu filter
    $projects = $projectModel->getAllByAccountId($accountId);
    $activeProject = null;
    foreach ($projects as $p) {
        if ((int) $p['is_active'] === 1) {
            $activeProject = $p;
            break;
        }
    }

    if (!$activeProject) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada proyek aktif.']);
        exit;
    }
    $projectId = $activeProject['id'];

    // 3. Parsing Input dari Ekstensi
    $input = $this->readJsonInput();
    
    if (!$input || empty($input['url'])) {
        echo json_encode(['status' => 'error', 'message' => 'Data payload tidak lengkap.']);
        exit;
    }

    // Parsing Path dari URL
    $parsedUrl = parse_url($input['url']);
    $path = $parsedUrl['path'] ?? '/';

    $requestHeaders = $input['requestHeaders'] ?? [];
    $responseHeaders = $input['responseHeaders'] ?? [];

    $endpointModel = $this->m('EndpointModel');
    $endpoint = $endpointModel->getByPathAndProjectId($path, $projectId);

    // Identifikasi Methods yang diizinkan (dari responseHeaders -> access-control-allow-methods)
    $allowMethodsStr = '';
    foreach ($responseHeaders as $key => $val) {
        if (strtolower($key) === 'access-control-allow-methods') {
            $allowMethodsStr = $val;
            break;
        }
    }

    // Default method flags
    $methods = [
        'get' => 0, 'post' => 0, 'put' => 0, 'patch' => 0, 
        'delete' => 0, 'head' => 0, 'option' => 0
    ];

    if (!empty($allowMethodsStr)) {
        $allowed = array_map('trim', explode(',', strtolower($allowMethodsStr)));
        foreach ($allowed as $m) {
            if ($m === 'options') $m = 'option';
            if (isset($methods[$m])) {
                $methods[$m] = 1;
            }
        }
    } else {
        // Fallback: gunakan method request saat ini
        $currentMethod = strtolower($input['method'] ?? '');
        if ($currentMethod === 'options') $currentMethod = 'option';
        if (isset($methods[$currentMethod])) {
            $methods[$currentMethod] = 1;
        }
    }

    // Data yang akan disimpan ke tabel endpoints
    $dataToSave = array_merge([
        'project_id' => $projectId,
        'path' => $path,
        'request_header' => json_encode($requestHeaders)
    ], $methods);

    if ($endpoint) {
        // Jika sudah ada, update metodenya dan header terbarunya
        $endpointModel->updateEndpoint($endpoint['id'], $dataToSave);
        $endpointId = $endpoint['id'];
    } else {
        // Buat endpoint baru
        $endpointId = $endpointModel->createEndpoint($dataToSave);
    }

    // --- PAYLOAD SAVING LOGIC ---
    $payloadModel = $this->m('PayloadModel');
    $payloadHash = $input['payloadHash'] ?? '';
    $eventId = trim((string) ($input['eventId'] ?? $input['event_id'] ?? '')) ?: bin2hex(random_bytes(16));


    // Handle Screenshot (Base64 to File)
    $screenshotUrl = null;
    $base64Image = $input['screenshot'] ?? '';
    
    if (!empty($base64Image) && preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
        $data = substr($base64Image, strpos($base64Image, ',') + 1);
        $data = base64_decode($data, true);
        if ($data === false || strlen($data) > self::MAX_BODY_BYTES) {
          http_response_code(413); echo json_encode(['status' => 'error', 'message' => 'Screenshot terlalu besar.']); exit;
        }
        $extension = strtolower($type[1]);
        
        $filename = 'shot_' . time() . '_' . substr($payloadHash, 0, 8) . '.' . $extension;
        
        // Path absolut menuju public/assets/images/screenshots/{projectId}/
        $dir = __DIR__ . '/../../../public/assets/images/screenshots/' . $projectId . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $filepath = $dir . $filename;
        if (file_put_contents($filepath, $data)) {
            $screenshotUrl = 'assets/images/screenshots/' . $projectId . '/' . $filename;
        }
    }

    // Persiapkan Data Payload
        $queryParams = $input['queryParams'] ?? '';
        $reqBodyRaw = $input['requestBody'] ?? null;
        $this->enforceBodyLimit($reqBodyRaw);
    $requestBodyStr = is_array($reqBodyRaw) ? json_encode($reqBodyRaw) : $reqBodyRaw;
    
        $resBodyRaw = $input['responseBody'] ?? null;
        $this->enforceBodyLimit($resBodyRaw);
    $responseBodyStr = is_array($resBodyRaw) ? json_encode($resBodyRaw) : $resBodyRaw;
    $responseHeadersStr = is_array($responseHeaders) ? json_encode($responseHeaders) : null;
    $referer = $input['pageUrl'] ?? null;

    $payloadData = [
      'endpoint_id' => $endpointId,
        'event_id' => $eventId,
        'session_id' => $input['sessionId'] ?? $input['session_id'] ?? null,
        'sequence_no' => $input['sequenceNo'] ?? $input['sequence_no'] ?? null,
        'tab_id' => $input['tabId'] ?? $input['tab_id'] ?? null,
        'frame_id' => $input['frameId'] ?? $input['frame_id'] ?? null,
        'navigation_id' => $input['navigationId'] ?? $input['navigation_id'] ?? null,
        'source_type' => $input['sourceType'] ?? $input['source_type'] ?? null,
        'request_url' => $input['url'],
        'host' => $this->normalizeProjectHost($input['url']),
        'content_type' => $input['contentType'] ?? null,
        'request_size' => $requestBodyStr === null ? 0 : strlen((string) $requestBodyStr),
        'response_size' => $responseBodyStr === null ? 0 : strlen((string) $responseBodyStr),
        'truncated' => !empty($input['truncated']) ? 1 : 0,
        'duration_ms' => $input['durationMs'] ?? $input['duration_ms'] ?? null,
        'hit_count' => 1,
        'first_seen' => date('Y-m-d H:i:s'),
        'last_seen' => date('Y-m-d H:i:s'),
        'initiator' => $input['initiator'] ?? null,
      'request_body' => $requestBodyStr,
        'query_params' => $queryParams,
        'response_body' => $responseBodyStr,
        'status_code' => $input['status'] ?? null,
        'http_method' => $input['method'] ?? 'GET',
        'referer' => $referer,
        'response_header' => $responseHeadersStr,
        'payload_hash' => $payloadHash,
        'screenshot' => $screenshotUrl
    ];

    $payloadModel->createPayload($payloadData);

    echo json_encode([
        'status' => 'success',
        'message' => 'Berhasil memetakan endpoint dan menyimpan payload.',
        'data' => [
            'endpoint_id' => $endpointId,
            'path' => $path
        ]
    ]);
    exit;
  }

  public function captureBulk() {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }

    // 1. Validasi Token Auth
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
    }
    
    if (!$token) {
        echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan.']);
        exit;
    }

    $tokenModel = $this->m('ExtensionTokenModel');
    $tokenData = $tokenModel->getByToken($token);
    
    if (!$tokenData) {
        echo json_encode(['status' => 'error', 'message' => 'Token tidak valid.']);
        exit;
    }

    $accountId = $tokenData['account_id'];

    // 2. Dapatkan Semua Proyek untuk Akun Ini
    $projectModel = $this->m('ProjectModel');
    $projects = $projectModel->getAllByAccountId($accountId);
    
    if (empty($projects)) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada proyek pada akun ini.']);
        exit;
    }

    // 3. Parsing Input (Array of Payloads)
    $input = $this->readJsonInput();
    if (!$input || empty($input['payloads']) || !is_array($input['payloads'])) {
        echo json_encode(['status' => 'error', 'message' => 'Data payload kosong atau format salah.']);
        exit;
    }

    $payloads = $input['payloads'];
    
    // Preflight every payload independently before any write. This prevents a mixed
    // batch from being assigned to the first payload's project.
    $payloadProjects = [];
    $validPayloads = [];
    $rejectedResults = [];
    foreach ($payloads as $idx => $candidate) {
      $refererHost = $this->normalizeProjectHost($candidate['pageUrl'] ?? '');
      $requestHost = $this->normalizeProjectHost($candidate['url'] ?? '');
      $matchedProject = null; $matchedScore = -1; $tie = false;
      foreach ($projects as $p) {
        $projectHost = $this->normalizeProjectHost($p['domain'] ?? '');
        $score = max($this->projectHostScore($refererHost, $projectHost) >= 0 ? 1000000 + $this->projectHostScore($refererHost, $projectHost) : -1, $this->projectHostScore($requestHost, $projectHost));
        if ($score > $matchedScore) { $matchedScore = $score; $matchedProject = $p; $tie = false; }
        elseif ($score >= 0 && $score === $matchedScore) { $tie = true; }
      }
      if (!$matchedProject || $matchedScore < 0 || $tie) {
        $rejectedResults[] = [
          'event_id' => $candidate['eventId'] ?? $candidate['event_id'] ?? null,
          'status' => 'rejected',
          'reason' => 'domain tidak cocok atau ambigu'
        ];
        continue;
      }
      $payloadProjects[$idx] = $matchedProject;
      $validPayloads[$idx] = $candidate;
    }
    if (count($payloads) > 100) {
      http_response_code(413);
      echo json_encode(['status' => 'error', 'message' => 'Maksimal 100 payload per batch.']);
      exit;
    }

    $items = [];
    foreach ($validPayloads as $idx => $pData) {
        $projectId = (int) $payloadProjects[$idx]['id'];
        $requestUrl = (string) ($pData['url'] ?? '');
        $pageUrl = (string) ($pData['pageUrl'] ?? '');
        $parsed = parse_url($requestUrl);
        if (!$parsed || empty($parsed['path'])) {
          $parsed = parse_url($pageUrl . $requestUrl);
        }
        $path = $parsed['path'] ?? '/';
        if ($path === '') $path = '/';
        $requestHeaders = is_array($pData['requestHeaders'] ?? null) ? $pData['requestHeaders'] : [];
        $responseHeaders = is_array($pData['responseHeaders'] ?? null) ? $pData['responseHeaders'] : [];

        $allowMethodsStr = '';
        $contentType = '';
        foreach ($responseHeaders as $key => $val) {
            $lowerKey = strtolower((string) $key);
            if ($lowerKey === 'access-control-allow-methods') $allowMethodsStr = (string) $val;
            if ($lowerKey === 'content-type') $contentType = (string) $val;
        }

        $methods = ['get'=>0, 'post'=>0, 'put'=>0, 'patch'=>0, 'delete'=>0, 'head'=>0, 'option'=>0];
        if ($allowMethodsStr !== '') {
            foreach (array_map('trim', explode(',', strtolower($allowMethodsStr))) as $method) {
                if ($method === 'options') $method = 'option';
                if (isset($methods[$method])) $methods[$method] = 1;
            }
        } else {
            $method = strtolower((string) ($pData['method'] ?? 'get'));
            if ($method === 'options') $method = 'option';
            if (isset($methods[$method])) $methods[$method] = 1;
        }

        $requestBody = $pData['requestBody'] ?? null;
        $responseBody = $pData['responseBody'] ?? null;
        $this->enforceBodyLimit($requestBody);
        $this->enforceBodyLimit($responseBody);
        $requestBodyString = is_array($requestBody) ? json_encode($requestBody, JSON_UNESCAPED_SLASHES) : $requestBody;
        $responseBodyString = is_array($responseBody) ? json_encode($responseBody, JSON_UNESCAPED_SLASHES) : $responseBody;
        $host = $this->normalizeProjectHost($requestUrl !== '' ? $requestUrl : $pageUrl);
        $eventId = trim((string) ($pData['eventId'] ?? $pData['event_id'] ?? ''));
        if ($eventId === '') $eventId = bin2hex(random_bytes(16));
        $payloadHash = trim((string) ($pData['payloadHash'] ?? ''));

        $items[] = [
          'project_id' => $projectId,
          'path' => $path,
          'methods' => $methods,
          'request_headers' => $requestHeaders,
          'event_id' => $eventId,
          'session_id' => $pData['sessionId'] ?? $pData['session_id'] ?? null,
          'session_title' => $pData['sessionTitle'] ?? $pData['session_title'] ?? null,
          'session_started_at' => $pData['sessionStartedAt'] ?? $pData['session_started_at'] ?? null,
          'sequence_no' => $pData['sequenceNo'] ?? $pData['sequence_no'] ?? null,
          'tab_id' => $pData['tabId'] ?? $pData['tab_id'] ?? null,
          'frame_id' => $pData['frameId'] ?? $pData['frame_id'] ?? null,
          'navigation_id' => $pData['navigationId'] ?? $pData['navigation_id'] ?? null,
          'source_type' => $pData['sourceType'] ?? $pData['source_type'] ?? null,
          'request_url' => $requestUrl,
          'host' => $host,
          'content_type' => $contentType,
          'request_size' => $requestBodyString === null ? 0 : strlen((string) $requestBodyString),
          'response_size' => $responseBodyString === null ? 0 : strlen((string) $responseBodyString),
          'truncated' => !empty($pData['truncated']),
          'duration_ms' => $pData['durationMs'] ?? $pData['duration_ms'] ?? null,
          'request_body' => $requestBodyString,
          'query_params' => $pData['queryParams'] ?? '',
          'response_body' => $responseBodyString,
          'status_code' => $pData['status'] ?? null,
          'http_method' => $pData['method'] ?? 'GET',
          'referer' => $pageUrl,
          'response_headers' => $responseHeaders,
          'payload_hash' => $payloadHash,
          'screenshot' => null,
          'initiator' => $pData['initiator'] ?? null
        ];
    }

    $captureModel = $this->m('CaptureModel');
    try {
      $results = $captureModel->saveBatch($accountId, $items);
    } catch (Throwable $exception) {
      error_log('Capture batch failed: ' . $exception->getMessage());
      http_response_code(503);
      echo json_encode(['status' => 'error', 'message' => 'Batch capture gagal disimpan; silakan retry.']);
      exit;
    }
    $results = array_merge($rejectedResults, $results);
    $processedCount = count($results);
    $storedCount = count(array_filter($results, static fn($result) => ($result['status'] ?? '') === 'stored'));
    $duplicateCount = count(array_filter($results, static fn($result) => ($result['status'] ?? '') === 'duplicate'));

    echo json_encode([
        'status' => 'success',
        'message' => "Berhasil memproses $processedCount payload(s) dari antrean.",
        'data' => [
          'processed' => $processedCount,
          'stored' => $storedCount,
          'duplicates' => $duplicateCount,
          'results' => $results
        ]
    ]);
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
