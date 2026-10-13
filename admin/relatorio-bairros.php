<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// Período
$periodos  = ['hoje' => 'Hoje', 'semana' => 'Esta semana', 'mes' => 'Este mês', 'tudo' => 'Todo o período'];
$periodo   = $_GET['periodo'] ?? 'mes';
if (!array_key_exists($periodo, $periodos)) $periodo = 'mes';

switch ($periodo) {
    case 'hoje':
        $filtroData = "AND DATE(p.created_at) = CURDATE()";
        break;
    case 'semana':
        $filtroData = "AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
        break;
    case 'mes':
        $filtroData = "AND YEAR(p.created_at) = YEAR(CURDATE()) AND MONTH(p.created_at) = MONTH(CURDATE())";
        break;
    default:
        $filtroData = "";
}

// Dados por bairro
$sql = "
    SELECT
        bairro,
        COUNT(*)                          AS total_pedidos,
        SUM(total)                        AS receita_total,
        AVG(total)                        AS ticket_medio,
        AVG(taxa_entrega)                 AS taxa_media
    FROM pedidos p
    WHERE status != 'cancelado'
      AND bairro IS NOT NULL
      AND bairro != ''
      $filtroData
    GROUP BY bairro
    ORDER BY receita_total DESC
";
$bairros = $pdo->query($sql)->fetchAll();

// Totais gerais
$sqlTotal = "
    SELECT
        COUNT(*)       AS total_pedidos,
        SUM(total)     AS receita_total,
        AVG(total)     AS ticket_medio,
        AVG(taxa_entrega) AS taxa_media
    FROM pedidos p
    WHERE status != 'cancelado'
      AND bairro IS NOT NULL
      AND bairro != ''
      $filtroData
";
$totais = $pdo->query($sqlTotal)->fetch();

$pageTitle = 'Relatório por Bairro — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Relatório por Bairro</h1>
        <p class="text-gray-400 text-sm mt-0.5">Receita e pedidos agrupados por bairro de entrega</p>
    </div>

    <!-- Filtro de período -->
    <div class="flex gap-2 flex-wrap">
        <?php foreach ($periodos as $key => $label): ?>
        <a href="?periodo=<?= $key ?>"
           class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors
                  <?= $periodo === $key
                    ? 'bg-red-600 text-white shadow-sm'
                    : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($bairros)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
    <div class="text-5xl mb-4">📍</div>
    <p class="text-gray-500 font-medium">Nenhum pedido com bairro registrado no período selecionado.</p>
</div>
<?php else: ?>

<!-- Cards de resumo -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Bairros atendidos</p>
        <p class="text-3xl font-extrabold text-gray-900"><?= count($bairros) ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Total de pedidos</p>
        <p class="text-3xl font-extrabold text-gray-900"><?= number_format((int)$totais['total_pedidos']) ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Receita total</p>
        <p class="text-3xl font-extrabold text-red-600">R$ <?= number_format((float)$totais['receita_total'], 2, ',', '.') ?></p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Ticket médio</p>
        <p class="text-3xl font-extrabold text-gray-900">R$ <?= number_format((float)$totais['ticket_medio'], 2, ',', '.') ?></p>
    </div>
</div>

<!-- Tabela -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-extrabold text-gray-900">Detalhamento por bairro</h2>
        <p class="text-xs text-gray-400 mt-0.5">Ordenado por receita total (maior primeiro) · <?= $periodos[$periodo] ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                    <th class="text-left px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">#</th>
                    <th class="text-left px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Bairro</th>
                    <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Pedidos</th>
                    <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Receita total</th>
                    <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Ticket médio</th>
                    <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Taxa média</th>
                    <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">% receita</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php
                $receitaMax   = (float)($bairros[0]['receita_total'] ?? 1);
                $receitaTotal = (float)($totais['receita_total'] ?: 1);
                foreach ($bairros as $i => $b):
                    $pct = $receitaTotal > 0 ? round((float)$b['receita_total'] / $receitaTotal * 100, 1) : 0;
                    $barWidth = $receitaMax > 0 ? round((float)$b['receita_total'] / $receitaMax * 100) : 0;
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-gray-400 font-medium"><?= $i + 1 ?></td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($b['bairro']) ?></div>
                        <div class="mt-1.5 h-1.5 bg-gray-100 rounded-full w-32">
                            <div class="h-1.5 bg-red-500 rounded-full" style="width:<?= $barWidth ?>%"></div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold text-gray-700"><?= number_format((int)$b['total_pedidos']) ?></td>
                    <td class="px-6 py-4 text-right font-bold text-gray-900">R$ <?= number_format((float)$b['receita_total'], 2, ',', '.') ?></td>
                    <td class="px-6 py-4 text-right text-gray-600">R$ <?= number_format((float)$b['ticket_medio'], 2, ',', '.') ?></td>
                    <td class="px-6 py-4 text-right text-gray-600">
                        <?= (float)$b['taxa_media'] > 0
                            ? 'R$ ' . number_format((float)$b['taxa_media'], 2, ',', '.')
                            : '<span class="text-green-600 font-semibold">grátis</span>' ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                                     <?= $pct >= 20 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' ?>">
                            <?= $pct ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <!-- Linha de totais -->
            <tfoot>
                <tr class="bg-gray-50 border-t-2 border-gray-200">
                    <td class="px-6 py-4" colspan="2">
                        <span class="font-extrabold text-gray-900 uppercase text-xs tracking-widest">Total</span>
                    </td>
                    <td class="px-6 py-4 text-right font-extrabold text-gray-900"><?= number_format((int)$totais['total_pedidos']) ?></td>
                    <td class="px-6 py-4 text-right font-extrabold text-red-600">R$ <?= number_format((float)$totais['receita_total'], 2, ',', '.') ?></td>
                    <td class="px-6 py-4 text-right font-extrabold text-gray-700">R$ <?= number_format((float)$totais['ticket_medio'], 2, ',', '.') ?></td>
                    <td class="px-6 py-4 text-right font-extrabold text-gray-700">
                        R$ <?= number_format((float)$totais['taxa_media'], 2, ',', '.') ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-800 text-white">100%</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
