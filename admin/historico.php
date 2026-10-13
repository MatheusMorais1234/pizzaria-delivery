<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// ── Helpers ──────────────────────────────────────────────────────
function safeTrim($v) { return isset($v) ? trim($v) : ''; }

// ── Parâmetros de filtro ─────────────────────────────────────────
$periodo    = safeTrim($_GET['periodo']    ?? 'hoje');
$status_f   = safeTrim($_GET['status']     ?? '');
$busca      = safeTrim($_GET['busca']      ?? '');
$data_ini   = safeTrim($_GET['data_ini']   ?? '');
$data_fim   = safeTrim($_GET['data_fim']   ?? '');
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 20;
$exportar   = isset($_GET['export']) && $_GET['export'] === 'csv';

// ── Calcula datas conforme período ──────────────────────────────
$hoje = date('Y-m-d');
switch ($periodo) {
    case 'hoje':
        $data_ini_sql = $hoje;
        $data_fim_sql = $hoje;
        break;
    case '7dias':
        $data_ini_sql = date('Y-m-d', strtotime('-6 days'));
        $data_fim_sql = $hoje;
        break;
    case '30dias':
        $data_ini_sql = date('Y-m-d', strtotime('-29 days'));
        $data_fim_sql = $hoje;
        break;
    case 'custom':
        $data_ini_sql = $data_ini ?: $hoje;
        $data_fim_sql = $data_fim ?: $hoje;
        break;
    default:
        $data_ini_sql = $hoje;
        $data_fim_sql = $hoje;
        break;
}

// ── Monta WHERE ──────────────────────────────────────────────────
$where  = ['DATE(p.created_at) BETWEEN :data_ini AND :data_fim'];
$params = [':data_ini' => $data_ini_sql, ':data_fim' => $data_fim_sql];

$status_validos = ['pendente', 'em_preparo', 'saiu_entrega', 'entregue', 'cancelado'];
if ($status_f && in_array($status_f, $status_validos, true)) {
    $where[]           = 'p.status = :status';
    $params[':status'] = $status_f;
}

if ($busca !== '') {
    $where[]          = '(p.cliente_nome LIKE :busca OR p.id LIKE :busca2)';
    $params[':busca']  = '%' . $busca . '%';
    $params[':busca2'] = '%' . $busca . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// ── Summary (cartões) — sem paginação ────────────────────────────
$sqlSum = "SELECT
    COUNT(*) AS total_pedidos,
    COALESCE(SUM(CASE WHEN p.status='entregue' THEN p.total ELSE 0 END), 0) AS receita,
    COALESCE(SUM(CASE WHEN p.status='entregue' THEN p.total ELSE 0 END) / NULLIF(SUM(CASE WHEN p.status='entregue' THEN 1 ELSE 0 END),0), 0) AS ticket_medio,
    SUM(CASE WHEN p.status='cancelado' THEN 1 ELSE 0 END) AS cancelamentos
FROM pedidos p $whereSql";
$stmtSum = $pdo->prepare($sqlSum);
$stmtSum->execute($params);
$summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

// ── Contagem total para paginação ────────────────────────────────
$sqlCount = "SELECT COUNT(*) FROM pedidos p $whereSql";
$stmtCnt  = $pdo->prepare($sqlCount);
$stmtCnt->execute($params);
$total_registros = (int)$stmtCnt->fetchColumn();
$total_paginas   = max(1, (int)ceil($total_registros / $por_pagina));
$pagina          = min($pagina, $total_paginas);
$offset          = ($pagina - 1) * $por_pagina;

// ── Query principal ──────────────────────────────────────────────
$sqlPedidos = "SELECT p.id, p.cliente_nome, p.cliente_telefone, p.endereco,
    p.observacao, p.total, p.status, p.tipo_entrega,
    p.cupom_codigo, p.desconto_cupom, p.motivo_cancelamento, p.created_at
FROM pedidos p $whereSql
ORDER BY p.created_at DESC
LIMIT :limit OFFSET :offset";

$stmtP = $pdo->prepare($sqlPedidos);
foreach ($params as $k => $v) $stmtP->bindValue($k, $v);
$stmtP->bindValue(':limit',  $por_pagina, PDO::PARAM_INT);
$stmtP->bindValue(':offset', $offset,     PDO::PARAM_INT);
$stmtP->execute();
$pedidos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// ── Busca itens dos pedidos da página atual ──────────────────────
$itensPorPedido = [];
if (!empty($pedidos)) {
    $ids         = array_column($pedidos, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlItens = "SELECT pi.pedido_id, pi.quantidade, pi.preco_unitario, pi.subtotal,
        pi.ingredientes_removidos, pi.adicionais_json, pr.nome
    FROM pedido_itens pi
    LEFT JOIN produtos pr ON pr.id = pi.produto_id
    WHERE pi.pedido_id IN ($placeholders)
    ORDER BY pi.pedido_id, pi.id";
    $stmtI = $pdo->prepare($sqlItens);
    $stmtI->execute($ids);
    foreach ($stmtI->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $item['ingredientes_removidos'] = $item['ingredientes_removidos']
            ? json_decode($item['ingredientes_removidos'], true) : [];
        $item['adicionais_json'] = $item['adicionais_json']
            ? json_decode($item['adicionais_json'], true) : [];
        $itensPorPedido[$item['pedido_id']][] = $item;
    }
}

// ── Exportar CSV ─────────────────────────────────────────────────
if ($exportar) {
    // Busca todos (sem paginação) para o CSV
    $sqlAll = "SELECT p.id, p.cliente_nome, p.cliente_telefone, p.endereco,
        p.observacao, p.total, p.status, p.tipo_entrega,
        p.cupom_codigo, p.desconto_cupom, p.motivo_cancelamento, p.created_at
    FROM pedidos p $whereSql ORDER BY p.created_at DESC";
    $stmtAll = $pdo->prepare($sqlAll);
    $stmtAll->execute($params);
    $todos = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'historico_pedidos_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['#', 'Cliente', 'Telefone', 'Endereço', 'Status', 'Tipo', 'Total', 'Cupom', 'Desconto', 'Data/Hora'], ';');
    foreach ($todos as $p) {
        fputcsv($out, [
            $p['id'],
            $p['cliente_nome'],
            $p['cliente_telefone'],
            $p['endereco'],
            $p['status'],
            $p['tipo_entrega'],
            number_format((float)$p['total'], 2, ',', '.'),
            $p['cupom_codigo'] ?? '',
            $p['desconto_cupom'] ? number_format((float)$p['desconto_cupom'], 2, ',', '.') : '',
            $p['created_at'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Labels/cores para status ─────────────────────────────────────
function statusBadge($s) {
    $map = [
        'pendente'     => ['label' => 'Pendente',      'cls' => 'bg-yellow-100 text-yellow-800'],
        'em_preparo'   => ['label' => 'Em preparo',    'cls' => 'bg-blue-100 text-blue-800'],
        'saiu_entrega' => ['label' => 'Saiu p/ entrega','cls' => 'bg-orange-100 text-orange-800'],
        'entregue'     => ['label' => 'Entregue',      'cls' => 'bg-green-100 text-green-800'],
        'cancelado'    => ['label' => 'Cancelado',     'cls' => 'bg-gray-100 text-gray-600'],
    ];
    $d = $map[$s] ?? ['label' => $s, 'cls' => 'bg-gray-100 text-gray-500'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' . $d['cls'] . '">' . htmlspecialchars($d['label']) . '</span>';
}

function tipoBadge($t) {
    if ($t === 'retirada') {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Retirada</span>';
    }
    return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
        Entrega</span>';
}

// Prepara dados JSON dos itens para o modal JS
$pedidosJson = [];
foreach ($pedidos as $p) {
    $itens = $itensPorPedido[$p['id']] ?? [];
    $pedidosJson[$p['id']] = [
        'id'                   => $p['id'],
        'cliente_nome'         => $p['cliente_nome'],
        'cliente_telefone'     => $p['cliente_telefone'],
        'endereco'             => $p['endereco'],
        'observacao'           => $p['observacao'],
        'total'                => $p['total'],
        'status'               => $p['status'],
        'tipo_entrega'         => $p['tipo_entrega'],
        'cupom_codigo'         => $p['cupom_codigo'],
        'desconto_cupom'       => $p['desconto_cupom'],
        'motivo_cancelamento'  => $p['motivo_cancelamento'],
        'created_at'           => $p['created_at'],
        'itens'                => $itens,
    ];
}

// Monta URL base para paginação (preserva filtros)
function buildUrl($params, $pagina) {
    $p = $params;
    $p['pagina'] = $pagina;
    return '?' . http_build_query($p);
}

$urlParams = array_filter([
    'periodo'  => $periodo,
    'status'   => $status_f,
    'busca'    => $busca,
    'data_ini' => ($periodo === 'custom' ? $data_ini : ''),
    'data_fim' => ($periodo === 'custom' ? $data_fim : ''),
]);

$pageTitle = 'Histórico de Pedidos — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- ══ CABEÇALHO ════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-extrabold text-gray-900">Histórico de Pedidos</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            <?= $total_registros ?> registro<?= $total_registros != 1 ? 's' : '' ?> encontrado<?= $total_registros != 1 ? 's' : '' ?>
        </p>
    </div>
    <a href="<?= htmlspecialchars('?' . http_build_query(array_merge($urlParams, ['export' => 'csv']))) ?>"
       class="inline-flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Exportar CSV
    </a>
</div>

<!-- ══ CARTÕES DE RESUMO ════════════════════════════════════════ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <!-- Total de pedidos -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Total de Pedidos</p>
            <div class="w-9 h-9 bg-gray-100 rounded-xl flex items-center justify-center">
                <svg class="w-4.5 h-4.5 text-gray-500 w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-gray-900"><?= number_format((int)$summary['total_pedidos']) ?></p>
    </div>

    <!-- Receita (entregues) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Receita</p>
            <div class="w-9 h-9 bg-green-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-green-600">R$ <?= number_format((float)$summary['receita'], 2, ',', '.') ?></p>
        <p class="text-xs text-gray-400 mt-1">Somente entregues</p>
    </div>

    <!-- Ticket médio -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Ticket Médio</p>
            <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-blue-600">R$ <?= number_format((float)$summary['ticket_medio'], 2, ',', '.') ?></p>
    </div>

    <!-- Cancelamentos -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Cancelamentos</p>
            <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-red-600"><?= (int)$summary['cancelamentos'] ?></p>
    </div>

</div>

<!-- ══ FILTROS ══════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
    <form method="GET" action="" class="flex flex-wrap gap-3 items-end">

        <!-- Período rápido -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-widest">Período</label>
            <div class="flex gap-1">
                <?php
                $periodos = ['hoje' => 'Hoje', '7dias' => '7 dias', '30dias' => '30 dias', 'custom' => 'Personalizado'];
                foreach ($periodos as $val => $label):
                    $ativo = $periodo === $val ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200';
                ?>
                <button type="submit" name="periodo" value="<?= $val ?>"
                        class="<?= $ativo ?> text-xs font-semibold px-3 py-2 rounded-lg transition-colors whitespace-nowrap">
                    <?= $label ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Datas personalizadas -->
        <div id="custom-dates" class="<?= $periodo === 'custom' ? 'flex' : 'hidden' ?> gap-2 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">De</label>
                <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>"
                       class="border border-gray-200 rounded-xl px-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Até</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>"
                       class="border border-gray-200 rounded-xl px-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
        </div>

        <!-- Separador -->
        <div class="h-10 w-px bg-gray-200 self-end hidden sm:block"></div>

        <!-- Status -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-widest">Status</label>
            <select name="status"
                    class="border border-gray-200 rounded-xl px-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 pr-8">
                <option value=""          <?= $status_f === ''             ? 'selected' : '' ?>>Todos</option>
                <option value="pendente"     <?= $status_f === 'pendente'     ? 'selected' : '' ?>>Pendente</option>
                <option value="em_preparo"   <?= $status_f === 'em_preparo'   ? 'selected' : '' ?>>Em preparo</option>
                <option value="saiu_entrega" <?= $status_f === 'saiu_entrega' ? 'selected' : '' ?>>Saiu p/ entrega</option>
                <option value="entregue"     <?= $status_f === 'entregue'     ? 'selected' : '' ?>>Entregue</option>
                <option value="cancelado"    <?= $status_f === 'cancelado'    ? 'selected' : '' ?>>Cancelado</option>
            </select>
        </div>

        <!-- Busca -->
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-widest">Busca</label>
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Nome ou #número…"
                       class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                              focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent">
            </div>
        </div>

        <!-- Preserva período no submit do select/busca -->
        <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
        <?php if ($periodo === 'custom'): ?>
        <!-- as datas são campos visíveis acima -->
        <?php endif; ?>

        <!-- Botões -->
        <div class="flex gap-2 self-end">
            <button type="submit"
                    class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                Filtrar
            </button>
            <a href="?periodo=hoje"
               class="bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                Limpar
            </a>
        </div>

    </form>
</div>

<!-- ══ TABELA ═══════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">

    <?php if (empty($pedidos)): ?>
    <div class="text-center py-20">
        <svg class="w-12 h-12 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="text-gray-400 font-semibold">Nenhum pedido encontrado</p>
        <p class="text-gray-300 text-sm mt-1">Tente ajustar os filtros acima.</p>
    </div>
    <?php else: ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-5 py-3.5">#</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5">Cliente</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5 hidden md:table-cell">Telefone</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5 hidden lg:table-cell">Itens</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5">Total</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5 hidden sm:table-cell">Tipo</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5">Status</th>
                    <th class="text-left text-xs font-bold text-gray-500 uppercase tracking-widest px-4 py-3.5 hidden sm:table-cell">Data/Hora</th>
                    <th class="px-4 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($pedidos as $p):
                $itens       = $itensPorPedido[$p['id']] ?? [];
                $resumoItens = array_map(fn($i) => $i['quantidade'] . 'x ' . $i['nome'], $itens);
                $dtObj       = new DateTime($p['created_at']);
                $dtFormatado = $dtObj->format('d/m/Y H:i');
            ?>
            <tr class="hover:bg-gray-50 transition-colors cursor-pointer group"
                onclick="abrirModal(<?= $p['id'] ?>)">
                <td class="px-5 py-3.5">
                    <span class="font-bold text-gray-400 text-xs">#<?= $p['id'] ?></span>
                </td>
                <td class="px-4 py-3.5">
                    <p class="font-semibold text-gray-900 truncate max-w-[140px]"><?= htmlspecialchars($p['cliente_nome']) ?></p>
                </td>
                <td class="px-4 py-3.5 hidden md:table-cell">
                    <span class="text-gray-500"><?= htmlspecialchars($p['cliente_telefone']) ?></span>
                </td>
                <td class="px-4 py-3.5 hidden lg:table-cell">
                    <p class="text-gray-500 truncate max-w-[200px]" title="<?= htmlspecialchars(implode(', ', $resumoItens)) ?>">
                        <?= htmlspecialchars(implode(', ', $resumoItens) ?: '—') ?>
                    </p>
                </td>
                <td class="px-4 py-3.5">
                    <span class="font-extrabold text-gray-900">R$ <?= number_format((float)$p['total'], 2, ',', '.') ?></span>
                </td>
                <td class="px-4 py-3.5 hidden sm:table-cell">
                    <?= tipoBadge($p['tipo_entrega']) ?>
                </td>
                <td class="px-4 py-3.5">
                    <?= statusBadge($p['status']) ?>
                </td>
                <td class="px-4 py-3.5 hidden sm:table-cell">
                    <span class="text-gray-500 text-xs whitespace-nowrap"><?= $dtFormatado ?></span>
                </td>
                <td class="px-4 py-3.5">
                    <button class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100"
                            onclick="event.stopPropagation(); abrirModal(<?= $p['id'] ?>)"
                            title="Ver detalhes">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

<!-- ══ PAGINAÇÃO ════════════════════════════════════════════════ -->
<?php if ($total_paginas > 1): ?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <p class="text-sm text-gray-500">
        Página <span class="font-bold text-gray-800"><?= $pagina ?></span> de
        <span class="font-bold text-gray-800"><?= $total_paginas ?></span>
        &nbsp;·&nbsp; <?= $total_registros ?> registro<?= $total_registros != 1 ? 's' : '' ?>
    </p>

    <div class="flex items-center gap-1">
        <!-- Primeira -->
        <?php if ($pagina > 1): ?>
        <a href="<?= htmlspecialchars(buildUrl($urlParams, 1)) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors text-xs font-bold" title="Primeira">
            «
        </a>
        <a href="<?= htmlspecialchars(buildUrl($urlParams, $pagina - 1)) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors text-xs font-bold" title="Anterior">
            ‹
        </a>
        <?php else: ?>
        <span class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-100 bg-gray-50 text-gray-300 text-xs font-bold">«</span>
        <span class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-100 bg-gray-50 text-gray-300 text-xs font-bold">‹</span>
        <?php endif; ?>

        <!-- Números de página -->
        <?php
        $inicio_range = max(1, $pagina - 2);
        $fim_range    = min($total_paginas, $pagina + 2);
        for ($i = $inicio_range; $i <= $fim_range; $i++):
            $ativo = $i === $pagina;
        ?>
        <a href="<?= htmlspecialchars(buildUrl($urlParams, $i)) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl border text-xs font-bold transition-colors
                  <?= $ativo ? 'bg-red-600 border-red-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>

        <!-- Próxima / Última -->
        <?php if ($pagina < $total_paginas): ?>
        <a href="<?= htmlspecialchars(buildUrl($urlParams, $pagina + 1)) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors text-xs font-bold" title="Próxima">
            ›
        </a>
        <a href="<?= htmlspecialchars(buildUrl($urlParams, $total_paginas)) ?>"
           class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors text-xs font-bold" title="Última">
            »
        </a>
        <?php else: ?>
        <span class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-100 bg-gray-50 text-gray-300 text-xs font-bold">›</span>
        <span class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-100 bg-gray-50 text-gray-300 text-xs font-bold">»</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL DE DETALHE ══════════════════════════════════════════ -->
<div id="modal-overlay"
     class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4"
     onclick="fecharModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col"
         onclick="event.stopPropagation()">

        <!-- Header -->
        <div id="modal-header"
             class="flex items-center justify-between px-6 py-5 flex-shrink-0 rounded-t-2xl bg-gray-900">
            <div>
                <p class="text-white/60 text-xs font-semibold uppercase tracking-widest mb-0.5">Pedido</p>
                <h2 class="font-extrabold text-white text-2xl" id="modal-titulo">#—</h2>
            </div>
            <div class="flex items-center gap-3">
                <span id="modal-badge" class="px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 text-white"></span>
                <button onclick="fecharModal()"
                        class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Body com scroll -->
        <div class="flex-1 overflow-y-auto" id="modal-corpo"></div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50 rounded-b-2xl"
             id="modal-footer"></div>
    </div>
</div>

<!-- Dados dos pedidos para o JS -->
<script>
const PEDIDOS_DATA = <?= json_encode($pedidosJson, JSON_UNESCAPED_UNICODE) ?>;

// ── Config de cores/labels de status ─────────────────────────────
const STATUS_CFG = {
    pendente:     { label: 'Pendente',       headerCls: 'bg-yellow-500', badgeCls: 'bg-yellow-100 text-yellow-800' },
    em_preparo:   { label: 'Em preparo',     headerCls: 'bg-blue-600',   badgeCls: 'bg-blue-100 text-blue-800' },
    saiu_entrega: { label: 'Saiu p/ entrega',headerCls: 'bg-orange-500', badgeCls: 'bg-orange-100 text-orange-800' },
    entregue:     { label: 'Entregue',       headerCls: 'bg-green-600',  badgeCls: 'bg-green-100 text-green-800' },
    cancelado:    { label: 'Cancelado',      headerCls: 'bg-gray-600',   badgeCls: 'bg-gray-100 text-gray-600' },
};

// ── Helpers ───────────────────────────────────────────────────────
function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtBRL(v) {
    return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDt(str) {
    const d = new Date(str.replace(' ', 'T'));
    return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

// ── Abre modal ────────────────────────────────────────────────────
function abrirModal(id) {
    const p = PEDIDOS_DATA[id];
    if (!p) return;

    const cfg = STATUS_CFG[p.status] || { label: p.status, headerCls: 'bg-gray-700', badgeCls: 'bg-gray-100 text-gray-600' };

    // Header
    const header = document.getElementById('modal-header');
    header.className = 'flex items-center justify-between px-6 py-5 flex-shrink-0 rounded-t-2xl ' + cfg.headerCls;
    document.getElementById('modal-titulo').textContent = '#' + p.id;
    const badge = document.getElementById('modal-badge');
    badge.textContent = cfg.label;

    // Itens HTML
    const itensHtml = (p.itens && p.itens.length)
        ? p.itens.map(i => {
            const semIng = (i.ingredientes_removidos && i.ingredientes_removidos.length)
                ? `<p class="text-xs text-red-500 font-semibold mt-1">sem ${i.ingredientes_removidos.map(esc).join(', ')}</p>`
                : '';
            const comAdd = (i.adicionais_json && i.adicionais_json.length)
                ? `<div class="flex flex-wrap gap-1 mt-1">${i.adicionais_json.map(a =>
                    `<span class="text-xs bg-green-100 text-green-700 font-semibold px-2 py-0.5 rounded-full">+ ${esc(a.nome || a.name || '')}${a.preco ? ' +R$ ' + fmtBRL(a.preco) : ''}</span>`
                  ).join('')}</div>`
                : '';
            return `<div class="py-3 border-b border-gray-100 last:border-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold text-gray-600 flex-shrink-0 mt-0.5">${esc(i.quantidade)}</span>
                        <div class="min-w-0">
                            <span class="font-semibold text-gray-900 text-sm">${esc(i.nome || '—')}</span>
                            ${semIng}${comAdd}
                        </div>
                    </div>
                    <span class="font-bold text-gray-800 text-sm flex-shrink-0">R$ ${fmtBRL(i.subtotal)}</span>
                </div>
            </div>`;
        }).join('')
        : '<p class="text-gray-400 text-sm py-2">Sem itens registrados</p>';

    // Cupom
    const cupomHtml = p.cupom_codigo
        ? `<div class="flex items-center justify-between text-sm mt-2 pt-2 border-t border-gray-100">
               <span class="text-gray-500">Cupom <span class="font-mono font-bold text-gray-700">${esc(p.cupom_codigo)}</span></span>
               <span class="text-green-600 font-semibold">- R$ ${fmtBRL(p.desconto_cupom || 0)}</span>
           </div>`
        : '';

    // Motivo cancelamento
    const motivoHtml = p.motivo_cancelamento
        ? `<div class="mt-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
               <p class="text-xs font-bold text-red-700 mb-0.5">Motivo do cancelamento</p>
               <p class="text-red-800 text-sm">${esc(p.motivo_cancelamento)}</p>
           </div>`
        : '';

    // Body
    document.getElementById('modal-corpo').innerHTML = `
        <!-- Cliente -->
        <div class="px-6 pt-5 pb-4 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Cliente</p>
            <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white font-extrabold text-base flex-shrink-0">
                    ${esc(p.cliente_nome).charAt(0).toUpperCase()}
                </div>
                <div>
                    <p class="font-extrabold text-gray-900 text-base leading-tight">${esc(p.cliente_nome)}</p>
                    <p class="text-gray-500 text-sm">${esc(p.cliente_telefone)}</p>
                </div>
                <span class="ml-auto text-gray-400 text-xs whitespace-nowrap">${fmtDt(p.created_at)}</span>
            </div>
        </div>

        <!-- Entrega / Retirada -->
        <div class="px-6 py-4 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                ${p.tipo_entrega === 'retirada' ? 'Retirada na loja' : 'Endereço de entrega'}
            </p>
            ${p.tipo_entrega === 'retirada'
                ? `<span class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-lg">
                       <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                       Cliente retira na loja
                   </span>`
                : `<p class="text-gray-900 font-medium text-sm leading-relaxed">${esc(p.endereco || '—')}</p>`
            }
            ${p.observacao
                ? `<div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-xl px-3 py-2">
                       <p class="text-xs font-bold text-yellow-700 mb-0.5">Observação</p>
                       <p class="text-yellow-900 text-sm italic">${esc(p.observacao)}</p>
                   </div>`
                : ''
            }
            ${motivoHtml}
        </div>

        <!-- Itens -->
        <div class="px-6 py-4 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Itens do pedido</p>
            ${itensHtml}
        </div>

        <!-- Total -->
        <div class="px-6 py-4">
            ${cupomHtml}
            <div class="flex items-center justify-between mt-2">
                <p class="font-bold text-gray-700 text-base">Total do pedido</p>
                <p class="font-extrabold text-red-600 text-2xl">R$ ${fmtBRL(p.total)}</p>
            </div>
        </div>
    `;

    // Footer
    document.getElementById('modal-footer').innerHTML =
        `<button onclick="fecharModal()"
                 class="w-full border border-gray-200 text-gray-600 font-semibold py-2.5 rounded-xl hover:bg-gray-50 transition-colors text-sm">
             Fechar
         </button>`;

    const overlay = document.getElementById('modal-overlay');
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function fecharModal() {
    const o = document.getElementById('modal-overlay');
    o.classList.add('hidden');
    o.classList.remove('flex');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

// ── Toggle datas personalizadas ───────────────────────────────────
document.querySelectorAll('button[name="periodo"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const val = this.value;
        const div = document.getElementById('custom-dates');
        if (val === 'custom') {
            div.classList.remove('hidden');
            div.classList.add('flex');
        } else {
            div.classList.add('hidden');
            div.classList.remove('flex');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
