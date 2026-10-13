<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Meus Pedidos — Bella Napoli';

// "Meus pedidos" lista os pedidos feitos NESTE aparelho. O navegador
// guarda id + codigo secreto de cada pedido (localStorage) e manda aqui
// em ?p=id.token,id.token. So aparece o pedido cujo codigo confere.
//
// Antes a busca era por telefone, com LIKE parcial: qualquer pessoa via
// os pedidos - e o endereco - de qualquer outra.
$pares = [];
foreach (explode(',', (string) ($_GET['p'] ?? '')) as $par) {
    if (preg_match('/^(\d{1,10})\.([a-f0-9]{32})$/', trim($par), $m)) {
        $pares[(int) $m[1]] = $m[2];
    }
}
$pares   = array_slice($pares, 0, 30, true);
$buscou  = isset($_GET['p']);
$pedidos = [];

if ($pares) {
    $pdo  = getPDO();
    $ph   = implode(',', array_fill(0, count($pares), '?'));
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id IN ($ph) ORDER BY created_at DESC");
    $stmt->execute(array_keys($pares));
    $pedidos = array_values(array_filter(
        $stmt->fetchAll(),
        fn($p) => !empty($p['token']) && hash_equals($p['token'], $pares[(int) $p['id']])
    ));

    // Busca itens de cada pedido
    if ($pedidos) {
        $ids  = array_column($pedidos, 'id');
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT pi.pedido_id, pi.quantidade, pi.subtotal, pr.nome
            FROM pedido_itens pi
            JOIN produtos pr ON pr.id = pi.produto_id
            WHERE pi.pedido_id IN ($ph)
        ");
        $stmt->execute($ids);
        $itensPorPedido = [];
        foreach ($stmt->fetchAll() as $item) {
            $itensPorPedido[$item['pedido_id']][] = $item;
        }
        foreach ($pedidos as &$p) {
            $p['itens'] = $itensPorPedido[$p['id']] ?? [];
        }
        unset($p);
    }
}

$statusLabels = [
    'pendente'     => ['⏳ Pendente',      'bg-yellow-100 text-yellow-800'],
    'em_preparo'   => ['👨‍🍳 Em preparo', 'bg-blue-100 text-blue-800'],
    'saiu_entrega' => ['🛵 Em entrega',   'bg-orange-100 text-orange-800'],
    'entregue'     => ['✅ Entregue',      'bg-green-100 text-green-800'],
];

include __DIR__ . '/includes/header.php';
?>

<!-- NAVBAR -->
<nav class="bg-red-600 shadow-lg">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="<?= BASE ?>/" class="text-white font-bold text-xl">🍕 Bella Napoli</a>
        <span class="text-red-200 text-sm">Meus Pedidos</span>
    </div>
</nav>

<main class="max-w-2xl mx-auto px-4 py-10 flex-1 w-full">

    <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Meus Pedidos</h1>
    <p class="text-gray-500 mb-8">Os pedidos feitos neste aparelho aparecem aqui.</p>

    <?php if (!$buscou): ?>
    <!-- O navegador sabe quais pedidos foram feitos daqui: manda a lista e recarrega -->
    <script>
    (function () {
        let lista = [];
        try { lista = JSON.parse(localStorage.getItem('pizzaria_meus_pedidos') || '[]'); } catch (e) {}
        const p = lista
            .filter(x => Number.isInteger(x.id) && /^[a-f0-9]{32}$/.test(x.token || ''))
            .map(x => x.id + '.' + x.token).join(',');
        location.replace('?p=' + encodeURIComponent(p));
    })();
    </script>
    <?php endif; ?>

    <?php if ($buscou): ?>
        <?php if (empty($pedidos)): ?>
        <div class="text-center py-16 text-gray-400">
            <div class="text-6xl mb-4">📭</div>
            <p class="font-semibold text-lg">Nenhum pedido feito neste aparelho</p>
            <p class="text-sm mt-1">Seus próximos pedidos vão aparecer aqui automaticamente.</p>
            <a href="<?= BASE ?>/cardapio.php" class="mt-6 inline-block bg-red-600 text-white px-6 py-3 rounded-full font-semibold hover:bg-red-700 transition-colors">
                Fazer um pedido
            </a>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 mb-4">
            <strong><?= count($pedidos) ?></strong> pedido<?= count($pedidos) !== 1 ? 's' : '' ?> encontrado<?= count($pedidos) !== 1 ? 's' : '' ?>
        </p>

        <div class="space-y-4">
            <?php foreach ($pedidos as $p):
                [$label, $badgeClass] = $statusLabels[$p['status']] ?? ['?', 'bg-gray-100 text-gray-600'];
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header do pedido -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
                    <div>
                        <span class="font-extrabold text-gray-800 text-lg">#<?= $p['id'] ?></span>
                        <span class="text-gray-400 text-sm ml-2">
                            <?= date('d/m/Y \à\s H:i', strtotime($p['created_at'])) ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $badgeClass ?>">
                            <?= $label ?>
                        </span>
                        <span class="font-extrabold text-red-600 text-lg">
                            R$ <?= number_format($p['total'], 2, ',', '.') ?>
                        </span>
                    </div>
                </div>

                <!-- Itens -->
                <div class="px-5 py-3 space-y-1.5">
                    <?php foreach ($p['itens'] as $item): ?>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span><?= $item['quantidade'] ?>× <?= htmlspecialchars($item['nome']) ?></span>
                        <span class="font-medium">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Endereço + ação -->
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-50 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500 truncate">
                        📍 <?= htmlspecialchars($p['endereco']) ?>
                    </p>
                    <?php if ($p['status'] !== 'entregue'): ?>
                    <a href="<?= BASE ?>/acompanhar.php?id=<?= (int) $p['id'] ?>&amp;t=<?= htmlspecialchars($p['token']) ?>"
                       class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-semibold px-3 py-1.5 rounded-lg whitespace-nowrap transition-colors">
                        Acompanhar →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
