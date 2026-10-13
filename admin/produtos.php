<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $id = filter_var($_POST['toggle_id'], FILTER_VALIDATE_INT);
    if ($id) $pdo->prepare('UPDATE produtos SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
    header('Location: /pizzaria/admin/produtos.php');
    exit;
}

$produtos = $pdo->query('
    SELECT p.*, c.nome AS categoria_nome
    FROM produtos p
    JOIN categorias c ON c.id = p.categoria_id
    ORDER BY c.ordem, p.nome
')->fetchAll();

$pageTitle = 'Produtos — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- Cabeçalho -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Produtos</h1>
        <p class="text-gray-500 text-sm mt-0.5"><?= count($produtos) ?> produtos cadastrados</p>
    </div>
    <a href="/pizzaria/admin/produto-form.php"
       class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Produto
    </a>
</div>

<?php if (isset($_GET['salvo'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Produto salvo com sucesso!
</div>
<?php endif; ?>

<!-- Grid de produtos (cards) -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($produtos as $p): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col <?= !$p['ativo'] ? 'opacity-60' : '' ?>">
        <!-- Imagem -->
        <div class="relative h-40 bg-gray-100 overflow-hidden">
            <img src="<?= htmlspecialchars($p['imagem_url']) ?>"
                 alt="<?= htmlspecialchars($p['nome']) ?>"
                 class="w-full h-full object-cover"
                 onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=400&q=60'">
            <!-- Badge categoria -->
            <span class="absolute top-2 left-2 bg-black/60 text-white text-xs font-semibold px-2 py-0.5 rounded-full backdrop-blur-sm">
                <?= htmlspecialchars($p['categoria_nome']) ?>
            </span>
            <!-- Badge ativo/inativo -->
            <span class="absolute top-2 right-2 text-xs font-bold px-2 py-0.5 rounded-full
                <?= $p['ativo'] ? 'bg-green-500 text-white' : 'bg-gray-500 text-white' ?>">
                <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
            </span>
        </div>

        <!-- Info -->
        <div class="p-4 flex flex-col flex-1">
            <div class="flex items-center gap-2 mb-1">
                <h3 class="font-bold text-gray-900 text-base leading-tight flex-1"><?= htmlspecialchars($p['nome']) ?></h3>
                <?php if ($p['estoque'] !== null): ?>
                    <?php if ((int)$p['estoque'] === 0): ?>
                    <span class="text-[10px] font-extrabold bg-red-100 text-red-700 px-2 py-0.5 rounded-full uppercase tracking-wide flex-shrink-0">Esgotado</span>
                    <?php else: ?>
                    <span class="text-[10px] font-extrabold bg-green-100 text-green-700 px-2 py-0.5 rounded-full flex-shrink-0"><?= (int)$p['estoque'] ?> un.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <p class="text-gray-500 text-xs leading-relaxed flex-1 mb-3">
                <?= htmlspecialchars(mb_strimwidth($p['descricao'], 0, 80, '…')) ?>
            </p>
            <div class="flex items-center justify-between">
                <div>
                    <?php if (($p['desconto_pct'] ?? 0) > 0): ?>
                        <?php $precoFinal = $p['preco'] * (1 - $p['desconto_pct'] / 100); ?>
                        <div class="flex items-center gap-1.5">
                            <span class="text-red-600 font-extrabold text-lg">R$ <?= number_format($precoFinal, 2, ',', '.') ?></span>
                            <span class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?= number_format($p['desconto_pct'], 0) ?>% OFF</span>
                        </div>
                        <span class="text-xs text-gray-400 line-through">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                    <?php else: ?>
                        <span class="text-red-600 font-extrabold text-xl">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2 items-center">
                    <a href="/pizzaria/admin/produto-form.php?id=<?= $p['id'] ?>"
                       class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg font-semibold transition-colors">
                        Editar
                    </a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
                        <button type="submit"
                                class="text-xs px-3 py-1.5 rounded-lg font-semibold transition-colors
                                    <?= $p['ativo'] ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-green-50 hover:bg-green-100 text-green-700' ?>">
                            <?= $p['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
