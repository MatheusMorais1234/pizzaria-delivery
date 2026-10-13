<?php
// Protecao contra CSRF no painel admin.
//
// Sem isto, um site qualquer aberto pelo dono da pizzaria (com a sessao
// do admin ativa) poderia enviar formularios em nome dele: apagar
// produtos, mudar precos, cancelar pedidos.
//
// Cada sessao tem um token. Toda requisicao POST do admin precisa trazer
// o mesmo token - no campo "csrf" do formulario ou no cabecalho
// X-CSRF-Token (para o fetch do JavaScript). O layout_top.php insere o
// token em todos os formularios e chamadas automaticamente.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(): bool {
    $enviado = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($enviado) && $enviado !== '' && hash_equals(csrf_token(), $enviado);
}

// Encerra a requisicao se for POST sem token valido.
function csrf_exigir(bool $json = false): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || csrf_valido()) {
        return;
    }
    http_response_code(403);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Recarregue a página.']);
    } else {
        echo 'Sessão expirada. Volte e recarregue a página.';
    }
    exit;
}
