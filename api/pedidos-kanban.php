<?php
// Retorna todos os pedidos agrupados por status com seus itens
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$pdo = getPDO();

$pedidos = $pdo->query("
    SELECT p.*, p.mesa
    FROM pedidos p
    WHERE p.status NOT IN ('entregue', 'cancelado')
       OR DATE(p.created_at) = CURDATE()
    ORDER BY p.created_at DESC
")->fetchAll();

$pedidoIds = array_column($pedidos, 'id');

$itensPorPedido = [];
if ($pedidoIds) {
    $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
    $stmt = $pdo->prepare("
        SELECT pi.pedido_id, pi.quantidade, pi.preco_unitario, pi.subtotal,
               COALESCE(pr.nome, cb.nome) AS nome,
               pi.ingredientes_removidos, pi.adicionais_json, pi.tamanho_nome
        FROM pedido_itens pi
        LEFT JOIN produtos pr ON pr.id = pi.produto_id
        LEFT JOIN combos cb ON cb.id = pi.combo_id
        WHERE pi.pedido_id IN ($placeholders)
    ");
    $stmt->execute($pedidoIds);
    foreach ($stmt->fetchAll() as $item) {
        $item['ingredientes_removidos'] = $item['ingredientes_removidos']
            ? json_decode($item['ingredientes_removidos'], true) : [];
        $item['adicionais'] = $item['adicionais_json']
            ? json_decode($item['adicionais_json'], true) : [];
        unset($item['adicionais_json']);
        $itensPorPedido[$item['pedido_id']][] = $item;
    }
}

$agrupados = [
    'pendente'     => [],
    'em_preparo'   => [],
    'saiu_entrega' => [],
    'entregue'     => [],
    'cancelado'    => [],
];

foreach ($pedidos as $p) {
    $p['itens'] = $itensPorPedido[$p['id']] ?? [];
    $agrupados[$p['status']][] = $p;
}

// Total do dia (entregues)
$totalDia = $pdo->query("
    SELECT COALESCE(SUM(total), 0)
    FROM pedidos
    WHERE status = 'entregue' AND DATE(created_at) = CURDATE()
")->fetchColumn();

echo json_encode([
    'pedidos'   => $agrupados,
    'total_dia' => (float) $totalDia,
]);
