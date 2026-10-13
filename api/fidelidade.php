<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo  = getPDO();
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

if ($acao === 'consultar') {
    // Telefone completo (DDD + numero) e busca exata: com LIKE '%...%' bastavam
    // alguns digitos para descobrir o nome de qualquer cliente. E so os pontos
    // saem daqui - o nome nunca foi usado pelo carrinho.
    $tel = preg_replace('/\D/', '', $_GET['telefone'] ?? '');
    if (strlen($tel) < 10 || strlen($tel) > 13) {
        echo json_encode(['erro' => 'Telefone inválido']);
        exit;
    }
    $row = $pdo->prepare("SELECT pontos FROM fidelidade WHERE REGEXP_REPLACE(telefone,'[^0-9]','') = ?");
    $row->execute([$tel]);
    $dados = $row->fetch();
    echo json_encode(['pontos' => (int) ($dados['pontos'] ?? 0)]);
    exit;
}

echo json_encode(['erro' => 'Ação inválida']);
