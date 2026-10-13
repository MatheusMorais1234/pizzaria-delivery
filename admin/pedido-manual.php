<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo    = getPDO();
$erros  = [];
$sucesso = false;

// ══════════════════════════════════════════════════════════════
// PROCESSAMENTO DO POST
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome           = trim($_POST['cliente_nome']      ?? '');
    $telefone       = trim($_POST['cliente_telefone']  ?? '');
    $tipoEntrega    = $_POST['tipo_entrega'] === 'retirada' ? 'retirada' : 'entrega';
    $endereco       = trim($_POST['endereco']          ?? '');
    $observacao     = trim($_POST['observacao']        ?? '');
    $formaPagamento = in_array($_POST['forma_pagamento'] ?? '', ['dinheiro','credito','debito','pix'])
                      ? $_POST['forma_pagamento'] : 'dinheiro';
    $trocoPara      = ($formaPagamento === 'dinheiro' && !empty($_POST['troco_para']))
                      ? round((float)$_POST['troco_para'], 2) : null;
    $statusInicial  = in_array($_POST['status_inicial'] ?? '', ['pendente', 'em_preparo'])
                      ? $_POST['status_inicial']
                      : 'em_preparo';

    // Decodifica itens do carrinho (JSON)
    $itensRaw = $_POST['itens'] ?? '[]';
    $itens    = json_decode($itensRaw, true);
    if (!is_array($itens)) $itens = [];

    // Validações
    if (!$nome)     $erros[] = 'Nome do cliente é obrigatório.';
    if (!$telefone) $erros[] = 'Telefone/WhatsApp é obrigatório.';
    if ($tipoEntrega === 'entrega' && !$endereco) $erros[] = 'Endereço é obrigatório para entrega.';
    if (empty($itens)) $erros[] = 'Adicione pelo menos um item ao pedido.';

    // Calcula total no servidor (segurança — não confia no cliente)
    $totalServidor = 0;
    $itensValidados = [];
    if (!empty($itens)) {
        $ids = array_map('intval', array_column($itens, 'produto_id'));
        $ids = array_filter($ids);

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, preco FROM produtos WHERE id IN ($placeholders) AND ativo = 1");
            $stmt->execute($ids);
            $precosDB = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => preco]

            foreach ($itens as $item) {
                $pid = (int)($item['produto_id'] ?? 0);
                $qty = max(1, (int)($item['quantidade'] ?? 1));
                if (!isset($precosDB[$pid])) continue; // produto inativo ou inexistente
                $preco    = (float)$precosDB[$pid];
                $subtotal = $preco * $qty;
                $totalServidor += $subtotal;
                $itensValidados[] = [
                    'produto_id'  => $pid,
                    'quantidade'  => $qty,
                    'preco'       => $preco,
                    'subtotal'    => $subtotal,
                    'nome'        => $item['nome'] ?? '',
                ];
            }
        }

        if (empty($itensValidados)) $erros[] = 'Nenhum item válido encontrado no pedido.';
    }

    // Insere no banco dentro de uma transaction
    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $sqlPedido = "INSERT INTO pedidos
                            (cliente_nome, cliente_telefone, endereco, observacao, total, status, tipo_entrega, forma_pagamento, troco_para)
                          VALUES
                            (:nome, :telefone, :endereco, :observacao, :total, :status, :tipo_entrega, :forma_pagamento, :troco_para)";
            $stmtP = $pdo->prepare($sqlPedido);
            $stmtP->execute([
                ':nome'            => $nome,
                ':telefone'        => $telefone,
                ':endereco'        => $endereco,
                ':observacao'      => $observacao,
                ':total'           => $totalServidor,
                ':status'          => $statusInicial,
                ':tipo_entrega'    => $tipoEntrega,
                ':forma_pagamento' => $formaPagamento,
                ':troco_para'      => $trocoPara,
            ]);

            $pedidoId = (int)$pdo->lastInsertId();

            // INSERT dos itens
            $stmtItem = $pdo->prepare("INSERT INTO pedido_itens
                                          (pedido_id, produto_id, quantidade, preco_unitario, subtotal,
                                           ingredientes_removidos, adicionais_json)
                                        VALUES
                                          (:pedido_id, :produto_id, :quantidade, :preco, :subtotal,
                                           :ingredientes, :adicionais)");
            foreach ($itensValidados as $it) {
                $stmtItem->execute([
                    ':pedido_id'    => $pedidoId,
                    ':produto_id'   => $it['produto_id'],
                    ':quantidade'   => $it['quantidade'],
                    ':preco'        => $it['preco'],
                    ':subtotal'     => $it['subtotal'],
                    ':ingredientes' => null,
                    ':adicionais'   => null,
                ]);
            }

            $pdo->commit();

            // Redireciona para o kanban com confirmação
            header('Location: ' . BASE . '/admin/pedidos.php?adicionado=1');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar pedido: ' . $e->getMessage();
        }
    }
}

// ══════════════════════════════════════════════════════════════
// CARREGA PRODUTOS AGRUPADOS POR CATEGORIA
// ══════════════════════════════════════════════════════════════
$categorias = $pdo->query("
    SELECT c.id AS cat_id, c.nome AS cat_nome,
           p.id, p.nome, p.preco, p.imagem_url, p.descricao
    FROM categorias c
    JOIN produtos p ON p.categoria_id = c.id AND p.ativo = 1
    ORDER BY c.ordem, c.nome, p.nome
")->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por categoria
$cardapio = [];
foreach ($categorias as $row) {
    $catId = $row['cat_id'];
    if (!isset($cardapio[$catId])) {
        $cardapio[$catId] = ['nome' => $row['cat_nome'], 'produtos' => []];
    }
    $cardapio[$catId]['produtos'][] = $row;
}

// Recarrega valores submetidos para repopular formulário após erro
$postNome      = htmlspecialchars($_POST['cliente_nome']     ?? '');
$postTelefone  = htmlspecialchars($_POST['cliente_telefone'] ?? '');
$postEndereco  = htmlspecialchars($_POST['endereco']         ?? '');
$postObs       = htmlspecialchars($_POST['observacao']       ?? '');
$postTipo      = $_POST['tipo_entrega'] ?? 'entrega';
$postStatus    = $_POST['status_inicial'] ?? 'em_preparo';
$postItens     = htmlspecialchars($_POST['itens'] ?? '[]', ENT_QUOTES);

$pageTitle = 'Novo Pedido Manual — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- Flash de sucesso (via query string vindo de redirect) -->
<?php if (isset($_GET['adicionado'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Pedido registrado com sucesso! Ele já aparece no kanban.
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-5">
    <a href="<?= BASE ?>/admin/pedidos.php" class="hover:text-gray-700 transition-colors font-medium">Pedidos</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-700 font-semibold">Novo Pedido Manual</span>
</div>

<!-- Cabeçalho da página -->
<div class="flex flex-wrap items-start gap-3 mb-6">
    <div>
        <div class="flex items-center gap-3 mb-1">
            <h1 class="text-2xl font-extrabold text-gray-900">Novo Pedido Manual</h1>
            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                    <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"/>
                </svg>
                WhatsApp
            </span>
        </div>
        <p class="text-sm text-gray-500">Registrar pedido recebido pelo WhatsApp para aparecer no kanban</p>
    </div>
    <div class="ml-auto">
        <a href="<?= BASE ?>/admin/pedidos.php"
           class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-600 font-semibold text-sm rounded-xl hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Voltar ao Kanban
        </a>
    </div>
</div>

<!-- Erros -->
<?php if (!empty($erros)): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
    <p class="font-semibold text-red-700 text-sm mb-1 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Corrija os erros abaixo:
    </p>
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        <?php foreach ($erros as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" id="form-pedido-manual" onsubmit="return prepararSubmit(event)">
<input type="hidden" name="itens" id="itens-json" value="<?= $postItens ?>">

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- ══════════════════════════════════════════════════════════
         COLUNA ESQUERDA — Dados do cliente
    ══════════════════════════════════════════════════════════════ -->
    <div class="space-y-5">

        <!-- Card: Dados do cliente -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
                Dados do cliente
            </h2>

            <div class="space-y-4">

                <!-- Nome -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Nome do cliente <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="cliente_nome" required
                           value="<?= $postNome ?>"
                           placeholder="Ex: João Silva"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50">
                </div>

                <!-- Telefone -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Telefone / WhatsApp <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </span>
                        <input type="text" name="cliente_telefone" required
                               value="<?= $postTelefone ?>"
                               placeholder="(11) 99999-9999"
                               class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50">
                    </div>
                </div>

                <!-- Tipo de entrega -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Tipo de entrega <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label id="label-entrega"
                               class="entrega-opcao flex items-center gap-3 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all
                                      <?= $postTipo !== 'retirada' ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' ?>">
                            <input type="radio" name="tipo_entrega" value="entrega" class="sr-only"
                                   onchange="alternarTipoEntrega('entrega')"
                                   <?= $postTipo !== 'retirada' ? 'checked' : '' ?>>
                            <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                                         <?= $postTipo !== 'retirada' ? 'bg-red-100' : 'bg-gray-100' ?>">
                                <svg class="w-4 h-4 <?= $postTipo !== 'retirada' ? 'text-red-600' : 'text-gray-500' ?>"
                                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </span>
                            <div>
                                <p class="font-semibold text-sm <?= $postTipo !== 'retirada' ? 'text-red-700' : 'text-gray-700' ?>">Entrega</p>
                                <p class="text-xs text-gray-400">No endereço</p>
                            </div>
                        </label>
                        <label id="label-retirada"
                               class="entrega-opcao flex items-center gap-3 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all
                                      <?= $postTipo === 'retirada' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' ?>">
                            <input type="radio" name="tipo_entrega" value="retirada" class="sr-only"
                                   onchange="alternarTipoEntrega('retirada')"
                                   <?= $postTipo === 'retirada' ? 'checked' : '' ?>>
                            <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0
                                         <?= $postTipo === 'retirada' ? 'bg-blue-100' : 'bg-gray-100' ?>">
                                <svg class="w-4 h-4 <?= $postTipo === 'retirada' ? 'text-blue-600' : 'text-gray-500' ?>"
                                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </span>
                            <div>
                                <p class="font-semibold text-sm <?= $postTipo === 'retirada' ? 'text-blue-700' : 'text-gray-700' ?>">Retirada</p>
                                <p class="text-xs text-gray-400">Na loja</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Endereço (aparece/some dinamicamente) -->
                <div id="bloco-endereco" class="<?= $postTipo === 'retirada' ? 'hidden' : '' ?>">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Endereço de entrega <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </span>
                        <textarea name="endereco" id="campo-endereco" rows="2"
                                  placeholder="Rua, número, bairro, complemento…"
                                  class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm
                                         focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent
                                         bg-gray-50 resize-none"><?= $postEndereco ?></textarea>
                    </div>
                </div>

                <!-- Observação -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Observação
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea name="observacao" rows="2"
                              placeholder="Ex: sem cebola, alérgico a frutos do mar…"
                              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                     focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent
                                     bg-gray-50 resize-none"><?= $postObs ?></textarea>
                </div>

            </div>
        </div>

        <!-- Card: Status inicial -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                Status inicial
            </h2>
            <div class="grid grid-cols-2 gap-3">
                <label id="label-pendente"
                       class="status-opcao flex items-center gap-3 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all
                              <?= $postStatus === 'pendente' ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' ?>">
                    <input type="radio" name="status_inicial" value="pendente" class="sr-only"
                           onchange="alternarStatus('pendente')"
                           <?= $postStatus === 'pendente' ? 'checked' : '' ?>>
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-yellow-400"></span>
                    <div>
                        <p class="font-semibold text-sm <?= $postStatus === 'pendente' ? 'text-yellow-700' : 'text-gray-700' ?>">Pendente</p>
                        <p class="text-xs text-gray-400">Aguardando confirmação</p>
                    </div>
                </label>
                <label id="label-em_preparo"
                       class="status-opcao flex items-center gap-3 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all
                              <?= $postStatus === 'em_preparo' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' ?>">
                    <input type="radio" name="status_inicial" value="em_preparo" class="sr-only"
                           onchange="alternarStatus('em_preparo')"
                           <?= $postStatus !== 'pendente' ? 'checked' : '' ?>>
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-blue-500"></span>
                    <div>
                        <p class="font-semibold text-sm <?= $postStatus === 'em_preparo' ? 'text-blue-700' : 'text-gray-700' ?>">Em preparo</p>
                        <p class="text-xs text-gray-400">Já confirmado</p>
                    </div>
                </label>
            </div>
            <p class="text-xs text-gray-400 mt-3">
                Pedidos do WhatsApp geralmente já estão confirmados — inicie em <strong>Em preparo</strong>.
            </p>
        </div>

        <!-- Card: Forma de pagamento -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
                Forma de pagamento
            </h2>
            <div class="space-y-2">
                <?php
                $postPag = $_POST['forma_pagamento'] ?? 'dinheiro';
                $pagopts = [
                    'dinheiro' => ['emoji'=>'💵','label'=>'Dinheiro','sub'=>'Pago na entrega'],
                    'credito'  => ['emoji'=>'💳','label'=>'Crédito na entrega','sub'=>'Maquininha crédito'],
                    'debito'   => ['emoji'=>'💳','label'=>'Débito na entrega','sub'=>'Maquininha débito'],
                    'pix'      => ['emoji'=>'📱','label'=>'PIX na entrega','sub'=>'Via app do banco'],
                ];
                foreach ($pagopts as $val => $opt):
                    $sel = $postPag === $val;
                ?>
                <label class="pag-opcao flex items-center gap-3 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all <?= $sel ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-gray-50 hover:border-gray-300' ?>">
                    <input type="radio" name="forma_pagamento" value="<?= $val ?>" class="sr-only"
                           onchange="alternarPagamento('<?= $val ?>')"
                           <?= $sel ? 'checked' : '' ?>>
                    <span class="text-xl leading-none flex-shrink-0"><?= $opt['emoji'] ?></span>
                    <div>
                        <p class="font-semibold text-sm <?= $sel ? 'text-red-700' : 'text-gray-700' ?>"><?= $opt['label'] ?></p>
                        <p class="text-xs text-gray-400"><?= $opt['sub'] ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <!-- Troco (só para dinheiro) -->
            <div id="bloco-troco-manual" class="mt-4 <?= $postPag !== 'dinheiro' ? 'hidden' : '' ?>">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Troco para quanto? <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-bold">R$</span>
                    <input type="number" name="troco_para" min="0" step="0.01"
                           value="<?= htmlspecialchars($_POST['troco_para'] ?? '') ?>"
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                           placeholder="0,00">
                </div>
            </div>
        </div>

        <!-- Card: Resumo do pedido / Botão enviar -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </span>
                Resumo do pedido
            </h2>

            <!-- Itens do carrinho (JS) -->
            <div id="resumo-carrinho-vazio" class="text-center py-6 text-gray-300">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-10H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p class="text-sm font-medium">Nenhum item adicionado</p>
                <p class="text-xs mt-1">Selecione produtos no cardápio ao lado</p>
            </div>
            <div id="resumo-carrinho-lista" class="hidden space-y-2 mb-4"></div>

            <!-- Total -->
            <div id="resumo-total-bloco" class="hidden">
                <div class="border-t border-gray-100 pt-3 mb-4 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-600">Total do pedido</span>
                    <span id="resumo-total" class="font-extrabold text-red-600 text-xl">R$ 0,00</span>
                </div>
            </div>

            <!-- Aviso de validação JS -->
            <div id="aviso-form" class="hidden bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm text-red-600 font-medium"></div>

            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-bold py-3.5 rounded-xl
                           transition-colors shadow-sm flex items-center justify-center gap-2 text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Registrar pedido no sistema
            </button>
            <a href="<?= BASE ?>/admin/pedidos.php"
               class="mt-3 w-full flex items-center justify-center text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors">
                Cancelar e voltar ao kanban
            </a>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════
         COLUNA DIREITA — Seleção de produtos (cardápio)
    ══════════════════════════════════════════════════════════════ -->
    <div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <!-- Header cardápio -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </div>
                <h2 class="text-base font-bold text-gray-800 flex-1">Cardápio</h2>
                <span class="text-xs text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full font-medium">
                    <?= count($categorias) ?> itens ativos
                </span>
            </div>

            <!-- Busca rápida no cardápio -->
            <div class="px-6 py-3 border-b border-gray-100">
                <div class="relative">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                    <input type="search" id="busca-cardapio" placeholder="Buscar produto…"
                           oninput="filtrarCardapio(this.value)"
                           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent">
                </div>
            </div>

            <!-- Lista de produtos por categoria -->
            <div class="overflow-y-auto" style="max-height: calc(100vh - 280px);">
                <?php if (empty($cardapio)): ?>
                <div class="text-center py-12 text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <p class="text-sm font-medium">Nenhum produto ativo cadastrado</p>
                </div>
                <?php else: ?>
                    <?php foreach ($cardapio as $catId => $cat): ?>
                    <div class="categoria-bloco">
                        <!-- Cabeçalho da categoria -->
                        <div class="px-6 py-2.5 bg-gray-50 border-b border-gray-100 sticky top-0 z-10">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">
                                <?= htmlspecialchars($cat['nome']) ?>
                            </p>
                        </div>
                        <!-- Produtos -->
                        <div class="divide-y divide-gray-50">
                            <?php foreach ($cat['produtos'] as $prod): ?>
                            <div class="produto-item px-6 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors"
                                 data-nome="<?= strtolower(htmlspecialchars($prod['nome'])) ?>"
                                 data-cat="<?= strtolower(htmlspecialchars($cat['nome'])) ?>">

                                <!-- Imagem ou placeholder -->
                                <?php if (!empty($prod['imagem_url'])): ?>
                                <img src="<?= htmlspecialchars($prod['imagem_url']) ?>"
                                     alt="<?= htmlspecialchars($prod['nome']) ?>"
                                     class="w-12 h-12 object-cover rounded-xl flex-shrink-0 border border-gray-100"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center text-xl flex-shrink-0 border border-gray-100" style="display:none;">🍕</div>
                                <?php else: ?>
                                <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center text-xl flex-shrink-0 border border-gray-100">🍕</div>
                                <?php endif; ?>

                                <!-- Info do produto -->
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-900 text-sm leading-tight truncate">
                                        <?= htmlspecialchars($prod['nome']) ?>
                                    </p>
                                    <?php if (!empty($prod['descricao'])): ?>
                                    <p class="text-xs text-gray-400 truncate mt-0.5">
                                        <?= htmlspecialchars($prod['descricao']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <p class="text-sm font-bold text-red-600 mt-1">
                                        R$ <?= number_format($prod['preco'], 2, ',', '.') ?>
                                    </p>
                                </div>

                                <!-- Botão adicionar -->
                                <button type="button"
                                        onclick="adicionarProduto(<?= (int)$prod['id'] ?>, <?= htmlspecialchars(json_encode($prod['nome']), ENT_QUOTES) ?>, <?= number_format($prod['preco'], 2, '.', '') ?>)"
                                        class="w-9 h-9 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white
                                               rounded-xl flex items-center justify-center flex-shrink-0
                                               transition-colors shadow-sm font-bold text-lg leading-none"
                                        title="Adicionar ao pedido">
                                    +
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /grid -->
</form>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
// ══════════════════════════════════════════════════════════════
// Estado do carrinho
// ══════════════════════════════════════════════════════════════
let carrinho = [];

// Restaura carrinho do hidden input se houver (após erro de POST)
(function() {
    try {
        const raw = document.getElementById('itens-json').value;
        if (raw && raw !== '[]') {
            carrinho = JSON.parse(raw) || [];
        }
    } catch(e) {}
    renderCarrinho();
})();

// ══════════════════════════════════════════════════════════════
// Gerenciar carrinho
// ══════════════════════════════════════════════════════════════
function adicionarProduto(id, nome, preco) {
    const idx = carrinho.findIndex(i => i.produto_id === id);
    if (idx >= 0) {
        carrinho[idx].quantidade++;
    } else {
        carrinho.push({ produto_id: id, nome, preco: parseFloat(preco), quantidade: 1 });
    }
    renderCarrinho();
    pulsarBotao(id);
}

function incrementarItem(id) {
    const idx = carrinho.findIndex(i => i.produto_id === id);
    if (idx >= 0) { carrinho[idx].quantidade++; renderCarrinho(); }
}

function decrementarItem(id) {
    const idx = carrinho.findIndex(i => i.produto_id === id);
    if (idx < 0) return;
    if (carrinho[idx].quantidade <= 1) {
        removerItem(id);
    } else {
        carrinho[idx].quantidade--;
        renderCarrinho();
    }
}

function removerItem(id) {
    carrinho = carrinho.filter(i => i.produto_id !== id);
    renderCarrinho();
}

function renderCarrinho() {
    const listaEl   = document.getElementById('resumo-carrinho-lista');
    const vazioEl   = document.getElementById('resumo-carrinho-vazio');
    const totalBloc = document.getElementById('resumo-total-bloco');
    const totalEl   = document.getElementById('resumo-total');

    if (carrinho.length === 0) {
        listaEl.classList.add('hidden');
        listaEl.innerHTML = '';
        vazioEl.classList.remove('hidden');
        totalBloc.classList.add('hidden');
        return;
    }

    vazioEl.classList.add('hidden');
    listaEl.classList.remove('hidden');
    totalBloc.classList.remove('hidden');

    let totalGeral = 0;
    listaEl.innerHTML = carrinho.map(item => {
        const subtotal = item.preco * item.quantidade;
        totalGeral += subtotal;
        return `
        <div class="flex items-center gap-3 bg-gray-50 border border-gray-100 rounded-xl px-3 py-2.5">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 leading-tight truncate">${esc(item.nome)}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    R$ ${fmt(item.preco)} × ${item.quantidade}
                    = <span class="font-bold text-gray-700">R$ ${fmt(subtotal)}</span>
                </p>
            </div>
            <!-- Controles de quantidade -->
            <div class="flex items-center gap-1.5 flex-shrink-0">
                <button type="button" onclick="decrementarItem(${item.produto_id})"
                        class="w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-gray-600
                               font-bold text-base flex items-center justify-center transition-colors leading-none"
                        title="Diminuir">−</button>
                <span class="w-6 text-center font-bold text-sm text-gray-800">${item.quantidade}</span>
                <button type="button" onclick="incrementarItem(${item.produto_id})"
                        class="w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-gray-600
                               font-bold text-base flex items-center justify-center transition-colors leading-none"
                        title="Aumentar">+</button>
            </div>
            <!-- Remover -->
            <button type="button" onclick="removerItem(${item.produto_id})"
                    class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 transition-colors flex-shrink-0"
                    title="Remover item">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>`;
    }).join('');

    totalEl.textContent = 'R$ ' + fmt(totalGeral);
}

// ══════════════════════════════════════════════════════════════
// Forma de pagamento: visual + troco
// ══════════════════════════════════════════════════════════════
function alternarPagamento(val) {
    document.querySelectorAll('.pag-opcao').forEach(el => {
        const radio = el.querySelector('input[type="radio"]');
        const p     = el.querySelector('p.font-semibold');
        const sel   = radio.value === val;
        el.classList.toggle('border-red-500', sel);
        el.classList.toggle('bg-red-50', sel);
        el.classList.toggle('border-gray-200', !sel);
        el.classList.toggle('bg-gray-50', !sel);
        el.classList.toggle('hover:border-gray-300', !sel);
        if (p) { p.classList.toggle('text-red-700', sel); p.classList.toggle('text-gray-700', !sel); }
    });
    document.getElementById('bloco-troco-manual').classList.toggle('hidden', val !== 'dinheiro');
}

// ══════════════════════════════════════════════════════════════
// Tipo de entrega: mostrar/ocultar campo endereço
// ══════════════════════════════════════════════════════════════
function alternarTipoEntrega(tipo) {
    const blocoEnd = document.getElementById('bloco-endereco');
    const lblEnt   = document.getElementById('label-entrega');
    const lblRet   = document.getElementById('label-retirada');

    if (tipo === 'retirada') {
        blocoEnd.classList.add('hidden');
        document.getElementById('campo-endereco').removeAttribute('required');
        lblEnt.className = lblEnt.className
            .replace('border-red-500 bg-red-50', 'border-gray-200 bg-gray-50 hover:border-gray-300');
        lblRet.className = lblRet.className
            .replace('border-gray-200 bg-gray-50 hover:border-gray-300', 'border-blue-500 bg-blue-50');
    } else {
        blocoEnd.classList.remove('hidden');
        document.getElementById('campo-endereco').setAttribute('required', 'required');
        lblRet.className = lblRet.className
            .replace('border-blue-500 bg-blue-50', 'border-gray-200 bg-gray-50 hover:border-gray-300');
        lblEnt.className = lblEnt.className
            .replace('border-gray-200 bg-gray-50 hover:border-gray-300', 'border-red-500 bg-red-50');
    }
}

// Garante o required correto no load (caso retirada esteja pré-selecionada)
(function() {
    const checked = document.querySelector('input[name="tipo_entrega"]:checked');
    if (checked && checked.value === 'retirada') {
        document.getElementById('campo-endereco').removeAttribute('required');
    }
})();

// ══════════════════════════════════════════════════════════════
// Status: efeito visual nas opções
// ══════════════════════════════════════════════════════════════
function alternarStatus(status) {
    const lblPend = document.getElementById('label-pendente');
    const lblPrep = document.getElementById('label-em_preparo');

    if (status === 'pendente') {
        lblPend.className = lblPend.className
            .replace('border-gray-200 bg-gray-50 hover:border-gray-300', 'border-yellow-400 bg-yellow-50');
        lblPrep.className = lblPrep.className
            .replace('border-blue-500 bg-blue-50', 'border-gray-200 bg-gray-50 hover:border-gray-300');
    } else {
        lblPrep.className = lblPrep.className
            .replace('border-gray-200 bg-gray-50 hover:border-gray-300', 'border-blue-500 bg-blue-50');
        lblPend.className = lblPend.className
            .replace('border-yellow-400 bg-yellow-50', 'border-gray-200 bg-gray-50 hover:border-gray-300');
    }
}

// ══════════════════════════════════════════════════════════════
// Busca rápida no cardápio
// ══════════════════════════════════════════════════════════════
function filtrarCardapio(termo) {
    const q = termo.toLowerCase().trim();
    document.querySelectorAll('.produto-item').forEach(el => {
        const nome = el.dataset.nome || '';
        const cat  = el.dataset.cat  || '';
        el.style.display = (!q || nome.includes(q) || cat.includes(q)) ? '' : 'none';
    });
    // Oculta categorias completamente vazias
    document.querySelectorAll('.categoria-bloco').forEach(bloco => {
        const visiveis = bloco.querySelectorAll('.produto-item:not([style*="none"])');
        bloco.style.display = visiveis.length === 0 ? 'none' : '';
    });
}

// ══════════════════════════════════════════════════════════════
// Efeito visual no botão + após adicionar ao carrinho
// ══════════════════════════════════════════════════════════════
function pulsarBotao(produtoId) {
    // Encontra o botão pelo evento de produto
    document.querySelectorAll('.produto-item').forEach(el => {
        const btn = el.querySelector('button[onclick]');
        if (btn && btn.getAttribute('onclick').includes('(' + produtoId + ',')) {
            btn.classList.add('scale-90', 'bg-green-600');
            setTimeout(() => btn.classList.remove('scale-90', 'bg-green-600'), 200);
        }
    });
}

// ══════════════════════════════════════════════════════════════
// Validação e serialização antes do submit
// ══════════════════════════════════════════════════════════════
function prepararSubmit(e) {
    const aviso  = document.getElementById('aviso-form');
    const erros  = [];

    const nome     = document.querySelector('[name="cliente_nome"]').value.trim();
    const telefone = document.querySelector('[name="cliente_telefone"]').value.trim();
    const tipo     = document.querySelector('input[name="tipo_entrega"]:checked')?.value;
    const endereco = document.getElementById('campo-endereco').value.trim();

    if (!nome)     erros.push('Informe o nome do cliente.');
    if (!telefone) erros.push('Informe o telefone/WhatsApp.');
    if (tipo === 'entrega' && !endereco) erros.push('Informe o endereço de entrega.');
    if (carrinho.length === 0) erros.push('Adicione pelo menos um item ao pedido.');

    if (erros.length > 0) {
        aviso.innerHTML = erros.join('<br>');
        aviso.classList.remove('hidden');
        aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
        e.preventDefault();
        return false;
    }

    aviso.classList.add('hidden');

    // Serializa carrinho para o hidden input
    document.getElementById('itens-json').value = JSON.stringify(
        carrinho.map(i => ({
            produto_id: i.produto_id,
            nome:       i.nome,
            preco:      i.preco,
            quantidade: i.quantidade,
        }))
    );

    return true;
}

// ══════════════════════════════════════════════════════════════
// Helpers
// ══════════════════════════════════════════════════════════════
function fmt(v) {
    return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
