<?php

require_once __DIR__ . '/ProcAuth.php';

class ProcProjects extends Controller {
  public function __construct() {
    if (!ProcAuth::hasAuthenticatedSession()) {
      header('Location: ' . burl . '/auth');
      exit;
    }
  }

  public function index() {
    header('Location: ' . burl . '/panel');
    exit;
  }

  public function create() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('Location: ' . burl . '/panel');
      exit;
    }

    $title = trim($_POST['title'] ?? '');
    $domain = trim($_POST['domain'] ?? '');

    if ($title === '' || $domain === '') {
      Flasher::setFlash('Gagal', 'Judul proyek dan target domain tidak boleh kosong.', 'error');
      header('Location: ' . burl . '/panel');
      exit;
    }

    $projectModel = $this->m('ProjectModel');
    $accountId = $_SESSION['account']['id'];
    
    $existingProjects = $projectModel->getAllByAccountId($accountId);
    
    $logoUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain) . "&sz=128";

    $projectId = $projectModel->createProject([
      'account_id' => $accountId,
      'title' => $title,
      'domain' => $domain,
      'logo' => $logoUrl
    ]);

    if (empty($existingProjects) && $projectId) {
      $projectModel->setActiveProject($projectId, $accountId);
    }

    Flasher::setFlash('Berhasil', 'Proyek baru berhasil dibuat.', 'success');
    header('Location: ' . burl . '/panel');
    exit;
  }

  public function delete($id = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
      header('Location: ' . burl . '/panel');
      exit;
    }

    $projectModel = $this->m('ProjectModel');
    $accountId = $_SESSION['account']['id'];

    if ($projectModel->deleteProject($id, $accountId)) {
      Flasher::setFlash('Berhasil', 'Proyek berhasil dihapus.', 'success');
    } else {
      Flasher::setFlash('Gagal', 'Proyek gagal dihapus atau tidak ditemukan.', 'error');
    }

    header('Location: ' . burl . '/panel');
    exit;
  }

  public function clear($id = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
      header('Location: ' . burl . '/panel');
      exit;
    }

    $projectModel = $this->m('ProjectModel');
    $accountId = $_SESSION['account']['id'];
    
    $project = $projectModel->getByIdAndAccountId($id, $accountId);
    if (!$project) {
        Flasher::setFlash('Gagal', 'Proyek tidak ditemukan atau akses ditolak.', 'error');
        header('Location: ' . burl . '/panel');
        exit;
    }

    $endpointModel = $this->m('EndpointModel');
    if ($endpointModel->clearByProjectId($id)) {
      // Hapus folder screenshot khusus project ini
      $dir = __DIR__ . '/../../../public/assets/images/screenshots/' . $id;
      if (is_dir($dir)) {
          $files = array_diff(scandir($dir), ['.', '..']);
          foreach ($files as $file) {
              @unlink("$dir/$file");
          }
          @rmdir($dir);
      }
      
      Flasher::setFlash('Berhasil', 'Seluruh data endpoint dan payload pada proyek ini berhasil dibersihkan.', 'success');
    } else {
      Flasher::setFlash('Gagal', 'Gagal membersihkan data proyek.', 'error');
    }

    header('Location: ' . burl . '/panel/endpoints');
    exit;
  }

  public function check_exists() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      exit;
    }
    
    $title = trim($_POST['title'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $accountId = $_SESSION['account']['id'];
    
    $projectModel = $this->m('ProjectModel');
    
    $response = [
      'title_exists' => false,
      'domain_exists' => false
    ];
    
    if ($title !== '') {
      $response['title_exists'] = $projectModel->checkTitleExists($title, $accountId);
    }
    if ($domain !== '') {
      $response['domain_exists'] = $projectModel->checkDomainExists($domain, $accountId);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
  }

  public function set_active($id = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
      http_response_code(405);
      exit;
    }
    
    $accountId = $_SESSION['account']['id'];
    $projectModel = $this->m('ProjectModel');
    
    if ($projectModel->setActiveProject($id, $accountId)) {
      echo json_encode(['success' => true]);
    } else {
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Gagal mengaktifkan proyek.']);
    }
    exit;
  }
}
