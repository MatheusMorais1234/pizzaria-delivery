<?php
require_once __DIR__ . '/base.php';
// Verificação de sessão admin — incluir no topo de cada página protegida
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE . '/admin/login.php');
    exit;
}

// Todo POST do painel precisa do token CSRF da sessao
require_once __DIR__ . '/csrf.php';
csrf_exigir();
