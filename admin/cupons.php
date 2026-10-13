<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo    = getPDO();
$erros  = [];
$sucesso = '';

// ── Ações POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'toggle') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare('UPDATE cupons SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
        header('Location: ' . BASE . '/admin/cupons.php?ok=toggle'); exit;
    }

    if ($acao === 'excluir') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id) $pdo->prepare('DELETE FROM cupons WHERE id = ?')->execute([$id]);
        header('Location: ' . BASE . '/admin/cupons.php?ok=excluir'); exit;
    }

    if ($acao === 'salvar') {
        $editId   = filter_var($_POST['edit_id'] ?? 0, FILTER_VALIDATE_INT);
        $codigo   = strtoupper(trim($_POST['codigo'] ?? ''));
        $tipo     = in_array($_POST['tipo'] ?? '', ['percentual','fixo']) ? $_POST['tipo'] : 'percentual';
        $valor    = filter_var(str_replace(',', '.', $_POST['valor'] ?? ''), FILTER_VALIDATE_FLOAT);
        $minimo   = filter_var(str_replace(',', '.', $_POST['pedido_minimo'] ?? '0'), FILTER_VALIDATE_FLOAT);
        $limite   = $_POST['uso_limite'] !== '' ? filter_var($_POST['uso_limite'] ?? '', FILTER_VALIDATE_INT) : null;
        $validade = $_POST['validade'] ?? '';
        $ativo    = isset($_POST['ativo']) ? 1 : 0;

        if (!$codigo || !preg_match('/^[A-Z0-9_\-]{2,30}$/', $codigo)) $erros[] = 'Código inválido (só letras maiúsculas, números, traço ou underline, 2–30 chars).';
        if ($valor === false || $valor <= 0) $erros[] = 'Valor do desconto inválido.';
        if ($tipo === 'percentual' && $valor > 100) $erros[] = 'Desconto percentual não pode ser maior que 100%.';

        if (empty($erros)) {
            if ($editId) {
                $pdo->prepare('UPDATE cupons SET codigo=?,tipo=?,valor=?,pedido_minimo=?,uso_limite=?,validade=?,ativo=? WHERE id=?')
                    ->execute([$codigo, $tipo, $valor, $minimo ?: 0, $limite, $validade ?: null, $ativo, $editId]);
            } else {
                try {
                    $pdo->prepare('INSERT INTO cupons (codigo,tipo,valor,pedido_minimo,uso_limite,validade,ativo) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$codigo, $tipo, $valor, $minimo ?: 0, $limite, $validade ?: null, $ativo]);
                } catch (Exception $e) {
                    $erros[] = 'Código já existe. Escolha outro.';
                }
            }
            if (empty($erros)) { header('Location: ' . BASE . '/admin/cupons.php?ok=salvo'); exit; }
        }
    }
}

// ── Edição ────────────────────────────────────────────────────
$editando = null;
$editId   = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM cupons WHERE id = ?');
    $stmt->execute([$editId]);
    $editando = $stmt->fetch();
}

$cupons = $pdo->query('SELECT * FROM cupons ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Cupons — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if (isset($_GET['ok'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    <?= $_GET['ok'] === 'salvo' ? 'Cupom salvo com sucesso!' : ($_GET['ok'] === 'excluir' ? 'Cupom excluído.' : 'Status atualizado.') ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Cupons de Desconto</h1>
        <p class="text-gray-500 text-sm mt-0.5"><?= count($cupons) ?> cupons cadastrados</p>
    </div>
    <button onclick="toggleForm()"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Cupom
    </button>
</div>

<!-- ── Formulário ──────────────────────────────────────────── -->
<div id="painel-form" class="<?= $editando || !empty($erros) ? '' : 'hidden' ?> mb-6">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 max-w-2xl">
    <h2 class="font-bold text-gray-800 text-lg mb-4"><?= $editando ? 'Editar cupom' : 'Criar novo cupom' ?></h2>

    <?php if (!empty($erros)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="edit_id" value="<?= $editando['id'] ?? 0 ?>">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Código *</label>
                <input type="text" name="codigo" required maxlength="30"
                       value="<?= htmlspecialchars($editando['codigo'] ?? strtoupper($_POST['codigo'] ?? '')) ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 uppercase"
                       placeholder="EX: PROMO10"
                       oninput="this.value=this.value.toUpperCase()">
                <p class="text-xs text-gray-400 mt-1">Só letras, números, traço ou underline</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipo *</label>
                <select name="tipo" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400" onchange="atualizarTipo(this.value)">
                    <option value="percentual" <?= ($editando['tipo'] ?? 'percentual') === 'percentual' ? 'selected' : '' ?>>Percentual (%)</option>
                    <option value="fixo"       <?= ($editando['tipo'] ?? '') === 'fixo' ? 'selected' : '' ?>>Fixo (R$)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Valor do desconto *</label>
                <div class="relative">
                    <span id="prefixo-valor" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">
                        <?= ($editando['tipo'] ?? 'percentual') === 'fixo' ? 'R$' : '%' ?>
                    </span>
                    <input type="text" name="valor" required
                           value="<?= htmlspecialchars(isset($editando['valor']) ? number_format($editando['valor'],2,',','') : '') ?>"
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                           placeholder="10,00">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Pedido mínimo</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">R$</span>
                    <input type="text" name="pedido_minimo"
                           value="<?= htmlspecialchars(isset($editando['pedido_minimo']) ? number_format($editando['pedido_minimo'],2,',','') : '0,00') ?>"
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                           placeholder="0,00">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Limite de usos</label>
                <input type="number" name="uso_limite" min="1"
                       value="<?= htmlspecialchars($editando['uso_limite'] ?? '') ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                       placeholder="Ilimitado">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Válido até</label>
                <input type="date" name="validade"
                       value="<?= htmlspecialchars($editando['validade'] ?? '') ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                        <input type="checkbox" name="ativo" value="1" class="sr-only peer"
                               <?= ($editando['ativo'] ?? 1) ? 'checked' : '' ?>>
                        <div class="w-10 h-5 bg-gray-300 peer-checked:bg-red-500 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-700">Ativo</span>
                </label>
            </div>
        </div>

        <div class="flex gap-3 pt-1">
            <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors">
                <?= $editando ? 'Salvar alterações' : 'Criar cupom' ?>
            </button>
            <button type="button" onclick="toggleForm()"
                    class="px-5 py-3 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 transition-colors text-sm">
                Cancelar
            </button>
        </div>
    </form>
</div>
</div>

<!-- ── Lista de cupons ─────────────────────────────────────── -->
<?php if (empty($cupons)): ?>
<div class="text-center py-20 text-gray-400">
    <div class="text-6xl mb-4">🎫</div>
    <p class="text-lg font-semibold">Nenhum cupom cadastrado</p>
    <p class="text-sm mt-1">Crie o primeiro cupom clicando em "Novo Cupom"</p>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-5 py-3.5 font-semibold text-gray-600">Código</th>
                <th class="text-left px-4 py-3.5 font-semibold text-gray-600">Desconto</th>
                <th class="text-left px-4 py-3.5 font-semibold text-gray-600 hidden sm:table-cell">Mínimo</th>
                <th class="text-left px-4 py-3.5 font-semibold text-gray-600 hidden md:table-cell">Usos</th>
                <th class="text-left px-4 py-3.5 font-semibold text-gray-600 hidden md:table-cell">Validade</th>
                <th class="text-left px-4 py-3.5 font-semibold text-gray-600">Status</th>
                <th class="px-4 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        <?php foreach ($cupons as $c): ?>
        <tr class="hover:bg-gray-50 transition-colors <?= !$c['ativo'] ? 'opacity-50' : '' ?>">
            <td class="px-5 py-4">
                <span class="font-bold text-gray-900 tracking-wider font-mono text-base"><?= htmlspecialchars($c['codigo']) ?></span>
            </td>
            <td class="px-4 py-4">
                <?php if ($c['tipo'] === 'percentual'): ?>
                    <span class="bg-orange-100 text-orange-700 font-bold text-sm px-2.5 py-1 rounded-full">
                        <?= number_format($c['valor'], 0) ?>% OFF
                    </span>
                <?php else: ?>
                    <span class="bg-blue-100 text-blue-700 font-bold text-sm px-2.5 py-1 rounded-full">
                        −R$ <?= number_format($c['valor'], 2, ',', '.') ?>
                    </span>
                <?php endif; ?>
            </td>
            <td class="px-4 py-4 text-gray-500 hidden sm:table-cell">
                <?= $c['pedido_minimo'] > 0 ? 'R$ ' . number_format($c['pedido_minimo'], 2, ',', '.') : '—' ?>
            </td>
            <td class="px-4 py-4 text-gray-500 hidden md:table-cell">
                <?= $c['usos'] ?>
                <?= $c['uso_limite'] !== null ? ' / ' . $c['uso_limite'] : '' ?>
            </td>
            <td class="px-4 py-4 text-gray-500 hidden md:table-cell">
                <?= $c['validade'] ? date('d/m/Y', strtotime($c['validade'])) : '—' ?>
            </td>
            <td class="px-4 py-4">
                <span class="text-xs font-bold px-2 py-1 rounded-full <?= $c['ativo'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                    <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
            </td>
            <td class="px-4 py-4">
                <div class="flex items-center gap-1 justify-end">
                    <a href="?editar=<?= $c['id'] ?>"
                       class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg font-semibold transition-colors">
                        Editar
                    </a>
                    <form method="POST" class="inline">
                        <input type="hidden" name="acao" value="toggle">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg font-semibold transition-colors
                            <?= $c['ativo'] ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-green-50 hover:bg-green-100 text-green-700' ?>">
                            <?= $c['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('Excluir este cupom?')">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg font-semibold transition-colors">
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
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
function toggleForm() {
    const p = document.getElementById('painel-form');
    p.classList.toggle('hidden');
    if (!p.classList.contains('hidden')) p.querySelector('input[name="codigo"]').focus();
}

function atualizarTipo(tipo) {
    document.getElementById('prefixo-valor').textContent = tipo === 'fixo' ? 'R$' : '%';
}

<?php if ($editando || !empty($erros)): ?>
document.getElementById('painel-form').classList.remove('hidden');
<?php endif; ?>
</script>
