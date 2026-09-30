<?php

require_once __DIR__ . '/../back/ProcAuth.php';

class Panel extends Controller {
  public function __construct() {
    if (!ProcAuth::hasAuthenticatedSession()) {
      header('Location: ' . burl . '/auth');
      exit;
    }
  }

  public function index() {
    $data['judul'] = 'Dashboard - ' . app_name;
    
    $projectModel = $this->m('ProjectModel');
    $data['projects'] = $projectModel->getAllByAccountId($_SESSION['account']['id']);
    $data['active_project'] = $projectModel->getActiveProjectStats($_SESSION['account']['id']);
    if (!empty($data['active_project'])) {
      $data['chart_data'] = $projectModel->getProjectChartData($data['active_project']['id']);
    }
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/index', $data);
    $this->v('panel/templates/footer');
  }

  public function endpoints() {
    $data['judul'] = 'Endpoint - ' . app_name;
    
    $projectModel = $this->m('ProjectModel');
    $data['projects'] = $projectModel->getAllByAccountId($_SESSION['account']['id']);
    $data['active_project'] = $projectModel->getActiveProjectStats($_SESSION['account']['id']);
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;
    $offset = ($page - 1) * $limit;
    
    $keyword = $_GET['keyword'] ?? null;
    $referer = $_GET['referer'] ?? null;
    $data['keyword'] = $keyword;
    $data['referer'] = $referer;
    
    $data['endpoints'] = [];
    $data['total'] = 0;
    $data['referers'] = [];
    
    if (!empty($data['active_project'])) {
        $endpointModel = $this->m('EndpointModel');
        $payloadModel = $this->m('PayloadModel');
        
        $data['referers'] = $payloadModel->getDistinctBaseReferers($data['active_project']['id']);
        
        if ($keyword) {
            $data['endpoints'] = $endpointModel->searchEndpointsByKeyword($data['active_project']['id'], $keyword, $referer);
            $data['total'] = count($data['endpoints']);
        } else {
            $data['endpoints'] = $endpointModel->getPaginatedByProjectId($data['active_project']['id'], $limit, $offset, $referer);
            $data['total'] = $endpointModel->getCountByProjectId($data['active_project']['id'], $referer);
        }
    }
    
    $data['page'] = $page;
    $data['limit'] = $limit;
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/endpoints', $data);
    $this->v('panel/templates/footer');
  }

  public function extension() {
    $data['judul'] = 'Ekstensi Chrome - ' . app_name;

    $releaseIndexPath = __DIR__ . '/../../../public/downloads/extensions/releases.json';
    $releaseIndex = is_file($releaseIndexPath)
      ? json_decode((string) file_get_contents($releaseIndexPath), true)
      : ['releases' => []];
    $data['extension_releases'] = is_array($releaseIndex['releases'] ?? null) ? $releaseIndex['releases'] : [];
    $data['extension_release'] = $data['extension_releases'][0] ?? null;

    $this->v('panel/templates/header', $data);
    $this->v('panel/extension', $data);
    $this->v('panel/templates/footer');
  }

  public function sessions() {
    $data['judul'] = 'Sesi Audit - ' . app_name;
    $data['page'] = max(1, (int) ($_GET['page'] ?? 1));
    $db = new Database();
    $db->query('SELECT s.*, p.title AS project_title, p.domain,
      (SELECT COUNT(*) FROM payloads x JOIN endpoints ex ON ex.id=x.endpoint_id WHERE x.session_id=s.session_id AND ex.project_id=s.project_id) AS event_count
      FROM capture_sessions s JOIN projects p ON p.id=s.project_id
      WHERE s.account_id=:account_id ORDER BY s.last_seen_at DESC,s.id DESC LIMIT 51 OFFSET :offset');
    $db->bind('account_id', (int) $_SESSION['account']['id']);
    $db->bind('offset', ($data['page'] - 1) * 50, PDO::PARAM_INT);
    $data['sessions'] = $db->getAll();
    $data['has_more'] = count($data['sessions']) > 50;
    $data['sessions'] = array_slice($data['sessions'], 0, 50);
    $this->v('panel/templates/header', $data);
    $this->v('panel/sessions', $data);
    $this->v('panel/templates/footer');
  }

  public function session($sessionId = null) {
    if (!$sessionId) { http_response_code(404); exit('Sesi audit tidak ditemukan.'); }
    $db = new Database();
    $db->query('SELECT s.*, p.title AS project_title, p.domain FROM capture_sessions s JOIN projects p ON p.id=s.project_id
      WHERE s.account_id=:account_id AND s.session_id=:session_id LIMIT 1');
    $db->bind('account_id', (int) $_SESSION['account']['id']);
    $db->bind('session_id', substr((string) $sessionId, 0, 64));
    $session = $db->single();
    if (!$session) { http_response_code(404); exit('Sesi audit tidak ditemukan.'); }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $db->query('SELECT p.id,p.event_id,p.source_type,p.http_method,p.status_code,p.sequence_no,p.request_url,p.occurred_at,p.created_at,p.last_seen,p.hit_count,p.capture_version,p.truncated,e.path FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id
      WHERE e.project_id=:project_id AND p.session_id=:session_id ORDER BY COALESCE(p.occurred_at,p.created_at) ASC,p.id ASC LIMIT 101 OFFSET :offset');
    $db->bind('project_id', (int) $session['project_id']);
    $db->bind('session_id', $session['session_id']);
    $db->bind('offset', ($page - 1) * 100, PDO::PARAM_INT);
    $events = $db->getAll();
    $data = ['judul' => 'Audit ' . $session['session_id'], 'session' => $session, 'events' => array_slice($events,0,100), 'page'=>$page,'has_more'=>count($events)>100];
    $this->v('panel/templates/header', $data);
    $this->v('panel/session', $data);
    $this->v('panel/templates/footer');
  }

  public function export_session($sessionId = null) {
    if (!$sessionId) { http_response_code(404); exit; }
    $db = new Database();
    $db->query('SELECT s.*, p.title AS project_title, p.domain FROM capture_sessions s JOIN projects p ON p.id=s.project_id
      WHERE s.account_id=:account_id AND s.session_id=:session_id LIMIT 1');
    $db->bind('account_id', (int) $_SESSION['account']['id']);
    $db->bind('session_id', substr((string) $sessionId, 0, 64));
    $session = $db->single();
    if (!$session) { http_response_code(404); exit; }
    $db->query('SELECT COALESCE(MAX(p.id),0) AS max_id FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id WHERE e.project_id=:project_id AND p.session_id=:session_id');
    $db->bind('project_id', (int) $session['project_id']);
    $db->bind('session_id', $session['session_id']);
    $maxId = (int) $db->single()['max_id'];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="sniper-session-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $session['session_id']) . '.json"');
    echo '{"session":' . json_encode($session, JSON_INVALID_UTF8_SUBSTITUTE) . ',"events":[';
    $cursor = 0; $count = 0;
    do {
      $db->query('SELECT p.*,e.path FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id WHERE e.project_id=:project AND p.session_id=:session AND p.id>:cursor AND p.id<=:max_id ORDER BY p.id LIMIT 100');
      $db->bind('project',(int)$session['project_id']); $db->bind('session',$sessionId);
      $db->bind('cursor',$cursor,PDO::PARAM_INT); $db->bind('max_id',$maxId,PDO::PARAM_INT);
      $events = $db->getAll();
      foreach ($events as $event) {
        echo ($count++ ? ',' : '') . json_encode($event, JSON_INVALID_UTF8_SUBSTITUTE);
        $cursor = (int)$event['id'];
      }
    } while (count($events) === 100);
    echo '],"manifest":' . json_encode(['complete'=>true,'event_count'=>$count,'through_id'=>$maxId,'order'=>'ingestion_id']) . '}';
    exit;
  }

  public function event($eventId = null) {
    $db = new Database();
    $db->query('SELECT p.*,e.path,e.origin FROM payloads p JOIN endpoints e ON e.id=p.endpoint_id JOIN projects pr ON pr.id=e.project_id WHERE p.event_id=:event AND pr.account_id=:account LIMIT 1');
    $db->bind('event',(string)$eventId); $db->bind('account',(int)$_SESSION['account']['id']);
    $event=$db->single();
    if (!$event) { http_response_code(404); exit('Event tidak ditemukan.'); }
    $data=['judul'=>'Detail rekaman','event'=>$event,'snapshot'=>$event['source_type']==='snapshot'?json_decode($event['response_body']??'',true):null];
    $this->v('panel/templates/header',$data); $this->v('panel/event',$data); $this->v('panel/templates/footer');
  }

  public function update_endpoint_label() {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      ProcAuth::requireCsrf();
      $id = $_POST['id'] ?? null;
      $label = $_POST['label'] ?? '';
      if ($id) {
        $db = new Database();
        $db->query('UPDATE endpoints e JOIN projects p ON p.id=e.project_id SET e.label=:label WHERE e.id=:id AND p.account_id=:account_id');
        $db->bind('label', substr($label, 0, 255)); $db->bind('id', (int)$id); $db->bind('account_id', (int)$_SESSION['account']['id']); $db->exe();
        if ($db->row() < 1) { http_response_code(404); echo json_encode(['status'=>'error']); exit; }
        echo json_encode(['status' => 'success']);
        exit;
      }
    }
    echo json_encode(['status' => 'error']);
    exit;
  }

  public function payloads($endpoint_id = null) {
    $data['judul'] = 'Payload - ' . app_name;
    
    $projectModel = $this->m('ProjectModel');
    $data['projects'] = $projectModel->getAllByAccountId($_SESSION['account']['id']);
    $data['active_project'] = $projectModel->getActiveProjectStats($_SESSION['account']['id']);
    $data['endpoint_id'] = $endpoint_id;
    $data['endpoint'] = null;
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;
    $offset = ($page - 1) * $limit;
    
    $data['payloads'] = [];
    $data['total'] = 0;
    if ($endpoint_id) {
        $endpointModel = $this->m('EndpointModel');
        $db = new Database();
        $db->query('SELECT e.* FROM endpoints e JOIN projects p ON p.id=e.project_id WHERE e.id=:id AND p.account_id=:account_id LIMIT 1');
        $db->bind('id', (int)$endpoint_id); $db->bind('account_id', (int)$_SESSION['account']['id']);
        $data['endpoint'] = $db->single();
        if (!$data['endpoint']) { http_response_code(404); exit('Endpoint tidak ditemukan.'); }
        
        $payloadModel = $this->m('PayloadModel');
        $data['payloads'] = $payloadModel->getPaginatedByEndpointId($endpoint_id, $limit, $offset);
        $data['total'] = $payloadModel->getCountByEndpointId($endpoint_id);
    }
    
    $data['page'] = $page;
    $data['limit'] = $limit;
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/payloads', $data);
    $this->v('panel/templates/footer');
  }

  public function account() {
    $data['judul'] = 'Pengaturan Akun - ' . app_name;
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/account', $data);
    $this->v('panel/templates/footer');
  }

}
