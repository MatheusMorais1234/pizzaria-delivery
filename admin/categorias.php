<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// ── Ações POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'salvar') {
        $nome  = trim($_POST['nome'] ?? '');
        $ordem = max(0, (int) ($_POST['ordem'] ?? 0));

        if ($nome !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE categorias SET nome=?, ordem=? WHERE id=?')
                    ->execute([$nome, $ordem, $id]);
                header('Location: /pizzaria/admin/categorias.php?msg=editado'); exit;
            } else {
                $pdo->prepare('INSERT INTO categorias (nome, ordem) VALUES (?,?)')
                    ->execute([$nome, $ordem]);
                header('Location: /pizzaria/admin/categorias.php?msg=criado'); exit;
            }
        }

    } elseif ($acao === 'deletar' && $id > 0) {
        $stmtQtd = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE categoria_id=?');
        $stmtQtd->execute([$id]);
        $temProdutos = (int) $stmtQtd->fetchColumn();

        if ($temProdutos > 0) {
            header('Location: /pizzaria/admin/categorias.php?msg=com_produtos'); exit;
        }
        $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$id]);
        header('Location: /pizzaria/admin/categorias.php?msg=removido'); exit;
    }
}

$msgs = [
    'criado'       => 'Categoria criada com sucesso!',
    'editado'      => 'Categoria atualizada com sucesso!',
    'removido'     => 'Categoria removida.',
    'com_produtos' => 'Não é possível remover: existem produtos nesta categoria. Mova ou exclua os produtos primeiro.',
];
$msg     = $msgs[$_GET['msg'] ?? ''] ?? '';
$msgErro = ($_GET['msg'] ?? '') === 'com_produtos';

$lista = $pdo->query('
    SELECT c.*, COUNT(p.id) AS total_produtos
    FROM categorias c
    LEFT JOIN produtos p ON p.categoria_id = c.id
    GROUP BY c.id
    ORDER BY c.ordem ASC, c.nome ASC
')->fetchAll();

$pageTitle = 'Categorias — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- ── Cabeçalho ───────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Categorias</h1>
        <p class="text-gray-400 text-sm mt-0.5">Organiza os produtos no cardápio por seção</p>
    </div>
    <button onclick="abrirModal()"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Nova categoria
    </button>
</div>

<?php if ($msg): ?>
<div class="<?= $msgErro ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <?php if ($msgErro): ?>
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <?php else: ?>
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    <?php endif; ?>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Tabela ──────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($lista)): ?>
    <div class="py-20 text-center text-gray-400">
        <p class="text-5xl mb-4">🗂️</p>
        <p class="text-base mb-4">Nenhuma categoria cadastrada ainda.</p>
        <button onclick="abrirModal()"
                class="bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-2.5 rounded-xl transition-colors text-sm">
            Criar primeira categoria
        </button>
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="text-left px-5 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">Categoria</th>
                <th class="text-center px-5 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">Ordem</th>
                <th class="text-center px-5 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">Produtos</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($lista as $c): ?>
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-5 py-3.5">
                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($c['nome']) ?></span>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <span class="inline-block bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full">
                        <?= (int) $c['ordem'] ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <?php if ($c['total_produtos'] > 0): ?>
                    <a href="/pizzaria/admin/produtos.php?categoria=<?= $c['id'] ?>"
                       class="inline-block bg-blue-50 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full hover:bg-blue-100 transition-colors">
                        <?= $c['total_produtos'] ?> produto<?= $c['total_produtos'] != 1 ? 's' : '' ?>
                    </a>
                    <?php else: ?>
                    <span class="text-gray-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3.5">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button"
                                onclick="abrirModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['nome'])) ?>', <?= (int) $c['ordem'] ?>)"
                                class="text-gray-400 hover:text-blue-600 transition-colors" title="Editar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <form method="POST" class="inline"
                              onsubmit="return confirm('Remover «<?= htmlspecialchars(addslashes($c['nome'])) ?>»?\n<?= $c['total_produtos'] > 0 ? 'Atenção: esta categoria possui ' . $c['total_produtos'] . ' produto(s).' : '' ?>')">
                            <input type="hidden" name="acao" value="deletar">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors" title="Remover">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-400">
        A ordem define a sequência das seções no cardápio. Números menores aparecem primeiro.
    </div>
    <?php endif; ?>
</div>

<!-- ── Modal de criação / edição ──────────────────────────────── -->
<div id="modal-add" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this) fecharModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" onclick="event.stopPropagation()">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 id="modal-titulo" class="font-extrabold text-gray-900 text-lg">Nova categoria</h2>
            <button onclick="fecharModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 text-xl leading-none transition-colors">×</button>
        </div>

        <form method="POST" action="/pizzaria/admin/categorias.php" class="p-6 space-y-4">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="modal-id" value="0">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nome *</label>
                <input type="text" name="nome" id="modal-nome" required
                       maxlength="100"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                       placeholder="Ex: Pizzas, Bebidas, Sobremesas">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ordem no cardápio</label>
                <input type="number" name="ordem" id="modal-ordem"
                       min="0" max="255" value="0"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                <p class="text-xs text-gray-400 mt-1">Números menores aparecem primeiro (0 = mais ao topo).</p>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm">
                    Salvar
                </button>
                <button type="button" onclick="fecharModal()"
                        class="px-5 py-2.5 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 text-sm transition-colors">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
const modal = document.getElementById('modal-add');

function abrirModal(id = 0, nome = '', ordem = 0) {
    document.getElementById('modal-titulo').textContent = id ? 'Editar categoria' : 'Nova categoria';
    document.getElementById('modal-id').value    = id;
    document.getElementById('modal-nome').value  = nome;
    document.getElementById('modal-ordem').value = ordem;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('modal-nome').focus();
}

function fecharModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });
</script>
