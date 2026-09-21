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
      $id = $_POST['id'] ?? null;
      $label = $_POST['label'] ?? '';
      if ($id) {
        $endpointModel = $this->m('EndpointModel');
        $endpointModel->updateEndpoint($id, ['label' => $label]);
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
        $data['endpoint'] = $endpointModel->getBy('id', $endpoint_id);
        
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
