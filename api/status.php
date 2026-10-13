<?php
// API: retorna status atual de um pedido (GET) ou atualiza status (POST, admin)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$pedidoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ---- GET: consulta pública de status ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$pedidoId) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido.']);
        exit;
    }

    $stmt = getPDO()->prepare('SELECT status, motivo_cancelamento, token FROM pedidos WHERE id = ?');
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();

    // Consulta publica, mas so com o codigo secreto do pedido
    $token = (string) ($_GET['t'] ?? '');
    if (!$pedido || empty($pedido['token']) || !hash_equals($pedido['token'], $token)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido não encontrado.']);
        exit;
    }

    $etapas = ['pendente' => 1, 'em_preparo' => 2, 'saiu_entrega' => 3, 'entregue' => 4];

    echo json_encode([
        'sucesso'               => true,
        'status'                => $pedido['status'],
        'etapa'                 => $etapas[$pedido['status']] ?? 0,
        'motivo_cancelamento'   => $pedido['motivo_cancelamento'],
    ]);
    exit;
}

// ---- POST: admin atualiza status (requer sessão) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado.']);
        exit;
    }
    require_once __DIR__ . '/../includes/csrf.php';
    csrf_exigir(true);

    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = filter_var($body['pedido_id'] ?? 0, FILTER_VALIDATE_INT);
    $status = $body['status'] ?? '';
    $motivo = trim($body['motivo_cancelamento'] ?? '');

    $validos = ['pendente', 'em_preparo', 'saiu_entrega', 'entregue', 'cancelado'];
    if (!$id || !in_array($status, $validos, true)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos.']);
        exit;
    }

    $pdo = getPDO();
    if ($status === 'cancelado' && $motivo !== '') {
        $pdo->prepare('UPDATE pedidos SET status = ?, motivo_cancelamento = ? WHERE id = ?')
            ->execute([$status, $motivo, $id]);
    } else {
        $pdo->prepare('UPDATE pedidos SET status = ? WHERE id = ?')
            ->execute([$status, $id]);
    }

    echo json_encode(['sucesso' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
