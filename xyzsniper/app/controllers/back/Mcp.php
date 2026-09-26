<?php

class Mcp extends Controller {
  public function __construct() {
    header('Content-Type: application/json');
    // Cors untuk development
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'null'));
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
  }

  public function index() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Metode yang diizinkan hanya POST']);
      exit;
    }

    // Validasi Token
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = preg_match('/^Bearer\s+(\S+)$/i', trim($authHeader), $m) ? $m[1] : null;
    if (!$token) {
      http_response_code(401);
      echo json_encode(['error' => 'Akses ditolak: Authorization Bearer wajib disertakan']);
      exit;
    }

    $tokenModel = $this->m('ExtensionTokenModel');
    $tokenData = $tokenModel->getByToken($token);
    if (!$tokenData) {
      http_response_code(403);
      echo json_encode(['error' => 'Akses ditolak: Token tidak valid']);
      exit;
    }

    $input = file_get_contents('php://input');
    $request = json_decode($input, true);

    if (!$request || !isset($request['method'])) {
      $this->sendError(-32700, "Parse error atau Request tidak valid");
    }

    $id = isset($request['id']) ? $request['id'] : null;
    $method = $request['method'];
    $params = isset($request['params']) ? $request['params'] : [];

    switch ($method) {
      case 'initialize':
        $this->sendResult($id, [
          'protocolVersion' => '2024-11-05',
          'serverInfo' => [
            'name' => 'XYZ Sniper MCP Server',
            'version' => '1.0.0'
          ],
          'capabilities' => [
            'tools' => []
          ]
        ]);
        break;

      case 'tools/list':
        $this->sendResult($id, [
          'tools' => [
            [
              'name' => 'get_projects',
              'description' => 'Mengambil semua daftar proyek (target API) yang dimonitor.',
              'inputSchema' => [
                'type' => 'object',
                'properties' => (object)[],
              ]
            ],
            [
              'name' => 'get_endpoints',
              'description' => 'Mengambil daftar endpoint dari sebuah proyek.',
              'inputSchema' => [
                'type' => 'object',
                'properties' => [
                  'project_id' => [
                    'type' => 'integer',
                    'description' => 'ID dari proyek.'
                  ]
                ],
                'required' => ['project_id']
              ]
            ],
            [
              'name' => 'get_payloads',
              'description' => 'Mengambil history payload/request dari sebuah endpoint.',
              'inputSchema' => [
                'type' => 'object',
                'properties' => [
                  'endpoint_id' => [
                    'type' => 'integer',
                    'description' => 'ID dari endpoint.'
                  ]
                ],
                'required' => ['endpoint_id']
              ]
            ],
            [
              'name' => 'search_traffic',
              'description' => 'Mencari keyword tertentu (token, password, celah) pada seluruh tangkapan payload.',
              'inputSchema' => [
                'type' => 'object',
                'properties' => [
                  'keyword' => [
                    'type' => 'string',
                    'description' => 'Kata kunci untuk dicari.'
                  ]
                ],
                'required' => ['keyword']
              ]
            ]
          ]
        ]);
        break;

      case 'tools/call':
        $toolName = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];
        $this->handleToolCall($id, $toolName, $args);
        break;

      default:
        $this->sendError(-32601, "Method tidak ditemukan", $id);
        break;
    }
  }

  private function handleToolCall($id, $toolName, $args) {
    // Inisialisasi Database
    $db = new Database();

    try {
      switch ($toolName) {
        case 'get_projects':
          $db->query('SELECT id, title, domain, is_active, created_at FROM projects WHERE account_id = :account_id ORDER BY created_at DESC');
          $db->bind('account_id', $tokenData['account_id']);
          $projects = $db->getAll();
          
          $this->sendResult($id, [
            'content' => [
              [
                'type' => 'text',
                'text' => json_encode($projects, JSON_PRETTY_PRINT)
              ]
            ]
          ]);
          break;

        case 'get_endpoints':
          $projectId = $args['project_id'] ?? null;
          if (!$projectId) $this->sendError(-32602, "Parameter project_id dibutuhkan", $id);

          $db->query('
            SELECT e.id, e.path, e.get, e.post, e.put, e.patch, e.delete, e.option, e.label, e.created_at,
                   (SELECT COUNT(p.id) FROM payloads p WHERE p.endpoint_id = e.id) as payload_count
            FROM endpoints e 
            JOIN projects pr ON pr.id=e.project_id
            WHERE e.project_id = :project_id AND pr.account_id = :account_id
          ');
          $db->bind('project_id', $projectId);
          $db->bind('account_id', $tokenData['account_id']);
          $endpoints = $db->getAll();

          $this->sendResult($id, [
            'content' => [
              [
                'type' => 'text',
                'text' => json_encode($endpoints, JSON_PRETTY_PRINT)
              ]
            ]
          ]);
          break;

        case 'get_payloads':
          $endpointId = $args['endpoint_id'] ?? null;
          if (!$endpointId) $this->sendError(-32602, "Parameter endpoint_id dibutuhkan", $id);

          $db->query('SELECT p.id, p.http_method, p.query_params, p.response_header, p.response_body, p.request_body, p.status_code, p.referer, p.screenshot, p.created_at FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id WHERE p.endpoint_id = :endpoint_id AND pr.account_id = :account_id ORDER BY p.created_at DESC');
          $db->bind('endpoint_id', $endpointId);
          $db->bind('account_id', $tokenData['account_id']);
          $payloads = $db->getAll();

          // Decode JSON fields untuk kejelasan
          foreach ($payloads as &$p) {
            $p['response_header'] = json_decode($p['response_header'], true) ?: $p['response_header'];
            
            // Periksa jika body berupa JSON
            $reqDecoded = @json_decode($p['request_body'], true);
            if ($reqDecoded) $p['request_body'] = $reqDecoded;

            $resDecoded = @json_decode($p['response_body'], true);
            if ($resDecoded) $p['response_body'] = $resDecoded;
          }

          $this->sendResult($id, [
            'content' => [
              [
                'type' => 'text',
                'text' => json_encode($payloads, JSON_PRETTY_PRINT)
              ]
            ]
          ]);
          break;

        case 'search_traffic':
          $keyword = $args['keyword'] ?? null;
          if (!$keyword) $this->sendError(-32602, "Parameter keyword dibutuhkan", $id);

          $likeKeyword = "%{$keyword}%";
          $db->query('
            SELECT p.id, e.path, p.http_method, p.request_body, p.response_body, p.created_at 
            FROM payloads p
            JOIN endpoints e ON p.endpoint_id = e.id
            JOIN projects pr ON pr.id=e.project_id
            WHERE pr.account_id = :account_id AND (p.request_body LIKE :kw
               OR p.response_body LIKE :kw
               OR e.request_header LIKE :kw
               OR e.path LIKE :kw)
            LIMIT 50
          ');
          $db->bind('kw', $likeKeyword);
          $db->bind('account_id', $tokenData['account_id']);
          $results = $db->getAll();

          $this->sendResult($id, [
            'content' => [
              [
                'type' => 'text',
                'text' => json_encode($results, JSON_PRETTY_PRINT)
              ]
            ]
          ]);
          break;

        default:
          $this->sendError(-32601, "Tool tidak ditemukan", $id);
          break;
      }
    } catch (Exception $e) {
      $this->sendError(-32000, $e->getMessage(), $id);
    }
  }

  private function sendResult($id, $result) {
    echo json_encode([
      'jsonrpc' => '2.0',
      'id' => $id,
      'result' => $result
    ]);
    exit;
  }

  private function sendError($code, $message, $id = null) {
    echo json_encode([
      'jsonrpc' => '2.0',
      'id' => $id,
      'error' => [
        'code' => $code,
        'message' => $message
      ]
    ]);
    exit;
  }
}
