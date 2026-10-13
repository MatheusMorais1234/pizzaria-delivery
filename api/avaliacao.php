<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$pedidoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
          ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$pedidoId) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT nota, comentario FROM avaliacoes WHERE pedido_id = ?');
    $stmt->execute([$pedidoId]);
    $row = $stmt->fetch();
    echo json_encode($row ?: null);
    exit;
}

// POST — salvar avaliação
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$nota       = (int) ($body['nota']       ?? 0);
$comentario = trim($body['comentario']   ?? '');

if ($nota < 1 || $nota > 5) {
    http_response_code(400);
    echo json_encode(['erro' => 'Nota inválida']);
    exit;
}

$stmt = $pdo->prepare('SELECT status FROM pedidos WHERE id = ?');
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido || $pedido['status'] !== 'entregue') {
    http_response_code(422);
    echo json_encode(['erro' => 'Pedido não encontrado ou não entregue ainda']);
    exit;
}

$pdo->prepare('INSERT INTO avaliacoes (pedido_id, nota, comentario) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE nota = VALUES(nota), comentario = VALUES(comentario)')
    ->execute([$pedidoId, $nota, $comentario]);

echo json_encode(['ok' => true]);
