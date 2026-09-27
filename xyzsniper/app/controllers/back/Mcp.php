<?php

class Mcp extends Controller {
  private const SUPPORTED_PROTOCOLS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];
  private const DEFAULT_LIMIT = 50;
  private const MAX_LIMIT = 200;

  public function __construct() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, MCP-Protocol-Version, Mcp-Session-Id, Last-Event-ID');
    header('Access-Control-Expose-Headers: MCP-Protocol-Version, Mcp-Session-Id');

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = array_values(array_unique(array_filter([
      rtrim((string) (defined('burl') ? burl : ''), '/'),
      rtrim((string) (defined('APP_CANONICAL_URL') ? APP_CANONICAL_URL : ''), '/')
    ])));
    if ($origin !== '') {
      if (!in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Origin MCP tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
        exit;
      }
      header('Access-Control-Allow-Origin: ' . $origin);
      header('Vary: Origin');
    }
  }

  public function index() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(204);
      exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Allow: POST, OPTIONS');
      http_response_code(405);
      echo json_encode(['error' => 'MCP endpoint menerima POST JSON-RPC.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $token = $this->bearerToken();
    if ($token === null) {
      http_response_code(401);
      header('WWW-Authenticate: Bearer');
      echo json_encode(['error' => 'Authorization Bearer wajib disertakan.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $tokenData = $this->m('ExtensionTokenModel')->getByToken($token);
    if (!$tokenData) {
      http_response_code(403);
      echo json_encode(['error' => 'Token MCP tidak valid atau sudah kedaluwarsa.'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    $this->m('ExtensionTokenModel')->updateLastUsed($tokenData['id']);

    $request = $this->readRequest();
    if ($request === null || array_is_list($request) || empty($request['method']) || !is_string($request['method'])) {
      $this->sendError(-32600, 'Request JSON-RPC tidak valid.');
    }

    $method = $request['method'];
    $id = array_key_exists('id', $request) ? $request['id'] : null;
    $params = isset($request['params']) && is_array($request['params']) ? $request['params'] : [];
    $protocol = $this->requestProtocol($request, $method);
    if ($protocol !== null) {
      header('MCP-Protocol-Version: ' . $protocol);
    }

    switch ($method) {
      case 'initialize':
        $this->sendResult($id, [
          'protocolVersion' => $protocol ?: self::SUPPORTED_PROTOCOLS[0],
          'serverInfo' => [
            'name' => 'XYZ Sniper MCP Server',
            'version' => '1.1.0'
          ],
          'instructions' => 'Gunakan get_projects lalu filter project/session sebelum membaca endpoint atau payload.',
          'capabilities' => [
            'tools' => ['listChanged' => false]
          ]
        ]);
        break;

      case 'server/discover':
        $this->sendResult($id, [
          'protocolVersions' => self::SUPPORTED_PROTOCOLS,
          'serverInfo' => ['name' => 'XYZ Sniper MCP Server', 'version' => '1.1.0'],
          'capabilities' => ['tools' => ['listChanged' => false]],
          'tools' => $this->toolDefinitions()
        ]);
        break;

      case 'notifications/initialized':
      case 'notifications/cancelled':
      case 'notifications/tools/list_changed':
        $this->sendNotificationAck();
        break;

      case 'ping':
        $this->sendResult($id, []);
        break;

      case 'tools/list':
        $this->sendResult($id, ['tools' => $this->toolDefinitions()]);
        break;

      case 'resources/list':
        $this->sendResult($id, ['resources' => []]);
        break;

      case 'prompts/list':
        $this->sendResult($id, ['prompts' => []]);
        break;

      case 'tools/call':
        $toolName = (string) ($params['name'] ?? '');
        $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
        $this->handleToolCall($id, $toolName, $args, (int) $tokenData['account_id']);
        break;

      default:
        $this->sendError(-32601, 'Method tidak ditemukan.', $id);
    }
  }

  private function bearerToken() {
    $authHeader = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    return preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches) ? $matches[1] : null;
  }

  private function readRequest() {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || strlen($raw) > 1024 * 1024) {
      $this->sendError(-32600, 'Request MCP terlalu besar.');
    }
    $request = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->sendError(-32700, 'JSON MCP tidak valid.');
    }
    return $request;
  }

  private function requestProtocol(array $request, $method) {
    $headerVersion = trim((string) ($_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? ''));
    $bodyVersion = trim((string) (($request['params']['protocolVersion'] ?? '')));
    $requested = $method === 'initialize' ? ($bodyVersion ?: $headerVersion) : ($headerVersion ?: $bodyVersion);
    if ($requested === '') return self::SUPPORTED_PROTOCOLS[0];
    if (!in_array($requested, self::SUPPORTED_PROTOCOLS, true)) {
      $this->sendError(-32602, 'Versi MCP tidak didukung. Gunakan salah satu: ' . implode(', ', self::SUPPORTED_PROTOCOLS));
    }
    return $requested;
  }

  private function toolDefinitions() {
    return [
      [
        'name' => 'get_projects',
        'description' => 'Mengambil daftar project audit pada akun aktif.',
        'inputSchema' => $this->objectSchema([], [])
      ],
      [
        'name' => 'get_endpoints',
        'description' => 'Mengambil endpoint project dengan pagination dan filter path.',
        'inputSchema' => $this->objectSchema([
          'project_id' => ['type' => 'integer', 'minimum' => 1],
          'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
          'offset' => ['type' => 'integer', 'minimum' => 0],
          'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200]
        ], ['project_id'])
      ],
      [
        'name' => 'get_payloads',
        'description' => 'Mengambil payload endpoint dengan metadata session dan pagination.',
        'inputSchema' => $this->objectSchema([
          'endpoint_id' => ['type' => 'integer', 'minimum' => 1],
          'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
          'offset' => ['type' => 'integer', 'minimum' => 0]
        ], ['endpoint_id'])
      ],
      [
        'name' => 'search_traffic',
        'description' => 'Mencari request/response pada project atau session tertentu.',
        'inputSchema' => $this->objectSchema([
          'keyword' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
          'project_id' => ['type' => 'integer', 'minimum' => 1],
          'session_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
          'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
          'offset' => ['type' => 'integer', 'minimum' => 0]
        ], ['keyword'])
      ],
      [
        'name' => 'get_sessions',
        'description' => 'Mengambil sesi audit browser beserta jumlah event.',
        'inputSchema' => $this->objectSchema([
          'project_id' => ['type' => 'integer', 'minimum' => 1],
          'status' => ['type' => 'string', 'enum' => ['active', 'ended']],
          'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
          'offset' => ['type' => 'integer', 'minimum' => 0]
        ])
      ],
      [
        'name' => 'get_session_events',
        'description' => 'Mengambil urutan event sesi untuk mapping workflow browser.',
        'inputSchema' => $this->objectSchema([
          'session_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
          'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
          'offset' => ['type' => 'integer', 'minimum' => 0]
        ], ['session_id'])
      ],
      [
        'name' => 'get_workflow_map',
        'description' => 'Mengambil ringkasan workflow sesi dalam urutan sequence, method, host, path, dan status.',
        'inputSchema' => $this->objectSchema([
          'session_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
          'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
          'offset' => ['type' => 'integer', 'minimum' => 0]
        ], ['session_id'])
      ],
      [
        'name' => 'get_capture_stats',
        'description' => 'Mengambil statistik capture endpoint, payload, hit, dan session.',
        'inputSchema' => $this->objectSchema([
          'project_id' => ['type' => 'integer', 'minimum' => 1]
        ])
      ]
    ];
  }

  private function objectSchema(array $properties, array $required = []) {
    return [
      'type' => 'object',
      'properties' => (object) $properties,
      'required' => $required,
      'additionalProperties' => false
    ];
  }

  private function page(array $args) {
    $limit = isset($args['limit']) ? (int) $args['limit'] : self::DEFAULT_LIMIT;
    $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
    if ($limit < 1 || $limit > self::MAX_LIMIT || $offset < 0) {
      $this->sendError(-32602, 'limit harus 1-' . self::MAX_LIMIT . ' dan offset tidak boleh negatif.');
    }
    return [$limit, $offset];
  }

  private function requireString(array $args, $key, $max = 200) {
    $value = trim((string) ($args[$key] ?? ''));
    if ($value === '' || strlen($value) > $max) {
      $this->sendError(-32602, $key . ' wajib diisi dan maksimal ' . $max . ' karakter.');
    }
    return $value;
  }

  private function handleToolCall($id, $toolName, array $args, $accountId) {
    $db = new Database();
    try {
      switch ($toolName) {
        case 'get_projects':
          $db->query('SELECT id, title, domain, is_active, created_at FROM projects WHERE account_id=:account_id ORDER BY is_active DESC, created_at DESC');
          $db->bind('account_id', $accountId);
          $this->sendToolResult($id, $db->getAll());
          break;

        case 'get_endpoints':
          $projectId = $this->requirePositiveInt($args, 'project_id');
          [$limit, $offset] = $this->page($args);
          $query = trim((string) ($args['query'] ?? ''));
          $sql = 'SELECT e.id,e.project_id,e.path,e.get,e.post,e.put,e.patch,e.delete,e.option,e.label,e.created_at,e.updated_at,
                    (SELECT COUNT(p.id) FROM payloads p WHERE p.endpoint_id=e.id) AS payload_count,
                    (SELECT COALESCE(SUM(p.hit_count),0) FROM payloads p WHERE p.endpoint_id=e.id) AS payload_hits_count
                  FROM endpoints e JOIN projects pr ON pr.id=e.project_id
                  WHERE e.project_id=:project_id AND pr.account_id=:account_id';
          if ($query !== '') $sql .= ' AND e.path LIKE :path_query';
          $sql .= ' ORDER BY e.id ASC LIMIT :limit OFFSET :offset';
          $db->query($sql);
          $db->bind('project_id', $projectId, PDO::PARAM_INT);
          $db->bind('account_id', $accountId, PDO::PARAM_INT);
          if ($query !== '') $db->bind('path_query', '%' . substr($query, 0, 200) . '%');
          $db->bind('limit', $limit, PDO::PARAM_INT);
          $db->bind('offset', $offset, PDO::PARAM_INT);
          $this->sendToolResult($id, $db->getAll(), ['limit' => $limit, 'offset' => $offset]);
          break;

        case 'get_payloads':
          $endpointId = $this->requirePositiveInt($args, 'endpoint_id');
          [$limit, $offset] = $this->page($args);
          $db->query('SELECT p.id,p.event_id,p.session_id,p.sequence_no,p.tab_id,p.frame_id,p.navigation_id,p.source_type,
                    p.host,p.request_url,p.http_method,p.query_params,p.request_body,p.response_body,e.request_header AS request_header,p.response_header,
                    p.status_code,p.content_type,p.request_size,p.response_size,p.truncated,p.duration_ms,p.hit_count,p.first_seen,p.last_seen,
                    p.referer,p.screenshot,p.created_at,e.project_id,e.path
                  FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id
                  WHERE p.endpoint_id=:endpoint_id AND pr.account_id=:account_id
                  ORDER BY COALESCE(p.sequence_no,18446744073709551615) ASC,p.created_at ASC LIMIT :limit OFFSET :offset');
          $db->bind('endpoint_id', $endpointId, PDO::PARAM_INT);
          $db->bind('account_id', $accountId, PDO::PARAM_INT);
          $db->bind('limit', $limit, PDO::PARAM_INT);
          $db->bind('offset', $offset, PDO::PARAM_INT);
          $this->sendToolResult($id, $this->decodePayloadRows($db->getAll()), ['limit' => $limit, 'offset' => $offset]);
          break;

        case 'search_traffic':
          $keyword = $this->requireString($args, 'keyword');
          [$limit, $offset] = $this->page($args);
          $sql = 'SELECT p.id,p.event_id,p.session_id,p.sequence_no,p.host,e.project_id,e.path,p.http_method,p.request_body,p.response_body,p.status_code,p.created_at
                  FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id
                  WHERE pr.account_id=:account_id AND (p.request_body LIKE :kw OR p.response_body LIKE :kw OR e.request_header LIKE :kw OR e.path LIKE :kw)';
          if (!empty($args['project_id'])) $sql .= ' AND e.project_id=:project_id';
          if (!empty($args['session_id'])) $sql .= ' AND p.session_id=:session_id';
          $sql .= ' ORDER BY p.last_seen DESC,p.id DESC LIMIT :limit OFFSET :offset';
          $db->query($sql);
          $db->bind('account_id', $accountId, PDO::PARAM_INT);
          $db->bind('kw', '%' . $keyword . '%');
          if (!empty($args['project_id'])) $db->bind('project_id', (int) $args['project_id'], PDO::PARAM_INT);
          if (!empty($args['session_id'])) $db->bind('session_id', substr(trim((string) $args['session_id']), 0, 64));
          $db->bind('limit', $limit, PDO::PARAM_INT);
          $db->bind('offset', $offset, PDO::PARAM_INT);
          $this->sendToolResult($id, $this->decodePayloadRows($db->getAll()), ['limit' => $limit, 'offset' => $offset]);
          break;

        case 'get_sessions':
          [$limit, $offset] = $this->page($args);
          $sql = 'SELECT s.session_id,s.project_id,s.title,s.status,s.started_at,s.last_seen_at,s.ended_at,p.title AS project_title,p.domain,
                    (SELECT COUNT(*) FROM payloads x JOIN endpoints ex ON ex.id=x.endpoint_id WHERE x.session_id=s.session_id AND ex.project_id=s.project_id) AS event_count,
                    (SELECT COALESCE(SUM(x.hit_count),0) FROM payloads x JOIN endpoints ex ON ex.id=x.endpoint_id WHERE x.session_id=s.session_id AND ex.project_id=s.project_id) AS hit_count
                  FROM capture_sessions s JOIN projects p ON p.id=s.project_id WHERE s.account_id=:account_id';
          if (!empty($args['project_id'])) $sql .= ' AND s.project_id=:project_id';
          if (!empty($args['status']) && in_array($args['status'], ['active', 'ended'], true)) $sql .= ' AND s.status=:status';
          $sql .= ' ORDER BY s.last_seen_at DESC LIMIT :limit OFFSET :offset';
          $db->query($sql);
          $db->bind('account_id', $accountId, PDO::PARAM_INT);
          if (!empty($args['project_id'])) $db->bind('project_id', (int) $args['project_id'], PDO::PARAM_INT);
          if (!empty($args['status']) && in_array($args['status'], ['active', 'ended'], true)) $db->bind('status', $args['status']);
          $db->bind('limit', $limit, PDO::PARAM_INT);
          $db->bind('offset', $offset, PDO::PARAM_INT);
          $this->sendToolResult($id, $db->getAll(), ['limit' => $limit, 'offset' => $offset]);
          break;

        case 'get_session_events':
          $sessionId = $this->requireString($args, 'session_id', 64);
          [$limit, $offset] = $this->page($args);
          $events = $this->sessionEvents($db, $accountId, $sessionId, $limit, $offset);
          $this->sendToolResult($id, $events, ['limit' => $limit, 'offset' => $offset]);
          break;

        case 'get_workflow_map':
          $sessionId = $this->requireString($args, 'session_id', 64);
          [$limit, $offset] = $this->page($args);
          $events = $this->sessionEvents($db, $accountId, $sessionId, $limit, $offset);
          $workflow = [];
          foreach ($events as $event) {
            $workflow[] = [
              'sequence_no' => $event['sequence_no'],
              'event_id' => $event['event_id'],
              'project_id' => $event['project_id'],
              'host' => $event['host'],
              'method' => $event['http_method'],
              'path' => $event['path'],
              'status_code' => $event['status_code'],
              'source_type' => $event['source_type'],
              'duration_ms' => $event['duration_ms'],
              'hit_count' => $event['hit_count'],
              'last_seen' => $event['last_seen'] ?: $event['created_at']
            ];
          }
          $this->sendToolResult($id, ['session_id' => $sessionId, 'events' => $workflow], ['limit' => $limit, 'offset' => $offset]);
          break;

        case 'get_capture_stats':
          $sql = 'SELECT p.id AS project_id,p.title,p.domain,
                    (SELECT COUNT(*) FROM endpoints e WHERE e.project_id=p.id) AS endpoint_count,
                    (SELECT COUNT(*) FROM payloads x JOIN endpoints e2 ON e2.id=x.endpoint_id WHERE e2.project_id=p.id) AS payload_count,
                    (SELECT COALESCE(SUM(x.hit_count),0) FROM payloads x JOIN endpoints e3 ON e3.id=x.endpoint_id WHERE e3.project_id=p.id) AS hit_count,
                    (SELECT COUNT(*) FROM capture_sessions s WHERE s.project_id=p.id AND s.account_id=p.account_id) AS session_count
                  FROM projects p WHERE p.account_id=:account_id';
          if (!empty($args['project_id'])) $sql .= ' AND p.id=:project_id';
          $sql .= ' ORDER BY p.is_active DESC,p.id ASC';
          $db->query($sql);
          $db->bind('account_id', $accountId, PDO::PARAM_INT);
          if (!empty($args['project_id'])) $db->bind('project_id', (int) $args['project_id'], PDO::PARAM_INT);
          $this->sendToolResult($id, $db->getAll());
          break;

        default:
          $this->sendError(-32601, 'Tool tidak ditemukan.', $id);
      }
    } catch (Throwable $exception) {
      error_log('MCP tool failed: ' . $exception->getMessage());
      $this->sendError(-32000, 'Tool gagal dijalankan: ' . $exception->getMessage(), $id);
    }
  }

  private function requirePositiveInt(array $args, $key) {
    $value = filter_var($args[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) $this->sendError(-32602, $key . ' wajib berupa integer positif.');
    return (int) $value;
  }

  private function sessionEvents(Database $db, $accountId, $sessionId, $limit, $offset) {
    $db->query('SELECT p.id,p.event_id,p.session_id,p.sequence_no,p.tab_id,p.frame_id,p.navigation_id,p.source_type,p.host,p.request_url,
                  p.http_method,p.status_code,p.content_type,p.request_size,p.response_size,p.truncated,p.duration_ms,p.hit_count,
                  p.first_seen,p.last_seen,p.created_at,e.project_id,e.path
                FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id
                WHERE p.session_id=:session_id AND pr.account_id=:account_id
                ORDER BY COALESCE(p.sequence_no,18446744073709551615) ASC,p.created_at ASC LIMIT :limit OFFSET :offset');
    $db->bind('session_id', $sessionId);
    $db->bind('account_id', $accountId, PDO::PARAM_INT);
    $db->bind('limit', $limit, PDO::PARAM_INT);
    $db->bind('offset', $offset, PDO::PARAM_INT);
    return $db->getAll();
  }

  private function decodePayloadRows(array $rows) {
    foreach ($rows as &$row) {
      foreach (['request_header', 'response_header', 'request_body', 'response_body'] as $key) {
        if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') continue;
        $decoded = json_decode($row[$key], true);
        if (json_last_error() === JSON_ERROR_NONE) $row[$key] = $decoded;
      }
    }
    return $rows;
  }

  private function sendToolResult($id, $data, array $meta = []) {
    $result = [
      'content' => [[
        'type' => 'text',
        'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
      ]],
      'structuredContent' => $data,
      'isError' => false
    ];
    if ($meta !== []) $result['_meta'] = $meta;
    $this->sendResult($id, $result);
  }

  private function sendResult($id, $result) {
    if ($id === null) $this->sendNotificationAck();
    echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }

  private function sendNotificationAck() {
    http_response_code(202);
    header('Content-Length: 0');
    exit;
  }

  private function sendError($code, $message, $id = null) {
    echo json_encode([
      'jsonrpc' => '2.0',
      'id' => $id,
      'error' => ['code' => $code, 'message' => $message]
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
}
