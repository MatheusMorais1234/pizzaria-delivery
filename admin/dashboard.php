<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// ── Contadores por status ────────────────────────────────────────
$rows = $pdo->query('SELECT status, COUNT(*) AS n, COALESCE(SUM(total),0) AS soma FROM pedidos GROUP BY status')->fetchAll();
$por_status = [];
foreach ($rows as $r) $por_status[$r['status']] = ['n' => (int)$r['n'], 'soma' => (float)$r['soma']];

$pendentes   = $por_status['pendente']['n']     ?? 0;
$emPreparo   = $por_status['em_preparo']['n']   ?? 0;
$saiuEntrega = $por_status['saiu_entrega']['n'] ?? 0;
$entregues   = $por_status['entregue']['n']     ?? 0;
$ativos      = $pendentes + $emPreparo + $saiuEntrega;
$totalGeral  = $pendentes + $emPreparo + $saiuEntrega + $entregues;

// ── Faturamento hoje vs ontem ────────────────────────────────────
$fatHoje   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status='entregue' AND DATE(created_at)=CURDATE()")->fetchColumn();
$fatOntem  = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status='entregue' AND DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();
$pedHoje   = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$pedOntem  = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at)=CURDATE()-INTERVAL 1 DAY")->fetchColumn();
$ticketMed = $pedHoje > 0 ? (float)$pdo->query("SELECT COALESCE(SUM(total)/COUNT(*),0) FROM pedidos WHERE DATE(created_at)=CURDATE()")->fetchColumn() : 0;

function varPct($hoje, $ontem): string {
    if ($ontem == 0) return $hoje > 0 ? '+100%' : '—';
    $p = round((($hoje - $ontem) / $ontem) * 100);
    return ($p >= 0 ? '+' : '') . $p . '%';
}
function varCor($hoje, $ontem): string {
    if ($ontem == 0) return 'text-gray-400';
    return $hoje >= $ontem ? 'text-green-600' : 'text-red-500';
}

// ── Últimos 7 dias: faturamento e pedidos ───────────────────────
$sete_dias_fat = [];
$sete_dias_ped = [];
$sete_dias_labels = [];
$rows = $pdo->query("
    SELECT DATE(created_at) AS dia,
           COALESCE(SUM(CASE WHEN status='entregue' THEN total ELSE 0 END),0) AS fat,
           COUNT(*) AS ped
    FROM pedidos
    WHERE created_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY dia
    ORDER BY dia
")->fetchAll();

$mapa = [];
foreach ($rows as $r) { $mapa[$r['dia']] = $r; }
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $sete_dias_labels[] = date('d/m', strtotime($d));
    $sete_dias_fat[]    = (float)($mapa[$d]['fat'] ?? 0);
    $sete_dias_ped[]    = (int)($mapa[$d]['ped'] ?? 0);
}

// ── Pedidos por hora hoje ────────────────────────────────────────
$porHora = array_fill(0, 24, 0);
$rows = $pdo->query("SELECT HOUR(created_at) AS h, COUNT(*) AS n FROM pedidos WHERE DATE(created_at)=CURDATE() GROUP BY h")->fetchAll();
foreach ($rows as $r) $porHora[(int)$r['h']] = (int)$r['n'];

// ── Top 6 produtos mais pedidos ──────────────────────────────────
$topProdutos = $pdo->query("
    SELECT pr.nome, SUM(pi.quantidade) AS qtd, SUM(pi.subtotal) AS receita
    FROM pedido_itens pi
    JOIN produtos pr ON pr.id = pi.produto_id
    GROUP BY pi.produto_id
    ORDER BY qtd DESC
    LIMIT 6
")->fetchAll();

// ── Últimos 10 pedidos ───────────────────────────────────────────
$ultimos = $pdo->query('SELECT * FROM pedidos ORDER BY created_at DESC LIMIT 10')->fetchAll();

// ── Taxa de conclusão ────────────────────────────────────────────
$taxaConclusao = $totalGeral > 0 ? round($entregues / $totalGeral * 100) : 0;

$pageTitle = 'Dashboard — Bella Napoli';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- ── Cabeçalho ────────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Dashboard</h1>
        <p class="text-gray-400 text-sm mt-0.5"><?= date('l, d \d\e F \d\e Y') ?></p>
    </div>
    <a href="<?= BASE ?>/admin/pedidos.php"
       class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        Ver pedidos
    </a>
</div>

<!-- ── Linha 1: Cards de métricas ──────────────────────────────── -->
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    <!-- Faturamento hoje -->
    <div class="bg-gradient-to-br from-red-600 to-orange-500 rounded-2xl p-5 shadow-md text-white col-span-2 xl:col-span-1">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-red-100 uppercase tracking-widest">Faturamento hoje</span>
            <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold leading-tight">R$ <?= number_format($fatHoje, 2, ',', '.') ?></p>
        <p class="text-xs text-red-200 mt-1">
            <span class="<?= $fatHoje >= $fatOntem ? 'text-green-300' : 'text-red-300' ?> font-bold"><?= varPct($fatHoje, $fatOntem) ?></span>
            vs. ontem (R$ <?= number_format($fatOntem, 2, ',', '.') ?>)
        </p>
    </div>

    <!-- Pedidos hoje -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Pedidos hoje</span>
            <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-gray-900"><?= $pedHoje ?></p>
        <p class="text-xs mt-1">
            <span class="<?= varCor($pedHoje, $pedOntem) ?> font-bold"><?= varPct($pedHoje, $pedOntem) ?></span>
            <span class="text-gray-400"> vs. ontem (<?= $pedOntem ?>)</span>
        </p>
    </div>

    <!-- Ticket médio -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Ticket médio</span>
            <div class="w-9 h-9 bg-purple-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-gray-900">R$ <?= number_format($ticketMed, 2, ',', '.') ?></p>
        <p class="text-xs text-gray-400 mt-1">média por pedido hoje</p>
    </div>

    <!-- Ativos agora -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Ativos agora</span>
            <div class="w-9 h-9 bg-yellow-50 rounded-xl flex items-center justify-center relative">
                <?php if ($ativos > 0): ?>
                <span class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400 rounded-full animate-ping"></span>
                <?php endif; ?>
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold text-gray-900"><?= $ativos ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= $pendentes ?> pend. · <?= $emPreparo ?> prep. · <?= $saiuEntrega ?> rota</p>
    </div>

</div>

<!-- ── Linha 2: Gráfico 7 dias + Donut status ───────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Área: faturamento + pedidos 7 dias -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col">
        <div class="flex items-center justify-between mb-1">
            <h2 class="font-extrabold text-gray-900">Últimos 7 dias</h2>
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>Faturamento</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span>Pedidos</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mb-3">Faturamento de pedidos entregues</p>
        <div class="flex-1 min-h-[200px]">
            <canvas id="grafico-7dias" style="display:block"></canvas>
        </div>
    </div>

    <!-- Donut: distribuição de status -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col">
        <h2 class="font-extrabold text-gray-900 mb-1">Status dos pedidos</h2>
        <p class="text-xs text-gray-400 mb-4">Todos os pedidos · <?= $totalGeral ?> total</p>
        <div class="flex-1 flex flex-col items-center justify-center">
            <canvas id="grafico-donut" width="160" height="160"></canvas>
            <div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-xs w-full">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400 flex-shrink-0"></span><span class="text-gray-600">Pendente <strong class="text-gray-900"><?= $pendentes ?></strong></span></span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 flex-shrink-0"></span><span class="text-gray-600">Preparo <strong class="text-gray-900"><?= $emPreparo ?></strong></span></span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-orange-500 flex-shrink-0"></span><span class="text-gray-600">Em rota <strong class="text-gray-900"><?= $saiuEntrega ?></strong></span></span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500 flex-shrink-0"></span><span class="text-gray-600">Entregue <strong class="text-gray-900"><?= $entregues ?></strong></span></span>
            </div>
        </div>
        <!-- Taxa de conclusão -->
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex justify-between text-xs mb-1.5">
                <span class="text-gray-500 font-semibold">Taxa de conclusão</span>
                <span class="font-extrabold text-green-600"><?= $taxaConclusao ?>%</span>
            </div>
            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 rounded-full transition-all" style="width:<?= $taxaConclusao ?>%"></div>
            </div>
        </div>
    </div>

</div>

<!-- ── Linha 3: Pedidos por hora + Top produtos ─────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <!-- Barras: pedidos por hora -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="font-extrabold text-gray-900">Pedidos por hora</h2>
                <p class="text-xs text-gray-400 mt-0.5">Hoje · hora atual em vermelho</p>
            </div>
            <span class="text-2xl font-extrabold text-gray-900"><?= $pedHoje ?><span class="text-sm text-gray-400 font-normal ml-1">pedidos</span></span>
        </div>
        <div class="flex-1 min-h-[160px]">
            <canvas id="grafico-horas" style="display:block"></canvas>
        </div>
    </div>

    <!-- Top produtos -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-extrabold text-gray-900 mb-1">Top produtos</h2>
        <p class="text-xs text-gray-400 mb-4">Por quantidade vendida (total)</p>
        <?php if (empty($topProdutos)): ?>
        <p class="text-gray-400 text-sm text-center py-8">Sem dados ainda.</p>
        <?php else:
            $maxQtd = max(array_column($topProdutos, 'qtd'));
            $cores  = ['bg-red-500','bg-orange-500','bg-yellow-500','bg-blue-500','bg-purple-500','bg-green-500'];
        ?>
        <div class="space-y-3.5">
            <?php foreach ($topProdutos as $i => $prod): ?>
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <span class="text-xs font-semibold text-gray-700 truncate max-w-[65%]"><?= htmlspecialchars($prod['nome']) ?></span>
                    <span class="text-xs font-extrabold text-gray-900 flex-shrink-0 ml-2"><?= $prod['qtd'] ?>x</span>
                </div>
                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full <?= $cores[$i % count($cores)] ?> transition-all"
                         style="width:<?= round($prod['qtd'] / $maxQtd * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ── Linha 4: Tabela de últimos pedidos ───────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="font-extrabold text-gray-900">Últimos pedidos</h2>
            <p class="text-xs text-gray-400 mt-0.5">Os 10 pedidos mais recentes</p>
        </div>
        <a href="<?= BASE ?>/admin/pedidos.php"
           class="text-xs text-red-600 hover:text-red-800 font-bold flex items-center gap-1 transition-colors">
            Ver kanban completo
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-6 py-3 text-left font-semibold">#</th>
                    <th class="px-6 py-3 text-left font-semibold">Cliente</th>
                    <th class="px-6 py-3 text-right font-semibold">Total</th>
                    <th class="px-6 py-3 text-center font-semibold">Status</th>
                    <th class="px-6 py-3 text-left font-semibold">Horário</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($ultimos as $p):
                    [$bg, $dot, $label] = match($p['status']) {
                        'pendente'     => ['bg-yellow-100 text-yellow-800', 'bg-yellow-400', 'Pendente'],
                        'em_preparo'   => ['bg-blue-100 text-blue-800',     'bg-blue-500',   'Em preparo'],
                        'saiu_entrega' => ['bg-orange-100 text-orange-800', 'bg-orange-500', 'Em rota'],
                        'entregue'     => ['bg-green-100 text-green-800',   'bg-green-500',  'Entregue'],
                        default        => ['bg-gray-100 text-gray-600',     'bg-gray-400',   $p['status']],
                    };
                    $ago = time() - strtotime($p['created_at']);
                    $agoStr = $ago < 60 ? 'agora' : ($ago < 3600 ? round($ago/60).'min atrás' : date('H:i', strtotime($p['created_at'])));
                ?>
                <tr class="hover:bg-gray-50/70 transition-colors">
                    <td class="px-6 py-3.5">
                        <span class="font-extrabold text-gray-700">#<?= $p['id'] ?></span>
                    </td>
                    <td class="px-6 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600 flex-shrink-0">
                                <?= strtoupper(substr($p['cliente_nome'], 0, 1)) ?>
                            </div>
                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($p['cliente_nome']) ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-3.5 text-right font-extrabold text-gray-900">
                        R$ <?= number_format($p['total'], 2, ',', '.') ?>
                    </td>
                    <td class="px-6 py-3.5 text-center">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $bg ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $dot ?>"></span>
                            <?= $label ?>
                        </span>
                    </td>
                    <td class="px-6 py-3.5 text-gray-400 text-xs"><?= $agoStr ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($ultimos)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center text-gray-400">
                        <div class="text-4xl mb-3">📋</div>
                        <p>Nenhum pedido ainda.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
const LABELS_7D  = <?= json_encode($sete_dias_labels) ?>;
const FAT_7D     = <?= json_encode($sete_dias_fat) ?>;
const PED_7D     = <?= json_encode($sete_dias_ped) ?>;
const HORAS      = <?= json_encode(array_values($porHora)) ?>;
const DONUT_DATA = [<?= $pendentes ?>, <?= $emPreparo ?>, <?= $saiuEntrega ?>, <?= $entregues ?>];
const DONUT_COR  = ['#facc15','#3b82f6','#f97316','#22c55e'];

// ── Utils ─────────────────────────────────────────────────────────
function fmtBRL(v) {
    return 'R$ ' + parseFloat(v).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:2});
}

// ── Gráfico de 7 dias (linha dupla + área) ─────────────────────
(function() {
    const c   = document.getElementById('grafico-7dias');
    if (!c) return;
    const ctx = c.getContext('2d');
    const wrap = c.parentElement;
    c.width  = wrap.offsetWidth;
    c.height = wrap.offsetHeight;
    const W = c.width, H = c.height;
    const pL=52, pR=16, pT=16, pB=32;
    const cW = W - pL - pR, cH = H - pT - pB;
    const n  = LABELS_7D.length;

    const maxF = Math.max(...FAT_7D, 1);
    const maxP = Math.max(...PED_7D, 1);

    function xOf(i) { return pL + (i / (n-1)) * cW; }
    function yF(v)  { return pT + cH - (v / maxF) * cH; }
    function yP(v)  { return pT + cH - (v / maxP) * cH; }

    // Grid
    ctx.strokeStyle = '#f3f4f6'; ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pT + (cH / 4) * i;
        ctx.beginPath(); ctx.moveTo(pL, y); ctx.lineTo(W - pR, y); ctx.stroke();
    }

    // Labels eixo X
    ctx.fillStyle = '#9ca3af'; ctx.font = '10px Inter,sans-serif'; ctx.textAlign = 'center';
    LABELS_7D.forEach((l, i) => ctx.fillText(l, xOf(i), H - 6));

    // Labels eixo Y (faturamento)
    ctx.textAlign = 'right'; ctx.font = '9px Inter,sans-serif';
    [0, 0.5, 1].forEach(f => {
        const v = maxF * f;
        ctx.fillText(v >= 1000 ? (v/1000).toFixed(1)+'k' : v.toFixed(0), pL - 4, pT + cH - f * cH + 4);
    });

    // Área + linha: faturamento (vermelho)
    const gradF = ctx.createLinearGradient(0, pT, 0, pT + cH);
    gradF.addColorStop(0, 'rgba(220,38,38,0.25)');
    gradF.addColorStop(1, 'rgba(220,38,38,0)');

    ctx.beginPath();
    FAT_7D.forEach((v, i) => i === 0 ? ctx.moveTo(xOf(i), yF(v)) : ctx.lineTo(xOf(i), yF(v)));
    ctx.lineTo(xOf(n-1), pT + cH); ctx.lineTo(xOf(0), pT + cH); ctx.closePath();
    ctx.fillStyle = gradF; ctx.fill();

    ctx.beginPath();
    FAT_7D.forEach((v, i) => i === 0 ? ctx.moveTo(xOf(i), yF(v)) : ctx.lineTo(xOf(i), yF(v)));
    ctx.strokeStyle = '#dc2626'; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke();

    // Pontos faturamento
    FAT_7D.forEach((v, i) => {
        ctx.beginPath(); ctx.arc(xOf(i), yF(v), 4, 0, Math.PI*2);
        ctx.fillStyle = '#fff'; ctx.fill();
        ctx.strokeStyle = '#dc2626'; ctx.lineWidth = 2; ctx.stroke();
    });

    // Linha: pedidos (azul, tracejada)
    ctx.setLineDash([4, 3]);
    ctx.beginPath();
    PED_7D.forEach((v, i) => i === 0 ? ctx.moveTo(xOf(i), yP(v)) : ctx.lineTo(xOf(i), yP(v)));
    ctx.strokeStyle = '#60a5fa'; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
    ctx.setLineDash([]);

    // Pontos pedidos
    PED_7D.forEach((v, i) => {
        ctx.beginPath(); ctx.arc(xOf(i), yP(v), 3.5, 0, Math.PI*2);
        ctx.fillStyle = '#fff'; ctx.fill();
        ctx.strokeStyle = '#3b82f6'; ctx.lineWidth = 2; ctx.stroke();
    });

    // Tooltip hover
    let hov = -1;
    const tip = document.createElement('div');
    tip.className = 'fixed z-50 bg-gray-900 text-white text-xs rounded-xl px-3 py-2 pointer-events-none shadow-xl hidden';
    document.body.appendChild(tip);

    c.addEventListener('mousemove', e => {
        const rect = c.getBoundingClientRect();
        const mx   = (e.clientX - rect.left) * (W / rect.width);
        let best = 0, bestDx = Infinity;
        LABELS_7D.forEach((_, i) => { const d = Math.abs(mx - xOf(i)); if (d < bestDx) { bestDx = d; best = i; } });
        if (bestDx > cW / (n * 1.5)) {
            tip.classList.add('hidden'); hov = -1; return;
        }
        hov = best;
        tip.innerHTML = `<p class="font-bold mb-0.5">${LABELS_7D[best]}</p>
            <p class="text-red-300">${fmtBRL(FAT_7D[best])}</p>
            <p class="text-blue-300">${PED_7D[best]} pedidos</p>`;
        tip.classList.remove('hidden');
        tip.style.left = (e.clientX + 12) + 'px';
        tip.style.top  = (e.clientY - 48) + 'px';
    });
    c.addEventListener('mouseleave', () => { tip.classList.add('hidden'); hov = -1; });
})();

// ── Donut ──────────────────────────────────────────────────────
(function() {
    const c   = document.getElementById('grafico-donut');
    if (!c) return;
    const ctx = c.getContext('2d');
    const cx = c.width / 2, cy = c.height / 2, r = 62, ri = 42;
    const total = DONUT_DATA.reduce((a, b) => a + b, 0);
    if (total === 0) {
        ctx.fillStyle = '#e5e7eb';
        ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.beginPath(); ctx.arc(cx, cy, ri, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#9ca3af'; ctx.font = 'bold 11px Inter,sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('Sem dados', cx, cy + 4);
        return;
    }
    let ang = -Math.PI / 2;
    DONUT_DATA.forEach((v, i) => {
        if (v === 0) return;
        const slice = (v / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, ang, ang + slice);
        ctx.closePath();
        ctx.fillStyle = DONUT_COR[i];
        ctx.fill();
        ang += slice;
    });
    // Buraco central
    ctx.beginPath(); ctx.arc(cx, cy, ri, 0, Math.PI * 2);
    ctx.fillStyle = '#fff'; ctx.fill();
    // Texto central
    ctx.fillStyle = '#111827'; ctx.font = 'bold 20px Inter,sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(total, cx, cy + 2);
    ctx.fillStyle = '#9ca3af'; ctx.font = '10px Inter,sans-serif';
    ctx.fillText('pedidos', cx, cy + 16);
})();

// ── Barras por hora ────────────────────────────────────────────
(function() {
    const c   = document.getElementById('grafico-horas');
    if (!c) return;
    const ctx = c.getContext('2d');
    const wrap2 = c.parentElement;
    c.width  = wrap2.offsetWidth;
    c.height = wrap2.offsetHeight;
    const W = c.width, H = c.height;
    const pL=8, pR=8, pT=16, pB=28;
    const barW = (W - pL - pR) / 24;
    const cH   = H - pT - pB;
    const max  = Math.max(...HORAS, 1);
    const hora = new Date().getHours();

    HORAS.forEach((v, h) => {
        const x  = pL + h * barW;
        const bH = v > 0 ? Math.max(4, (v / max) * cH) : 0;
        const y  = pT + cH - bH;

        // Gradiente por hora atual
        const grad = ctx.createLinearGradient(0, y, 0, y + bH);
        if (h === hora) {
            grad.addColorStop(0, '#dc2626'); grad.addColorStop(1, '#f87171');
        } else {
            grad.addColorStop(0, '#fca5a5'); grad.addColorStop(1, '#fecaca');
        }
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.roundRect(x + 2, y, barW - 4, bH || 2, [3, 3, 0, 0]);
        ctx.fill();

        // Valor no topo
        if (v > 0) {
            ctx.fillStyle = h === hora ? '#dc2626' : '#6b7280';
            ctx.font = h === hora ? 'bold 9px Inter,sans-serif' : '9px Inter,sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(v, x + barW / 2, y - 3);
        }
    });

    // Labels de hora
    ctx.fillStyle = '#9ca3af'; ctx.font = '9px Inter,sans-serif'; ctx.textAlign = 'center';
    [0, 3, 6, 9, 12, 15, 18, 21].forEach(h => {
        ctx.fillStyle = h === hora ? '#dc2626' : '#9ca3af';
        ctx.font      = h === hora ? 'bold 9px Inter,sans-serif' : '9px Inter,sans-serif';
        ctx.fillText(String(h).padStart(2,'0')+'h', pL + h * barW + barW/2, H - 5);
    });
})();
</script>
