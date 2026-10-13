<?php
// API: retorna detalhes completos de um pedido (admin)
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false]);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { echo json_encode(['sucesso' => false]); exit; }

$pdo = getPDO();

$pedido = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
$pedido->execute([$id]);
$pedido = $pedido->fetch();

if (!$pedido) { echo json_encode(['sucesso' => false]); exit; }

$itens = $pdo->prepare('
    SELECT pi.quantidade, pi.preco_unitario, pi.subtotal, p.nome
    FROM pedido_itens pi
    JOIN produtos p ON p.id = pi.produto_id
    WHERE pi.pedido_id = ?
');
$itens->execute([$id]);

echo json_encode([
    'sucesso' => true,
    'pedido'  => $pedido,
    'itens'   => $itens->fetchAll(),
]);
