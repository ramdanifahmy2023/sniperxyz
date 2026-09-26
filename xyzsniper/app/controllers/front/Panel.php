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
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/extension', $data);
    $this->v('panel/templates/footer');
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
