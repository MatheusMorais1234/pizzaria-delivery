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

$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$combo = null;

// Carrega combo existente para edição
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM combos WHERE id = ?');
    $stmt->execute([$id]);
    $combo = $stmt->fetch();
    if (!$combo) {
        header('Location: /pizzaria/admin/combos.php');
        exit;
    }
}

$erros = [];

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome          = trim($_POST['nome']          ?? '');
    $descricao     = trim($_POST['descricao']     ?? '');
    $precoOriginal = filter_var(str_replace(',', '.', $_POST['preco_original'] ?? ''), FILTER_VALIDATE_FLOAT);
    $precoCombo    = filter_var(str_replace(',', '.', $_POST['preco_combo']    ?? ''), FILTER_VALIDATE_FLOAT);
    $badge         = trim($_POST['badge']         ?? '');
    $imagemUrl     = trim($_POST['imagem_url']    ?? '');
    $ativo         = isset($_POST['ativo']) ? 1 : 0;
    $ordem         = filter_var($_POST['ordem'] ?? 0, FILTER_VALIDATE_INT);
    $ordem         = ($ordem === false || $ordem < 0) ? 0 : min(255, $ordem);

    // Validações
    if ($nome === '') {
        $erros[] = 'O nome do combo é obrigatório.';
    }
    if ($precoCombo === false || $precoCombo < 0) {
        $erros[] = 'O preço do combo é obrigatório e deve ser maior ou igual a zero.';
    }
    if ($precoOriginal === false) {
        $precoOriginal = 0.00;
    }

    if (empty($erros)) {
        if ($id) {
            // Atualiza
            $pdo->prepare('
                UPDATE combos
                SET nome=?, descricao=?, preco_original=?, preco_combo=?,
                    imagem_url=?, badge=?, ativo=?, ordem=?
                WHERE id=?
            ')->execute([
                $nome, $descricao, $precoOriginal, $precoCombo,
                $imagemUrl ?: null, $badge ?: null, $ativo, $ordem, $id
            ]);
        } else {
            // Insere
            $pdo->prepare('
                INSERT INTO combos (nome, descricao, preco_original, preco_combo, imagem_url, badge, ativo, ordem)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $nome, $descricao, $precoOriginal, $precoCombo,
                $imagemUrl ?: null, $badge ?: null, $ativo, $ordem
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        header('Location: /pizzaria/admin/combo-form.php?id=' . $id . '&salvo=1');
        exit;
    }

    // Repovoar campos após erro
    $combo = [
        'nome'          => $nome,
        'descricao'     => $descricao,
        'preco_original'=> $precoOriginal !== false ? $precoOriginal : '',
        'preco_combo'   => $precoCombo    !== false ? $precoCombo    : '',
        'badge'         => $badge,
        'imagem_url'    => $imagemUrl,
        'ativo'         => $ativo,
        'ordem'         => $ordem,
    ];
}

$modoEdicao = !empty($id) && !empty($combo['nome'] ?? '');
$pageTitle  = $modoEdicao
    ? 'Editar: ' . htmlspecialchars($combo['nome']) . ' — Bella Napoli Admin'
    : 'Novo Combo — Bella Napoli Admin';

include __DIR__ . '/includes/layout_top.php';
?>

<!-- Mensagem de sucesso -->
<?php if (isset($_GET['salvo'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Combo salvo com sucesso!
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
    <a href="/pizzaria/admin/combos.php" class="hover:text-gray-700 transition-colors font-medium">Combos</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-700 font-semibold">
        <?= $modoEdicao ? 'Editar combo' : 'Novo combo' ?>
    </span>
</div>

<div class="max-w-2xl">

    <h1 class="text-2xl font-extrabold text-gray-900 mb-6">
        <?= $modoEdicao ? 'Editar combo' : 'Criar novo combo' ?>
    </h1>

    <!-- Erros -->
    <?php if (!empty($erros)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
        <p class="font-semibold text-red-700 text-sm mb-1">Corrija os erros abaixo:</p>
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            <?php foreach ($erros as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <form method="POST" class="space-y-5">

            <!-- Nome -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Nome do combo <span class="text-red-500">*</span>
                </label>
                <input type="text" name="nome" required
                       value="<?= htmlspecialchars($combo['nome'] ?? '') ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                       placeholder="Ex: Combo Família Bella">
            </div>

            <!-- Descrição -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Descrição</label>
                <textarea name="descricao" rows="3"
                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50 resize-none"
                          placeholder="Descreva o que está incluso no combo…"><?= htmlspecialchars($combo['descricao'] ?? '') ?></textarea>
            </div>

            <!-- Preços -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Preço original / De -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Preço normal
                        <span class="text-gray-400 font-normal ml-1">(De — opcional)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">R$</span>
                        <input type="number" name="preco_original" min="0" step="0.01"
                               value="<?= htmlspecialchars(isset($combo['preco_original']) && $combo['preco_original'] > 0 ? number_format((float)$combo['preco_original'], 2, '.', '') : '') ?>"
                               class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                               placeholder="0.00"
                               oninput="atualizarEconomia()">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Aparece riscado no cardápio</p>
                </div>

                <!-- Preço combo / Por -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Preço do combo <span class="text-red-500">*</span>
                        <span class="text-gray-400 font-normal ml-1">(Por)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-red-400 text-sm font-bold">R$</span>
                        <input type="number" name="preco_combo" min="0" step="0.01" required
                               value="<?= htmlspecialchars(isset($combo['preco_combo']) ? number_format((float)$combo['preco_combo'], 2, '.', '') : '') ?>"
                               class="w-full border border-red-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-red-50/40 font-semibold"
                               placeholder="0.00"
                               oninput="atualizarEconomia()">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Preço final pago pelo cliente</p>
                </div>
            </div>

            <!-- Preview de economia -->
            <div id="preview-economia" class="hidden bg-green-50 border border-green-100 rounded-xl px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm">
                    <span class="text-gray-500">De </span>
                    <span id="economia-de" class="line-through text-gray-400 font-medium"></span>
                    <span class="text-gray-500"> por </span>
                    <span id="economia-por" class="text-red-600 font-extrabold"></span>
                    <span class="ml-2 bg-green-200 text-green-800 text-xs font-bold px-2 py-0.5 rounded-full" id="economia-pct"></span>
                </div>
            </div>

            <!-- Badge -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Badge
                    <span class="text-gray-400 font-normal ml-1">(opcional)</span>
                </label>
                <input type="text" name="badge" maxlength="50"
                       value="<?= htmlspecialchars($combo['badge'] ?? '') ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                       placeholder="Ex: MAIS PEDIDO, FAMÍLIA, OFERTA">
                <p class="text-xs text-gray-400 mt-1">Etiqueta destacada exibida no card do combo</p>
            </div>

            <!-- Imagem URL -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    URL da imagem
                    <span class="text-gray-400 font-normal ml-1">(opcional)</span>
                </label>
                <input type="url" name="imagem_url" id="campo-imagem-url"
                       value="<?= htmlspecialchars($combo['imagem_url'] ?? '') ?>"
                       oninput="previewImagemUrl(this.value)"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                       placeholder="https://…">

                <!-- Preview da imagem -->
                <div id="preview-imagem-wrap" class="mt-3 <?= empty($combo['imagem_url']) ? 'hidden' : '' ?>">
                    <div class="relative inline-block">
                        <img id="preview-imagem"
                             src="<?= htmlspecialchars($combo['imagem_url'] ?? '') ?>"
                             alt="Preview"
                             class="w-32 h-32 object-cover rounded-xl border border-gray-200 shadow-sm"
                             onerror="document.getElementById('preview-imagem-wrap').classList.add('hidden')">
                        <button type="button" onclick="limparImagemUrl()"
                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-md transition-colors">
                            ×
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ordem e Ativo -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Ordem -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ordem de exibição</label>
                    <input type="number" name="ordem" min="0" max="255"
                           value="<?= htmlspecialchars($combo['ordem'] ?? 0) ?>"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                           placeholder="0">
                    <p class="text-xs text-gray-400 mt-1">Menor número aparece primeiro</p>
                </div>

                <!-- Ativo -->
                <div class="flex flex-col justify-center">
                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100 h-full">
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="ativo" value="1" id="toggle-ativo"
                                   class="sr-only peer"
                                   <?= ($combo['ativo'] ?? 1) ? 'checked' : '' ?>>
                            <div class="w-10 h-5 bg-gray-300 peer-checked:bg-red-500 rounded-full transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                        </label>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Combo ativo</p>
                            <p class="text-xs text-gray-400">Visível no cardápio</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors shadow-sm">
                    <?= $modoEdicao ? 'Salvar alterações' : 'Criar combo' ?>
                </button>
                <a href="/pizzaria/admin/combos.php"
                   class="px-5 py-3 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 transition-colors text-center text-sm">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
// ── Preview de imagem por URL ─────────────────────────────────
function previewImagemUrl(url) {
    const wrap = document.getElementById('preview-imagem-wrap');
    const img  = document.getElementById('preview-imagem');
    if (url && url.trim() !== '') {
        img.src = url;
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
    }
}

function limparImagemUrl() {
    document.getElementById('campo-imagem-url').value = '';
    document.getElementById('preview-imagem').src = '';
    document.getElementById('preview-imagem-wrap').classList.add('hidden');
}

// ── Preview de economia (De / Por) ───────────────────────────
function atualizarEconomia() {
    const inputDe  = document.querySelector('input[name="preco_original"]');
    const inputPor = document.querySelector('input[name="preco_combo"]');
    const previewEl = document.getElementById('preview-economia');

    const de  = parseFloat(inputDe.value)  || 0;
    const por = parseFloat(inputPor.value) || 0;

    if (de > 0 && por > 0 && por < de) {
        const pct = Math.round(((de - por) / de) * 100);
        document.getElementById('economia-de').textContent  = 'R$ ' + de.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('economia-por').textContent = 'R$ ' + por.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        document.getElementById('economia-pct').textContent = pct + '% OFF';
        previewEl.classList.remove('hidden');
    } else {
        previewEl.classList.add('hidden');
    }
}

// Inicializa preview se estiver editando com valores já preenchidos
atualizarEconomia();
</script>
