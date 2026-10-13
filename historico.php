<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Meus Pedidos — Bella Napoli';

$telefone = trim($_GET['tel'] ?? '');
$pedidos  = [];
$erro     = '';

if ($telefone) {
    // Normaliza: mantém só dígitos para comparar
    $telLimpo = preg_replace('/\D/', '', $telefone);
    if (strlen($telLimpo) < 8) {
        $erro = 'Digite um telefone válido.';
    } else {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT p.*
            FROM pedidos p
            WHERE REGEXP_REPLACE(p.cliente_telefone, '[^0-9]', '') LIKE ?
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $stmt->execute(['%' . $telLimpo . '%']);
        $pedidos = $stmt->fetchAll();

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
        }
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
        <a href="/pizzaria/" class="text-white font-bold text-xl">🍕 Bella Napoli</a>
        <span class="text-red-200 text-sm">Meus Pedidos</span>
    </div>
</nav>

<main class="max-w-2xl mx-auto px-4 py-10 flex-1 w-full">

    <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Meus Pedidos</h1>
    <p class="text-gray-500 mb-8">Digite seu telefone para ver o histórico de pedidos.</p>

    <!-- Formulário de busca por telefone -->
    <form method="GET" class="flex gap-3 mb-8">
        <div class="relative flex-1">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <input type="tel" name="tel"
                   value="<?= htmlspecialchars($telefone) ?>"
                   placeholder="(11) 99999-9999"
                   required
                   class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-white">
        </div>
        <button type="submit"
                class="bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-3 rounded-xl transition-colors">
            Buscar
        </button>
    </form>

    <?php if ($erro): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-6">
        <?= htmlspecialchars($erro) ?>
    </div>
    <?php endif; ?>

    <?php if ($telefone && !$erro): ?>
        <?php if (empty($pedidos)): ?>
        <div class="text-center py-16 text-gray-400">
            <div class="text-6xl mb-4">📭</div>
            <p class="font-semibold text-lg">Nenhum pedido encontrado</p>
            <p class="text-sm mt-1">para o telefone <strong><?= htmlspecialchars($telefone) ?></strong></p>
            <a href="/pizzaria/cardapio.php" class="mt-6 inline-block bg-red-600 text-white px-6 py-3 rounded-full font-semibold hover:bg-red-700 transition-colors">
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
                    <a href="/pizzaria/acompanhar.php?id=<?= $p['id'] ?>"
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
