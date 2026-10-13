<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();
$erros   = [];
$sucesso = '';
$editando = null;

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $editId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $nome   = trim($_POST['nome'] ?? '');
        $taxa   = filter_var(str_replace(',', '.', $_POST['taxa_entrega'] ?? '0'), FILTER_VALIDATE_FLOAT);

        if (!$nome) $erros[] = 'Nome do bairro é obrigatório.';
        if ($taxa === false || $taxa < 0) $erros[] = 'Taxa de entrega inválida.';

        if (empty($erros)) {
            if ($editId) {
                $pdo->prepare('UPDATE bairros SET nome=?, taxa_entrega=? WHERE id=?')->execute([$nome, $taxa, $editId]);
                $sucesso = 'Bairro atualizado com sucesso!';
            } else {
                $pdo->prepare('INSERT INTO bairros (nome, taxa_entrega) VALUES (?,?)')->execute([$nome, $taxa]);
                $sucesso = 'Bairro cadastrado com sucesso!';
            }
        }
    }

    if ($acao === 'toggle') {
        $toggleId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($toggleId) {
            $pdo->prepare('UPDATE bairros SET ativo = NOT ativo WHERE id=?')->execute([$toggleId]);
        }
    }

    if ($acao === 'deletar') {
        $delId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($delId) {
            $pdo->prepare('DELETE FROM bairros WHERE id=?')->execute([$delId]);
            $sucesso = 'Bairro removido.';
        }
    }
}

// Editar (GET)
$editId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM bairros WHERE id=?');
    $stmt->execute([$editId]);
    $editando = $stmt->fetch();
}

$bairros = $pdo->query('SELECT * FROM bairros ORDER BY nome')->fetchAll();

$pageTitle = 'Bairros — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Bairros / Taxa de Entrega</h1>
        <p class="text-gray-400 text-sm mt-0.5">Cadastre bairros e defina o valor da taxa de entrega para cada um</p>
    </div>
</div>

<?php if ($sucesso): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    <?= htmlspecialchars($sucesso) ?>
</div>
<?php endif; ?>

<?php if (!empty($erros)): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
        <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Formulário -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-bold text-gray-800 mb-4">
                <?= $editando ? 'Editar bairro' : 'Novo bairro' ?>
            </h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= $editando ? (int)$editando['id'] : '' ?>">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nome do bairro *</label>
                    <input type="text" name="nome" required
                           value="<?= htmlspecialchars($editando['nome'] ?? '') ?>"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-gray-50"
                           placeholder="Ex: Centro, Setor Bueno…">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Taxa de entrega (R$)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">R$</span>
                        <input type="number" name="taxa_entrega" min="0" step="0.01"
                               value="<?= $editando ? number_format((float)$editando['taxa_entrega'], 2, '.', '') : '0.00' ?>"
                               class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-gray-50"
                               placeholder="0,00">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Use 0,00 para entrega grátis neste bairro</p>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm">
                        <?= $editando ? 'Salvar alterações' : 'Cadastrar bairro' ?>
                    </button>
                    <?php if ($editando): ?>
                    <a href="<?= BASE ?>/admin/bairros.php"
                       class="px-4 py-2.5 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 transition-colors text-sm text-center">
                        Cancelar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <?php if (empty($bairros)): ?>
            <div class="py-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p class="text-sm">Nenhum bairro cadastrado ainda.</p>
                <p class="text-xs mt-1">Use o formulário ao lado para adicionar o primeiro.</p>
            </div>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Bairro</th>
                        <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Taxa</th>
                        <th class="text-left px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
                        <th class="text-right px-5 py-3.5 font-semibold text-gray-500 text-xs uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($bairros as $b): ?>
                    <tr class="hover:bg-gray-50 transition-colors <?= !$b['ativo'] ? 'opacity-50' : '' ?>">
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($b['nome']) ?></p>
                        </td>
                        <td class="px-5 py-3.5">
                            <?php if ((float)$b['taxa_entrega'] > 0): ?>
                            <span class="font-semibold text-gray-700">R$ <?= number_format((float)$b['taxa_entrega'], 2, ',', '.') ?></span>
                            <?php else: ?>
                            <span class="text-green-600 font-semibold text-xs bg-green-50 px-2 py-0.5 rounded-full">Grátis</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <form method="POST">
                                <input type="hidden" name="acao" value="toggle">
                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                <button type="submit"
                                        class="text-xs font-semibold px-2.5 py-1 rounded-full transition-colors
                                               <?= $b['ativo'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
                                    <?= $b['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </button>
                            </form>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center gap-2 justify-end">
                                <a href="<?= BASE ?>/admin/bairros.php?editar=<?= $b['id'] ?>"
                                   class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition-colors px-2 py-1 rounded-lg hover:bg-blue-50">
                                    Editar
                                </a>
                                <form method="POST" onsubmit="return confirm('Remover o bairro <?= addslashes(htmlspecialchars($b['nome'])) ?>?')">
                                    <input type="hidden" name="acao" value="deletar">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <button type="submit"
                                            class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50">
                                        Remover
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
