<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo  = getPDO();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

if ($acao === 'consultar') {
    $tel = preg_replace('/\D/', '', $_GET['telefone'] ?? '');
    if (strlen($tel) < 8) {
        echo json_encode(['erro' => 'Telefone inválido']);
        exit;
    }
    $row = $pdo->prepare("SELECT pontos, nome FROM fidelidade WHERE REGEXP_REPLACE(telefone,'[^0-9]','') LIKE ?");
    $row->execute(['%' . $tel . '%']);
    $dados = $row->fetch();
    echo json_encode($dados ?: ['pontos' => 0, 'nome' => null]);
    exit;
}

echo json_encode(['erro' => 'Ação inválida']);
