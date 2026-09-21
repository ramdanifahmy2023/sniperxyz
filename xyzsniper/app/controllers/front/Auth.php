<?php

class Auth extends Controller {
  public function index() {
    $data['judul'] = 'Login - ' . app_name;
    
    $this->v('auth/templates/header', $data);
    $this->v('auth/login', $data);
    $this->v('auth/templates/footer');
  }
}
