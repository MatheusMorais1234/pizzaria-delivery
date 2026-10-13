<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valido' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$codigo = strtoupper(trim($body['codigo'] ?? ''));
$total  = (float) ($body['total'] ?? 0);

if (!$codigo) {
    echo json_encode(['valido' => false, 'mensagem' => 'Informe o código do cupom.']);
    exit;
}

$pdo  = getPDO();
$stmt = $pdo->prepare('SELECT * FROM cupons WHERE codigo = ? AND ativo = 1');
$stmt->execute([$codigo]);
$cupom = $stmt->fetch();

if (!$cupom) {
    echo json_encode(['valido' => false, 'mensagem' => 'Cupom inválido ou inativo.']);
    exit;
}

if ($cupom['validade'] && $cupom['validade'] < date('Y-m-d')) {
    echo json_encode(['valido' => false, 'mensagem' => 'Este cupom está expirado.']);
    exit;
}

if ($cupom['uso_limite'] !== null && $cupom['usos'] >= $cupom['uso_limite']) {
    echo json_encode(['valido' => false, 'mensagem' => 'Este cupom atingiu o limite de usos.']);
    exit;
}

if ($total < (float) $cupom['pedido_minimo']) {
    echo json_encode([
        'valido'   => false,
        'mensagem' => 'Pedido mínimo de R$ ' . number_format($cupom['pedido_minimo'], 2, ',', '.') . ' para usar este cupom.',
    ]);
    exit;
}

$desconto = $cupom['tipo'] === 'percentual'
    ? round($total * (float) $cupom['valor'] / 100, 2)
    : min((float) $cupom['valor'], $total);

echo json_encode([
    'valido'   => true,
    'codigo'   => $cupom['codigo'],
    'tipo'     => $cupom['tipo'],
    'valor'    => (float) $cupom['valor'],
    'desconto' => $desconto,
    'mensagem' => 'Cupom aplicado! −R$ ' . number_format($desconto, 2, ',', '.'),
]);
