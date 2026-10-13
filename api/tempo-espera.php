<?php
// API: leitura pública (GET) e escrita admin (POST) do tempo de espera atual
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Apenas admin autenticado pode alterar
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Acesso negado']);
        exit;
    }
    $minutos = max(0, (int)($_POST['minutos'] ?? 0));
    setConfig('tempo_espera_atual', (string)$minutos);
    echo json_encode(['ok' => true, 'minutos' => $minutos]);
} else {
    $minutos = (int)getConfig('tempo_espera_atual', '0');
    echo json_encode(['minutos' => $minutos]);
}
