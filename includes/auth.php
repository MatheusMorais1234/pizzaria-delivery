<?php
// Verificação de sessão admin — incluir no topo de cada página protegida
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: /pizzaria/admin/login.php');
    exit;
}
