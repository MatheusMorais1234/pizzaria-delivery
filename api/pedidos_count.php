<?php
// Retorna contagem de pedidos pendentes para polling do admin
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['pendentes' => 0]);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$pendentes = getPDO()
    ->query("SELECT COUNT(*) FROM pedidos WHERE status = 'pendente'")
    ->fetchColumn();

echo json_encode(['pendentes' => (int) $pendentes]);
