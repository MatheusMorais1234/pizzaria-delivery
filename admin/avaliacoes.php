<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// Filtro por nota
$filtroNota = filter_input(INPUT_GET, 'nota', FILTER_VALIDATE_INT);
if ($filtroNota && ($filtroNota < 1 || $filtroNota > 5)) $filtroNota = null;

// Paginação
$porPagina = 20;
$pagina    = max(1, (int)($_GET['pagina'] ?? 1));
$offset    = ($pagina - 1) * $porPagina;

// Estatísticas gerais
$stats = $pdo->query('SELECT COUNT(*) AS total, AVG(nota) AS media FROM avaliacoes')->fetch();
$totalAvaliacoes = (int) $stats['total'];
$mediaGeral      = $totalAvaliacoes > 0 ? round((float) $stats['media'], 1) : 0;

// Distribuição por estrela
$dist = [];
for ($i = 5; $i >= 1; $i--) $dist[$i] = 0;
$rows = $pdo->query('SELECT nota, COUNT(*) AS cnt FROM avaliacoes GROUP BY nota')->fetchAll();
foreach ($rows as $r) $dist[(int)$r['nota']] = (int)$r['cnt'];

// Contagem filtrada
$whereClause = $filtroNota ? 'WHERE a.nota = ' . (int)$filtroNota : '';
$totalFiltrado = (int) $pdo->query("SELECT COUNT(*) FROM avaliacoes a $whereClause")->fetchColumn();
$totalPaginas  = max(1, (int) ceil($totalFiltrado / $porPagina));
if ($pagina > $totalPaginas) $pagina = $totalPaginas;
$offset = ($pagina - 1) * $porPagina;

// Busca avaliações com dados do pedido
$stmt = $pdo->prepare("
    SELECT a.id, a.pedido_id, a.nota, a.comentario, a.created_at,
           p.cliente_nome, p.total
    FROM avaliacoes a
    LEFT JOIN pedidos p ON p.id = a.pedido_id
    $whereClause
    ORDER BY a.created_at DESC
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute();
$avaliacoes = $stmt->fetchAll();

$pageTitle = 'Avaliações — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- Cabeçalho -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Avaliações</h1>
        <p class="text-gray-400 text-sm mt-0.5"><?= $totalAvaliacoes ?> avaliação<?= $totalAvaliacoes !== 1 ? 'ões' : '' ?> recebida<?= $totalAvaliacoes !== 1 ? 's' : '' ?></p>
    </div>
</div>

<!-- Cards de estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

    <!-- Média geral -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center gap-6">
        <div class="text-center flex-shrink-0">
            <p class="text-6xl font-black text-gray-900"><?= $mediaGeral > 0 ? number_format($mediaGeral, 1) : '—' ?></p>
            <div class="flex items-center justify-center gap-0.5 mt-1">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="w-5 h-5 <?= $i <= round($mediaGeral) ? 'text-yellow-400' : 'text-gray-200' ?>"
                     fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                <?php endfor; ?>
            </div>
            <p class="text-xs text-gray-400 mt-1"><?= $totalAvaliacoes ?> avaliações</p>
        </div>
        <div class="flex-1 space-y-2">
            <?php for ($i = 5; $i >= 1; $i--):
                $cnt = $dist[$i];
                $pct = $totalAvaliacoes > 0 ? round($cnt / $totalAvaliacoes * 100) : 0;
            ?>
            <div class="flex items-center gap-2 text-xs">
                <span class="w-4 text-right font-semibold text-gray-600"><?= $i ?></span>
                <svg class="w-3.5 h-3.5 text-yellow-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                    <div class="h-2 rounded-full bg-yellow-400 transition-all" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="w-12 text-gray-400"><?= $cnt ?> (<?= $pct ?>%)</span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Resumo rápido -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-700 mb-4">Distribuição de notas</h3>
        <div class="grid grid-cols-5 gap-2">
            <?php for ($i = 5; $i >= 1; $i--):
                $cnt = $dist[$i];
                $colors = [5=>'bg-green-100 text-green-700',4=>'bg-emerald-100 text-emerald-700',3=>'bg-yellow-100 text-yellow-700',2=>'bg-orange-100 text-orange-700',1=>'bg-red-100 text-red-700'];
            ?>
            <a href="?nota=<?= $i ?><?= $filtroNota === $i ? '' : '' ?>"
               class="flex flex-col items-center p-3 rounded-xl <?= $colors[$i] ?> <?= $filtroNota === $i ? 'ring-2 ring-offset-1 ring-gray-400' : 'hover:opacity-80' ?> transition-all text-center">
                <span class="text-xl font-black"><?= $i ?></span>
                <svg class="w-4 h-4 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                <span class="text-xs font-semibold mt-1"><?= $cnt ?></span>
            </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Filtros em chips -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest mr-1">Filtrar:</span>
        <a href="/pizzaria/admin/avaliacoes.php"
           class="px-4 py-1.5 rounded-full text-sm font-semibold transition-all
                  <?= !$filtroNota ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            Todas
        </a>
        <?php for ($i = 5; $i >= 1; $i--): ?>
        <a href="?nota=<?= $i ?>"
           class="px-4 py-1.5 rounded-full text-sm font-semibold transition-all flex items-center gap-1
                  <?= $filtroNota === $i ? 'bg-yellow-400 text-gray-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= $i ?>
            <svg class="w-3.5 h-3.5 text-yellow-500 <?= $filtroNota === $i ? 'text-yellow-700' : '' ?>" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
        </a>
        <?php endfor; ?>
    </div>
</div>

<!-- Tabela de avaliações -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($avaliacoes)): ?>
    <div class="py-16 text-center text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
        </svg>
        <p class="text-sm">Nenhuma avaliação encontrada.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Pedido</th>
                    <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Cliente</th>
                    <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Nota</th>
                    <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Comentário</th>
                    <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Data</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($avaliacoes as $av): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-4">
                        <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">
                            #<?= $av['pedido_id'] ?>
                        </span>
                        <?php if ($av['total']): ?>
                        <p class="text-xs text-gray-400 mt-1">R$ <?= number_format($av['total'], 2, ',', '.') ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($av['cliente_nome'] ?? '—') ?></p>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-0.5">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg class="w-4 h-4 <?= $i <= $av['nota'] ? 'text-yellow-400' : 'text-gray-200' ?>"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <?php endfor; ?>
                            <span class="ml-1 text-xs font-bold text-gray-500"><?= $av['nota'] ?>/5</span>
                        </div>
                    </td>
                    <td class="px-5 py-4 max-w-xs">
                        <?php if ($av['comentario']): ?>
                        <p class="text-gray-600 text-sm leading-relaxed"><?= htmlspecialchars($av['comentario']) ?></p>
                        <?php else: ?>
                        <span class="text-gray-300 text-xs italic">Sem comentário</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap">
                        <p class="text-gray-600"><?= date('d/m/Y', strtotime($av['created_at'])) ?></p>
                        <p class="text-gray-400 text-xs"><?= date('H:i', strtotime($av['created_at'])) ?></p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-xs text-gray-400">
            Página <?= $pagina ?> de <?= $totalPaginas ?> · <?= $totalFiltrado ?> resultado<?= $totalFiltrado !== 1 ? 's' : '' ?>
        </p>
        <div class="flex gap-2">
            <?php if ($pagina > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>"
               class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-lg text-xs transition-colors">
                ← Anterior
            </a>
            <?php endif; ?>
            <?php if ($pagina < $totalPaginas): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>"
               class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-lg text-xs transition-colors">
                Próxima →
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
