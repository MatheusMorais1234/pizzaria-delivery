<?php
// API: salvar novo pedido
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

// Bloqueia pedidos fora do horário
if (!estaAberto()) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'A pizzaria está fechada no momento. Tente mais tarde!']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'JSON inválido.']);
    exit;
}

// Validação básica
$nome        = trim($body['cliente_nome']     ?? '');
$telefone    = trim($body['cliente_telefone'] ?? '');
$endereco    = trim($body['endereco']         ?? '');
$obs         = trim($body['observacao']       ?? '');
$mesa        = trim($body['mesa']             ?? '');
$itens       = $body['itens'] ?? [];
$cupomCodigo    = strtoupper(trim($body['cupom_codigo'] ?? ''));
$tipoEntrega    = in_array($body['tipo_entrega'] ?? '', ['entrega','retirada','mesa']) ? $body['tipo_entrega'] : 'entrega';
$formaPagamento = in_array($body['forma_pagamento'] ?? '', ['dinheiro','credito','debito','pix','mesa','Na mesa']) ? $body['forma_pagamento'] : 'dinheiro';
$trocoPara      = ($formaPagamento === 'dinheiro' && isset($body['troco_para']) && $body['troco_para'] > 0)
                    ? round((float)$body['troco_para'], 2) : null;

if ($tipoEntrega === 'mesa') {
    // Para pedidos de mesa, endereço e bairro não são obrigatórios
    if (!$nome || !$telefone) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Telefone da mesa é obrigatório.']);
        exit;
    }
    if (!$endereco) {
        $endereco = $mesa ? "Mesa $mesa" : 'Mesa';
    }
} else {
    if (!$nome || !$telefone || !$endereco) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nome, telefone e endereço são obrigatórios.']);
        exit;
    }
}

if (empty($itens)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'O carrinho está vazio.']);
    exit;
}

$pdo = getPDO();

// Verifica produtos e calcula total no servidor (não confiar no preço do cliente)
$total = 0;
$itensValidados = [];

foreach ($itens as $item) {
    $prodId  = filter_var($item['produto_id'] ?? 0, FILTER_VALIDATE_INT);
    $comboId = filter_var($item['combo_id']   ?? 0, FILTER_VALIDATE_INT);
    $qtd     = filter_var($item['quantidade'] ?? 0, FILTER_VALIDATE_INT);

    if ((!$prodId && !$comboId) || !$qtd || $qtd < 1) continue;

    // Handle combo items
    if ($comboId && !$prodId) {
        $stmtC = $pdo->prepare('SELECT id, nome, preco_combo FROM combos WHERE id = ? AND ativo = 1');
        $stmtC->execute([$comboId]);
        $combo = $stmtC->fetch();
        if (!$combo) continue;
        $precoUnit = (float) $combo['preco_combo'];
        $subtotal  = $precoUnit * $qtd;
        $total    += $subtotal;
        $itensValidados[] = [
            'produto_id'             => null,
            'combo_id'               => $combo['id'],
            'quantidade'             => $qtd,
            'preco_unit'             => $precoUnit,
            'subtotal'               => $subtotal,
            'ingredientes_removidos' => [],
            'adicionais_validados'   => [],
            'tamanho_nome'           => null,
        ];
        continue;
    }

    $stmt = $pdo->prepare('SELECT id, preco, desconto_pct, estoque, nome FROM produtos WHERE id = ? AND ativo = 1');
    $stmt->execute([$prodId]);
    $prod = $stmt->fetch();
    if (!$prod) continue;

    // Check stock
    if ($prod['estoque'] !== null && (int)$prod['estoque'] <= 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => "Produto '{$prod['nome']}' está esgotado."]);
        exit;
    }
    if ($prod['estoque'] !== null && (int)$prod['estoque'] < $qtd) {
        echo json_encode(['sucesso' => false, 'mensagem' => "Apenas {$prod['estoque']} unidade(s) disponível(eis) de '{$prod['nome']}'."]);
        exit;
    }

    // Ingredientes removidos: só aceita os que de fato pertencem ao produto.
    // O texto vem do navegador e depois aparece no painel do admin — aceitar
    // qualquer string abriria espaço para XSS armazenado.
    $stmtIng = $pdo->prepare('SELECT nome FROM produto_ingredientes WHERE produto_id = ?');
    $stmtIng->execute([$prodId]);
    $ingsDoProduto = $stmtIng->fetchAll(PDO::FETCH_COLUMN);
    $ingsRemovidos = array_values(array_intersect(
        $ingsDoProduto,
        array_map('strval', (array) ($item['ingredientes_removidos'] ?? []))
    ));

    // Valida adicionais no servidor (busca preço do BD, ignora preço do cliente)
    $adicionaisIds = array_filter(
        array_map(fn($a) => filter_var($a['id'] ?? 0, FILTER_VALIDATE_INT), $item['adicionais'] ?? [])
    );
    $extraTotal = 0;
    $adicionaisValidados = [];
    foreach (array_unique($adicionaisIds) as $aid) {
        if (!$aid) continue;
        $stmtA = $pdo->prepare('SELECT id, nome, preco FROM adicionais WHERE id=? AND ativo=1');
        $stmtA->execute([$aid]);
        $ad = $stmtA->fetch();
        if ($ad) {
            $extraTotal += (float) $ad['preco'];
            $adicionaisValidados[] = ['id' => $ad['id'], 'nome' => $ad['nome'], 'preco' => (float) $ad['preco']];
        }
    }

    // Valida tamanho se fornecido
    $tamanhoNome = trim($item['tamanho_nome'] ?? '');
    if ($tamanhoNome) {
        $stmtT = $pdo->prepare('SELECT preco FROM produto_tamanhos WHERE produto_id=? AND nome=? AND ativo=1');
        $stmtT->execute([$prodId, $tamanhoNome]);
        $tam = $stmtT->fetch();
        if ($tam) {
            // Override product price with size price
            $prod['preco'] = $tam['preco'];
            $prod['desconto_pct'] = 0; // tamanho price is already final
        }
    }

    $precoBase = round($prod['preco'] * (1 - (float)$prod['desconto_pct'] / 100), 2);
    $precoUnit = $precoBase + $extraTotal;
    $subtotal  = $precoUnit * $qtd;
    $total    += $subtotal;
    $itensValidados[] = [
        'produto_id'              => $prod['id'],
        'combo_id'                => null,
        'quantidade'              => $qtd,
        'preco_unit'              => $precoUnit,
        'subtotal'                => $subtotal,
        'ingredientes_removidos'  => $ingsRemovidos,
        'adicionais_validados'    => $adicionaisValidados,
        'tamanho_nome'            => $tamanhoNome,
    ];
}

if (empty($itensValidados)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum produto válido encontrado.']);
    exit;
}

// Pedido mínimo (não se aplica a mesa)
$pedidoMinimo = (float) getConfig('pedido_minimo', '0');
if ($tipoEntrega === 'entrega' && $pedidoMinimo > 0 && $total < $pedidoMinimo) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido mínimo para entrega é R$ ' . number_format($pedidoMinimo, 2, ',', '.') . '. Adicione mais itens!']);
    exit;
}

// Valida e aplica cupom no servidor
$descontoCupom = 0.0;
$cupomValido   = null;
if ($cupomCodigo) {
    $stmtC = $pdo->prepare('SELECT * FROM cupons WHERE codigo = ? AND ativo = 1');
    $stmtC->execute([$cupomCodigo]);
    $cupom = $stmtC->fetch();

    if ($cupom
        && (!$cupom['validade'] || $cupom['validade'] >= date('Y-m-d'))
        && ($cupom['uso_limite'] === null || $cupom['usos'] < $cupom['uso_limite'])
        && $total >= (float) $cupom['pedido_minimo']
    ) {
        $descontoCupom = $cupom['tipo'] === 'percentual'
            ? round($total * (float) $cupom['valor'] / 100, 2)
            : min((float) $cupom['valor'], $total);
        $cupomValido = $cupom;
        $total = max(0, $total - $descontoCupom);
    }
}

// Taxa do cartão (calculada no servidor a partir da config)
$taxaCartaoPct = 0.0;
if ($formaPagamento === 'credito') {
    $taxaCartaoPct = (float) ($pdo->query("SELECT valor FROM configuracoes WHERE chave='taxa_credito_pct'")->fetchColumn() ?: 0);
} elseif ($formaPagamento === 'debito') {
    $taxaCartaoPct = (float) ($pdo->query("SELECT valor FROM configuracoes WHERE chave='taxa_debito_pct'")->fetchColumn() ?: 0);
}
if ($taxaCartaoPct > 0) {
    $taxaCartaoValor = round($total * $taxaCartaoPct / 100, 2);
    $total += $taxaCartaoValor;
} else {
    $taxaCartaoPct = 0.0;
}

// Fidelidade — validar pontos resgatados
$fidelidadeAtivo  = getConfig('fidelidade_ativo', '0') === '1';
$pontosResgatados = 0;
$descontoFidelidade = 0.0;
$reaisporPonto    = max(1, (float) getConfig('fidelidade_reais_por_ponto', '10'));
$valorPontoUnit   = max(0.01, (float) getConfig('fidelidade_valor_ponto', '0.10'));
$resgateMinimo    = max(1, (int) getConfig('fidelidade_resgate_minimo', '50'));

if ($fidelidadeAtivo) {
    $pontosResgatados = max(0, (int)($body['pontos_resgatados'] ?? 0));
    if ($pontosResgatados > 0) {
        // Validate the customer has enough points
        $telLimpo = preg_replace('/\D/', '', $telefone);
        $stmtFid = $pdo->prepare("SELECT pontos FROM fidelidade WHERE REGEXP_REPLACE(telefone,'[^0-9]','') = ?");
        $stmtFid->execute([$telLimpo]);
        $fidRow = $stmtFid->fetch();
        $saldoAtual = $fidRow ? (int)$fidRow['pontos'] : 0;
        if ($pontosResgatados > $saldoAtual || $saldoAtual < $resgateMinimo) {
            $pontosResgatados = 0; // silently ignore invalid redemption
        } else {
            $descontoFidelidade = round($pontosResgatados * $valorPontoUnit, 2);
            $total = max(0, $total - $descontoFidelidade);
        }
    }
}

// Taxa de entrega (só para delivery, não retirada nem mesa)
$taxaEntrega = 0.0;
$bairroNome  = null;
if ($tipoEntrega === 'entrega') {
    $taxaEntrega = round(max(0, (float)($body['taxa_entrega'] ?? 0)), 2);
    $bairroNome  = trim($body['bairro'] ?? '') ?: null;
    // Validate against DB if bairro provided
    if ($bairroNome) {
        $stmtB = $pdo->prepare('SELECT taxa_entrega FROM bairros WHERE nome = ? AND ativo = 1');
        $stmtB->execute([$bairroNome]);
        $bairroDb = $stmtB->fetch();
        if ($bairroDb) {
            $taxaEntrega = (float) $bairroDb['taxa_entrega'];
        }
    }
    $total += $taxaEntrega;
}

try {
    $pdo->beginTransaction();

    // Calcular pontos ganhos neste pedido
    $pontosGanhos = 0;
    if ($fidelidadeAtivo && $reaisporPonto > 0) {
        $pontosGanhos = (int) floor($total / $reaisporPonto);
    }

    // Codigo secreto do pedido: so quem tem o link (id + token) acompanha
    $token = bin2hex(random_bytes(16));

    // Insere pedido
    $stmt = $pdo->prepare('
        INSERT INTO pedidos (cliente_nome, cliente_telefone, endereco, observacao, total, cupom_codigo, desconto_cupom, tipo_entrega, forma_pagamento, troco_para, taxa_cartao_pct, bairro, taxa_entrega, pontos_ganhos, pontos_resgatados, desconto_fidelidade, mesa, token, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pendente\')
    ');
    $stmt->execute([$nome, $telefone, $endereco, $obs, $total, $cupomValido ? $cupomValido['codigo'] : null, $descontoCupom, $tipoEntrega, $formaPagamento, $trocoPara, $taxaCartaoPct, $bairroNome, $taxaEntrega, $pontosGanhos, $pontosResgatados, $descontoFidelidade, $mesa ?: null, $token]);
    $pedidoId = (int) $pdo->lastInsertId();

    // Insere itens
    $stmtItem = $pdo->prepare('
        INSERT INTO pedido_itens (pedido_id, produto_id, combo_id, quantidade, preco_unitario, subtotal, ingredientes_removidos, adicionais_json, tamanho_nome)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($itensValidados as $i) {
        $ingJson = !empty($i['ingredientes_removidos']) ? json_encode($i['ingredientes_removidos'], JSON_UNESCAPED_UNICODE) : null;
        $addJson = !empty($i['adicionais_validados'])   ? json_encode($i['adicionais_validados'],   JSON_UNESCAPED_UNICODE) : null;
        $tamNome = $i['tamanho_nome'] ?: null;
        $stmtItem->execute([$pedidoId, $i['produto_id'], $i['combo_id'] ?? null, $i['quantidade'], $i['preco_unit'], $i['subtotal'], $ingJson, $addJson, $tamNome]);
    }

    // Decrementa estoque dos produtos
    foreach ($itensValidados as $iv) {
        if ($iv['produto_id']) {
            $pdo->prepare('UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND estoque IS NOT NULL')
                ->execute([$iv['quantidade'], $iv['produto_id']]);
        }
    }

    // Incrementa uso do cupom
    if ($cupomValido) {
        $pdo->prepare('UPDATE cupons SET usos = usos + 1 WHERE id = ?')->execute([$cupomValido['id']]);
    }

    // Fidelidade — atualizar saldo do cliente
    if ($fidelidadeAtivo) {
        $telLimpoFid = preg_replace('/\D/', '', $telefone);
        // Upsert registro de fidelidade
        $pdo->prepare("
            INSERT INTO fidelidade (telefone, nome, pontos)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nome   = COALESCE(NULLIF(VALUES(nome),''), nome),
                pontos = pontos
        ")->execute([$telefone, $nome, 0]);

        // Débito por resgate
        if ($pontosResgatados > 0) {
            $pdo->prepare("UPDATE fidelidade SET pontos = GREATEST(0, pontos - ?) WHERE REGEXP_REPLACE(telefone,'[^0-9]','') = ?")
                ->execute([$pontosResgatados, $telLimpoFid]);
            $pdo->prepare("INSERT INTO fidelidade_log (telefone, pedido_id, pontos, tipo, descricao) VALUES (?,?,?,'debito',?)")
                ->execute([$telefone, $pedidoId, $pontosResgatados, 'Resgate no pedido #' . $pedidoId]);
        }

        // Crédito por compra
        if ($pontosGanhos > 0) {
            $pdo->prepare("UPDATE fidelidade SET pontos = pontos + ? WHERE REGEXP_REPLACE(telefone,'[^0-9]','') = ?")
                ->execute([$pontosGanhos, $telLimpoFid]);
            $pdo->prepare("INSERT INTO fidelidade_log (telefone, pedido_id, pontos, tipo, descricao) VALUES (?,?,?,'credito',?)")
                ->execute([$telefone, $pedidoId, $pontosGanhos, 'Pontos ganhos no pedido #' . $pedidoId]);
        }
    }

    $pdo->commit();

    echo json_encode(['sucesso' => true, 'pedido_id' => $pedidoId, 'token' => $token]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno ao salvar pedido.']);
}
