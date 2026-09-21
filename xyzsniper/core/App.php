<?php

class App {
  // Default Routes
  protected $controller = DEFAULT_CONTROLLER;
  protected $method = 'index';
  protected $params = [];
  protected $path = 'front/';

  public function __construct() {
    $url = $this->parseURL();

    // Controller Setup
    $controllerName = isset($url[0]) ? $this->findController($url[0]) : null;

    if ($controllerName !== null) {
      $this->controller = $controllerName['name'];
      $this->path = $controllerName['path'];
      unset($url[0]);
    } elseif (isset($url[0])) {
      http_response_code(404);
      exit('Halaman tidak ditemukan.');
    }

    // Controller Instantiation
    require_once __DIR__ . '/../app/controllers/' . $this->path . $this->controller . '.php';
    $this->controller = new $this->controller;

    // Method Setup
    if (isset($url[1])) {
      if (method_exists($this->controller, $url[1])) {
        $this->method = $url[1];
        unset($url[1]);
      }
    }

    // Parameters Setup
    if (!empty($url)) {
      $this->params = array_values($url);
    }

    // Execution
    call_user_func_array([$this->controller, $this->method], $this->params);
  }

  private function findController($requestedName) {
    $requestedName = strtolower($requestedName);

    foreach (['front', 'back'] as $directory) {
      $controllerDirectory = __DIR__ . '/../app/controllers/' . $directory;
      foreach (scandir($controllerDirectory) as $filename) {
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'php') {
          continue;
        }

        $controllerName = pathinfo($filename, PATHINFO_FILENAME);
        if (strtolower($controllerName) === $requestedName) {
          return ['name' => $controllerName, 'path' => $directory . '/'];
        }
      }
    }

    return null;
  }

  // URL Parser
  public function parseURL() {
    if (isset($_GET['url'])) {
      $url = rtrim($_GET['url'], '/');
      $url = filter_var($url, FILTER_SANITIZE_URL);
      $url = explode('/', $url);
      return $url;
    }
    return [];
  }
}
