<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();

// ── Ações POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao  = $_POST['acao'] ?? '';
    $id    = (int) ($_POST['id'] ?? 0);

    if ($acao === 'salvar') {
        $nome  = trim($_POST['nome'] ?? '');
        $preco = filter_var(str_replace(',', '.', $_POST['preco'] ?? ''), FILTER_VALIDATE_FLOAT);
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if ($nome && $preco !== false && $preco >= 0) {
            if ($id > 0) {
                $pdo->prepare('UPDATE adicionais SET nome=?, preco=?, ativo=? WHERE id=?')
                    ->execute([$nome, $preco, $ativo, $id]);
                header('Location: ' . BASE . '/admin/adicionais.php?msg=editado'); exit;
            } else {
                $pdo->prepare('INSERT INTO adicionais (nome, preco, ativo) VALUES (?,?,?)')
                    ->execute([$nome, $preco, $ativo]);
                header('Location: ' . BASE . '/admin/adicionais.php?msg=criado'); exit;
            }
        }

    } elseif ($acao === 'toggle' && $id > 0) {
        $pdo->prepare('UPDATE adicionais SET ativo = NOT ativo WHERE id=?')->execute([$id]);
        header('Location: ' . BASE . '/admin/adicionais.php'); exit;

    } elseif ($acao === 'deletar' && $id > 0) {
        $pdo->prepare('DELETE FROM adicionais WHERE id=?')->execute([$id]);
        header('Location: ' . BASE . '/admin/adicionais.php?msg=removido'); exit;
    }
}

$msgs = [
    'criado'  => 'Adicional criado com sucesso!',
    'editado' => 'Adicional atualizado com sucesso!',
    'removido'=> 'Adicional removido.',
];
$msg = $msgs[$_GET['msg'] ?? ''] ?? '';

$lista = $pdo->query('SELECT * FROM adicionais ORDER BY ativo DESC, nome')->fetchAll();

$pageTitle = 'Adicionais — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<!-- ── Cabeçalho ───────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Adicionais</h1>
        <p class="text-gray-400 text-sm mt-0.5">Extras que o cliente pode adicionar ao pedido</p>
    </div>
    <button onclick="abrirModal()"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo adicional
    </button>
</div>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Tabela ──────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($lista)): ?>
    <div class="py-20 text-center text-gray-400">
        <p class="text-5xl mb-4">➕</p>
        <p class="text-base mb-4">Nenhum adicional cadastrado ainda.</p>
        <button onclick="abrirModal()"
                class="bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-2.5 rounded-xl transition-colors text-sm">
            Criar primeiro adicional
        </button>
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
                <th class="text-left px-5 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">Adicional</th>
                <th class="text-right px-5 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">Preço</th>
                <th class="text-center px-5 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wide">Status</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php foreach ($lista as $a): ?>
            <tr class="hover:bg-gray-50 transition-colors <?= !$a['ativo'] ? 'opacity-50' : '' ?>">
                <td class="px-5 py-3.5">
                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($a['nome']) ?></span>
                </td>
                <td class="px-5 py-3.5 text-right font-bold text-red-600">
                    +R$ <?= number_format($a['preco'], 2, ',', '.') ?>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="acao" value="toggle">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit"
                                class="px-3 py-1 rounded-full text-xs font-bold transition-colors
                                       <?= $a['ativo'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
                            <?= $a['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </button>
                    </form>
                </td>
                <td class="px-5 py-3.5">
                    <div class="flex items-center justify-end gap-3">
                        <!-- Editar: abre modal com dados pré-preenchidos via JS -->
                        <button type="button"
                                onclick="abrirModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['nome'])) ?>', '<?= number_format($a['preco'], 2, ',', '') ?>', <?= $a['ativo'] ?>)"
                                class="text-gray-400 hover:text-blue-600 transition-colors" title="Editar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <!-- Deletar -->
                        <form method="POST" class="inline"
                              onsubmit="return confirm('Remover «<?= htmlspecialchars(addslashes($a['nome'])) ?>»?')">
                            <input type="hidden" name="acao" value="deletar">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
    <?php endif; ?>
</div>

<!-- ── Modal de criação / edição ──────────────────────────────── -->
<div id="modal-add" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this) fecharModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation()">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 id="modal-titulo" class="font-extrabold text-gray-900 text-lg">Novo adicional</h2>
            <button onclick="fecharModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 text-xl leading-none transition-colors">×</button>
        </div>

        <form method="POST" action="<?= BASE ?>/admin/adicionais.php" class="p-6 space-y-4">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="modal-id" value="0">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nome *</label>
                <input type="text" name="nome" id="modal-nome" required
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                       placeholder="Ex: Borda recheada com catupiry">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Preço (R$) *</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">R$</span>
                    <input type="text" name="preco" id="modal-preco" required
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                           placeholder="5,00">
                </div>
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" id="modal-ativo" value="1" class="sr-only peer" checked>
                    <div class="w-10 h-5 bg-gray-300 peer-checked:bg-red-500 rounded-full transition-colors
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
                <p class="text-sm font-semibold text-gray-700">Disponível para clientes</p>
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

function abrirModal(id = 0, nome = '', preco = '', ativo = 1) {
    document.getElementById('modal-titulo').textContent = id ? 'Editar adicional' : 'Novo adicional';
    document.getElementById('modal-id').value    = id;
    document.getElementById('modal-nome').value  = nome;
    document.getElementById('modal-preco').value = preco;
    document.getElementById('modal-ativo').checked = ativo == 1;

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
