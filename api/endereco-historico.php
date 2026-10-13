<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$telefone = trim($_GET['telefone'] ?? '');
$telefone = preg_replace('/\D/', '', $telefone);

if (strlen($telefone) < 8) {
    echo json_encode(null);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT endereco, bairro FROM pedidos
    WHERE REGEXP_REPLACE(cliente_telefone, '[^0-9]', '') LIKE ?
      AND tipo_entrega = 'entrega'
      AND endereco != ''
      AND endereco != 'Retirada na loja'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute(['%' . $telefone . '%']);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(null);
    exit;
}

// Parse address: "Rua X, 123 — Complemento — Bairro — Goiânia — CEP XXXXX"
$endereco = $row['endereco'];
$bairro   = $row['bairro'] ?? null;

echo json_encode([
    'endereco_completo' => $endereco,
    'bairro' => $bairro,
]);
