<?php

class Api extends Controller {
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

    $input = json_decode(file_get_contents('php://input'), true);
    
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
    $input = json_decode(file_get_contents('php://input'), true);
    
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


    // Handle Screenshot (Base64 to File)
    $screenshotUrl = null;
    $base64Image = $input['screenshot'] ?? '';
    
    if (!empty($base64Image) && preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
        $data = substr($base64Image, strpos($base64Image, ',') + 1);
        $data = base64_decode($data);
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
    $requestBodyStr = is_array($reqBodyRaw) ? json_encode($reqBodyRaw) : $reqBodyRaw;
    
    $resBodyRaw = $input['responseBody'] ?? null;
    $responseBodyStr = is_array($resBodyRaw) ? json_encode($resBodyRaw) : $resBodyRaw;
    $responseHeadersStr = is_array($responseHeaders) ? json_encode($responseHeaders) : null;
    $referer = $input['pageUrl'] ?? null;

    $payloadData = [
        'endpoint_id' => $endpointId,
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
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['payloads']) || !is_array($input['payloads'])) {
        echo json_encode(['status' => 'error', 'message' => 'Data payload kosong atau format salah.']);
        exit;
    }

    $payloads = $input['payloads'];
    
    // Preflight every payload independently before any write. This prevents a mixed
    // batch from being assigned to the first payload's project.
    $payloadProjects = [];
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
      if (!$matchedProject || $matchedScore < 0 || $tie) { http_response_code(422); echo json_encode(['status'=>'error','message'=>"Payload $idx domain tidak cocok atau ambigu."]); exit; }
      $payloadProjects[$idx] = $matchedProject;
    }
    $projectId = $payloadProjects[0]['id'];
    $endpointModel = $this->m('EndpointModel');
    $payloadModel = $this->m('PayloadModel');

    // Proses setiap payload
    $processedCount = 0;

    foreach ($payloads as $idx => $pData) {
        $projectId = $payloadProjects[$idx]['id'];
        $path = parse_url($pData['url'], PHP_URL_PATH) ?? '/';
        $requestHeaders = $pData['requestHeaders'] ?? [];
        $responseHeaders = $pData['responseHeaders'] ?? [];

        $endpoint = $endpointModel->getByPathAndProjectId($path, $projectId);

        // Identifikasi Methods yang diizinkan
        $allowMethodsStr = '';
        foreach ($responseHeaders as $key => $val) {
            if (strtolower($key) === 'access-control-allow-methods') {
                $allowMethodsStr = $val;
                break;
            }
        }

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
            $currentMethod = strtolower($pData['method'] ?? '');
            if ($currentMethod === 'options') $currentMethod = 'option';
            if (isset($methods[$currentMethod])) {
                $methods[$currentMethod] = 1;
            }
        }

        $dataToSave = array_merge([
            'project_id' => $projectId,
            'path' => $path,
            'request_header' => json_encode($requestHeaders)
        ], $methods);

        if ($endpoint) {
            $endpointModel->updateEndpoint($endpoint['id'], $dataToSave);
            $endpointId = $endpoint['id'];
        } else {
            $endpointId = $endpointModel->createEndpoint($dataToSave);
        }

        $payloadHash = $pData['payloadHash'] ?? '';
        // Bulk screenshots must be supplied per payload and are intentionally
        // ignored here unless the originating tab metadata is available.
        $screenshotUrl = null;
        
        $queryParams = $pData['queryParams'] ?? '';
        $reqBodyRaw = $pData['requestBody'] ?? null;
        $requestBodyStr = is_array($reqBodyRaw) ? json_encode($reqBodyRaw) : $reqBodyRaw;
        
        $resBodyRaw = $pData['responseBody'] ?? null;
        $responseBodyStr = is_array($resBodyRaw) ? json_encode($resBodyRaw) : $resBodyRaw;
        $responseHeadersStr = is_array($responseHeaders) ? json_encode($responseHeaders) : null;
        $referer = $pData['pageUrl'] ?? null;

        $payloadData = [
            'endpoint_id' => $endpointId,
            'request_body' => $requestBodyStr,
            'query_params' => $queryParams,
            'response_body' => $responseBodyStr,
            'status_code' => $pData['status'] ?? null,
            'http_method' => $pData['method'] ?? 'GET',
            'referer' => $referer,
            'response_header' => $responseHeadersStr,
            'payload_hash' => $payloadHash,
            'screenshot' => $screenshotUrl
        ];

        $payloadModel->createPayload($payloadData);
        $processedCount++;
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Berhasil memproses $processedCount payload(s) dari antrean.",
    ]);
    exit;
  }
}
