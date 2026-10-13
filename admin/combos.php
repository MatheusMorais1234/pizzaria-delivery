<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// Cria a tabela se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS combos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    preco_original DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    preco_combo DECIMAL(8,2) NOT NULL,
    imagem_url VARCHAR(500) DEFAULT NULL,
    badge VARCHAR(50) DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Processa exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $idDel = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($idDel) {
        $pdo->prepare('DELETE FROM combos WHERE id = ?')->execute([$idDel]);
    }
    header('Location: ' . BASE . '/admin/combos.php?excluido=1');
    exit;
}

$combos = $pdo->query('SELECT * FROM combos ORDER BY ordem ASC, id ASC')->fetchAll();

$pageTitle = 'Combos — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- Cabeçalho -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Combos</h1>
        <p class="text-gray-500 text-sm mt-0.5"><?= count($combos) ?> combo<?= count($combos) !== 1 ? 's' : '' ?> cadastrado<?= count($combos) !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= BASE ?>/admin/combo-form.php"
       class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo combo
    </a>
</div>

<?php if (isset($_GET['excluido'])): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    Combo excluído com sucesso.
</div>
<?php endif; ?>

<?php if (isset($_GET['salvo'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Combo salvo com sucesso!
</div>
<?php endif; ?>

<?php if (empty($combos)): ?>
<!-- Estado vazio -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
    <div class="w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">
        🍕
    </div>
    <h3 class="text-lg font-bold text-gray-800 mb-1">Nenhum combo cadastrado</h3>
    <p class="text-gray-400 text-sm mb-5">Crie seu primeiro combo promocional para atrair mais clientes.</p>
    <a href="<?= BASE ?>/admin/combo-form.php"
       class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Criar primeiro combo
    </a>
</div>
<?php else: ?>
<!-- Tabela -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/60">
                    <th class="text-left font-semibold text-gray-500 px-5 py-3.5 text-xs uppercase tracking-wide">Nome</th>
                    <th class="text-left font-semibold text-gray-500 px-5 py-3.5 text-xs uppercase tracking-wide hidden md:table-cell">Descrição</th>
                    <th class="text-left font-semibold text-gray-500 px-5 py-3.5 text-xs uppercase tracking-wide">Preço</th>
                    <th class="text-left font-semibold text-gray-500 px-5 py-3.5 text-xs uppercase tracking-wide hidden sm:table-cell">Badge</th>
                    <th class="text-left font-semibold text-gray-500 px-5 py-3.5 text-xs uppercase tracking-wide">Status</th>
                    <th class="text-right font-semibold text-gray-500 px-5 py-3.5 text-xs uppercase tracking-wide">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($combos as $c): ?>
                <tr class="hover:bg-gray-50/50 transition-colors group">
                    <!-- Nome + imagem -->
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($c['imagem_url'])): ?>
                            <img src="<?= htmlspecialchars($c['imagem_url']) ?>"
                                 alt="<?= htmlspecialchars($c['nome']) ?>"
                                 class="w-10 h-10 rounded-xl object-cover border border-gray-100 flex-shrink-0"
                                 onerror="this.style.display='none'">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-red-50 border border-red-100 flex items-center justify-center text-lg flex-shrink-0">🍕</div>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-gray-800 leading-tight"><?= htmlspecialchars($c['nome']) ?></p>
                                <p class="text-xs text-gray-400">Ordem: <?= (int)$c['ordem'] ?></p>
                            </div>
                        </div>
                    </td>

                    <!-- Descrição (oculta em mobile) -->
                    <td class="px-5 py-4 hidden md:table-cell">
                        <p class="text-gray-500 text-xs leading-relaxed max-w-[200px]">
                            <?= htmlspecialchars(mb_strimwidth($c['descricao'] ?? '', 0, 70, '…')) ?>
                        </p>
                    </td>

                    <!-- Preços -->
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-0.5">
                            <?php if ($c['preco_original'] > 0): ?>
                            <span class="text-xs text-gray-400 line-through">De R$ <?= number_format($c['preco_original'], 2, ',', '.') ?></span>
                            <?php endif; ?>
                            <span class="font-extrabold text-red-600 text-base">Por R$ <?= number_format($c['preco_combo'], 2, ',', '.') ?></span>
                            <?php if ($c['preco_original'] > 0 && $c['preco_combo'] < $c['preco_original']): ?>
                                <?php $economia = $c['preco_original'] - $c['preco_combo']; ?>
                                <span class="text-xs text-green-600 font-semibold">Economia: R$ <?= number_format($economia, 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Badge (oculta em mobile) -->
                    <td class="px-5 py-4 hidden sm:table-cell">
                        <?php if (!empty($c['badge'])): ?>
                        <span class="inline-block bg-orange-100 text-orange-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">
                            <?= htmlspecialchars($c['badge']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full
                            <?= $c['ativo'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                            <span class="w-1.5 h-1.5 rounded-full inline-block <?= $c['ativo'] ? 'bg-green-500' : 'bg-gray-400' ?>"></span>
                            <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>

                    <!-- Ações -->
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="<?= BASE ?>/admin/combo-form.php?id=<?= $c['id'] ?>"
                               class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg font-semibold transition-colors">
                                Editar
                            </a>
                            <form method="POST" class="inline"
                                  onsubmit="return confirm('Excluir o combo \'<?= htmlspecialchars(addslashes($c['nome'])) ?>\'? Esta ação não pode ser desfeita.')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit"
                                        class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg font-semibold transition-colors">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
