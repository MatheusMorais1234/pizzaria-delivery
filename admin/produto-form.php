<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$produto = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
    $stmt->execute([$id]);
    $produto = $stmt->fetch();
    if (!$produto) { header('Location: ' . BASE . '/admin/produtos.php'); exit; }
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY ordem')->fetchAll();
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['nome']         ?? '');
    $catId       = filter_var($_POST['categoria_id'] ?? 0, FILTER_VALIDATE_INT);
    $descricao   = trim($_POST['descricao']    ?? '');
    $preco       = filter_var(str_replace(',','.', $_POST['preco'] ?? ''), FILTER_VALIDATE_FLOAT);
    $imagemUrl   = trim($_POST['imagem_url']   ?? '');
    $ativo       = isset($_POST['ativo']) ? 1 : 0;
    $estoqueRaw  = $_POST['estoque'] ?? '';
    $estoque     = ($estoqueRaw === '' || $estoqueRaw === null) ? null : max(0, (int)$estoqueRaw);
    // Pega apenas o último valor de desconto_pct (o hidden é o fallback, o number só existe quando ativo)
    $descontoPct = 0;
    if (isset($_POST['desconto_pct'])) {
        $vals = array_filter((array)$_POST['desconto_pct'], fn($v) => $v !== '' && $v !== '0');
        $descontoPct = !empty($vals) ? min(99, max(0, (float)end($vals))) : 0;
    }

    // Upload de imagem (tem prioridade sobre URL)
    $upload = $_FILES['imagem_upload'] ?? null;
    if ($upload && $upload['error'] === UPLOAD_ERR_OK && demo_ativa()) {
        // Na demo publica qualquer um entra no painel: nada de arquivos
        // de estranhos no servidor. Imagem por URL continua funcionando.
        $erros[] = 'Na demonstração o envio de imagens está desligado. Use o campo de URL.';
    } elseif ($upload && $upload['error'] === UPLOAD_ERR_OK) {
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($upload['tmp_name']);

        if (!in_array($mime, $tiposPermitidos)) {
            $erros[] = 'Tipo de arquivo inválido. Use JPG, PNG, WEBP ou GIF.';
        } elseif ($upload['size'] > 5 * 1024 * 1024) {
            $erros[] = 'Imagem muito grande. Máximo 5 MB.';
        } else {
            $ext     = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
            $arquivo = uniqid('prod_', true) . '.' . $ext;
            $destino = __DIR__ . '/../uploads/produtos/' . $arquivo;
            if (move_uploaded_file($upload['tmp_name'], $destino)) {
                $imagemUrl = BASE . '/uploads/produtos/' . $arquivo;
            } else {
                $erros[] = 'Falha ao salvar a imagem. Verifique permissões da pasta.';
            }
        }
    }

    if (!$nome)  $erros[] = 'Nome é obrigatório.';
    if (!$catId) $erros[] = 'Selecione uma categoria.';
    if ($preco === false || $preco < 0) $erros[] = 'Preço inválido.';

    if (empty($erros)) {
        if ($id) {
            $pdo->prepare('UPDATE produtos SET categoria_id=?,nome=?,descricao=?,preco=?,imagem_url=?,ativo=?,desconto_pct=?,estoque=? WHERE id=?')
                ->execute([$catId,$nome,$descricao,$preco,$imagemUrl,$ativo,$descontoPct,$estoque,$id]);
        } else {
            $pdo->prepare('INSERT INTO produtos (categoria_id,nome,descricao,preco,imagem_url,ativo,desconto_pct,estoque) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$catId,$nome,$descricao,$preco,$imagemUrl,$ativo,$descontoPct,$estoque]);
            $id = (int) $pdo->lastInsertId();
        }
        // Salva ingredientes
        $pdo->prepare('DELETE FROM produto_ingredientes WHERE produto_id=?')->execute([$id]);
        $ings = array_filter(array_map('trim', $_POST['ingredientes'] ?? []), fn($v) => $v !== '');
        $stmtIng = $pdo->prepare('INSERT INTO produto_ingredientes (produto_id, nome) VALUES (?,?)');
        foreach ($ings as $ing) { $stmtIng->execute([$id, $ing]); }

        // Salva tamanhos
        $pdo->prepare('DELETE FROM produto_tamanhos WHERE produto_id=?')->execute([$id]);
        $nomes  = $_POST['tamanho_nome']  ?? [];
        $precos = $_POST['tamanho_preco'] ?? [];
        $stmtT  = $pdo->prepare('INSERT INTO produto_tamanhos (produto_id, nome, preco, ordem) VALUES (?,?,?,?)');
        foreach ($nomes as $ord => $tNome) {
            $tNome  = trim($tNome);
            $tPreco = filter_var(str_replace(',', '.', $precos[$ord] ?? ''), FILTER_VALIDATE_FLOAT);
            if ($tNome && $tPreco !== false && $tPreco > 0) {
                $stmtT->execute([$id, $tNome, $tPreco, $ord]);
            }
        }

        header('Location: ' . BASE . '/admin/produto-form.php?id=' . $id . '&salvo=1');
        exit;
    }
    $produto = ['nome'=>$nome,'categoria_id'=>$catId,'descricao'=>$descricao,'preco'=>$preco,'imagem_url'=>$imagemUrl,'ativo'=>$ativo,'desconto_pct'=>$descontoPct,'estoque'=>$estoque];
}

// Ingredientes existentes
$ingredientesExist = [];
if ($id) {
    $s = $pdo->prepare('SELECT nome FROM produto_ingredientes WHERE produto_id=? ORDER BY id');
    $s->execute([$id]);
    $ingredientesExist = $s->fetchAll(PDO::FETCH_COLUMN);
}

// Tamanhos existentes
$tamanhos = [];
if ($id) {
    $stmtTam = $pdo->prepare('SELECT * FROM produto_tamanhos WHERE produto_id=? ORDER BY ordem');
    $stmtTam->execute([$id]);
    $tamanhos = $stmtTam->fetchAll();
}

$pageTitle = ($id ? 'Editar' : 'Novo') . ' Produto — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<?php if (isset($_GET['salvo'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    Produto salvo com sucesso!
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
    <a href="<?= BASE ?>/admin/produtos.php" class="hover:text-gray-700 transition-colors font-medium">Produtos</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-700 font-semibold"><?= $id ? 'Editar produto' : 'Novo produto' ?></span>
</div>

<div class="max-w-2xl">

    <h1 class="text-2xl font-extrabold text-gray-900 mb-6">
        <?= $id ? 'Editar produto' : 'Criar novo produto' ?>
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
        <form method="POST" enctype="multipart/form-data" class="space-y-5">

            <!-- Nome -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nome *</label>
                <input type="text" name="nome" required
                       value="<?= htmlspecialchars($produto['nome'] ?? '') ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                       placeholder="Ex: Margherita Clássica">
            </div>

            <!-- Categoria + Preço + Desconto -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Categoria *</label>
                    <select name="categoria_id" required
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50">
                        <option value="">Selecione…</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($produto['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Preço (R$) *</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">R$</span>
                        <input type="text" name="preco" required id="campo-preco"
                               value="<?= htmlspecialchars(isset($produto['preco']) ? number_format($produto['preco'],2,',','') : '') ?>"
                               class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50"
                               placeholder="42,90" oninput="atualizarPreviewDesconto()">
                    </div>
                </div>
            </div>

            <!-- Desconto em % -->
            <div class="p-4 bg-orange-50 border border-orange-100 rounded-xl">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Desconto promocional</p>
                        <p class="text-xs text-gray-400">Aparece como "X% OFF" no cardápio com o preço riscado</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="toggle-desconto" class="sr-only peer"
                               onchange="toggleDesconto(this.checked)"
                               <?= ($produto['desconto_pct'] ?? 0) > 0 ? 'checked' : '' ?>>
                        <div class="w-10 h-5 bg-gray-300 peer-checked:bg-orange-500 rounded-full transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                    </label>
                </div>
                <div id="painel-desconto" class="<?= ($produto['desconto_pct'] ?? 0) > 0 ? '' : 'hidden' ?>">
                    <div class="flex items-center gap-4">
                        <div class="relative flex-1">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">%</span>
                            <input type="number" name="desconto_pct" id="campo-desconto"
                                   min="1" max="99" step="1"
                                   value="<?= htmlspecialchars(($produto['desconto_pct'] ?? 0) > 0 ? number_format($produto['desconto_pct'],0) : '') ?>"
                                   class="w-full border border-orange-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-400"
                                   placeholder="Ex: 15"
                                   oninput="atualizarPreviewDesconto()">
                        </div>
                        <div id="preview-desconto" class="text-sm text-gray-600 hidden">
                            <span class="line-through text-gray-400" id="preco-original-preview"></span>
                            <span class="font-bold text-green-600 ml-1" id="preco-final-preview"></span>
                        </div>
                    </div>
                </div>
                <!-- Campo oculto enviado com 0 quando desconto está desativado -->
                <input type="hidden" name="desconto_pct" id="desconto-hidden" value="<?= htmlspecialchars($produto['desconto_pct'] ?? 0) ?>">
            </div>

            <!-- Tamanhos (P, M, G) -->
            <div class="border border-gray-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-sm">Tamanhos (P, M, G)</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Se cadastrado, o cliente escolhe o tamanho ao pedir. O preço do produto será ignorado.</p>
                    </div>
                    <button type="button" onclick="addTamanho()"
                            class="text-xs bg-gray-800 hover:bg-gray-700 text-white font-bold px-3 py-1.5 rounded-lg transition-colors">
                        + Tamanho
                    </button>
                </div>
                <div id="lista-tamanhos" class="space-y-2">
                    <?php foreach ($tamanhos as $t): ?>
                    <div class="flex items-center gap-2 tamanho-row">
                        <input type="text" name="tamanho_nome[]" value="<?= htmlspecialchars($t['nome']) ?>"
                               placeholder="Ex: P, M, G, Broto"
                               class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                        <div class="relative flex-shrink-0 w-32">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold pointer-events-none">R$</span>
                            <input type="number" name="tamanho_preco[]" value="<?= $t['preco'] ?>" min="0" step="0.01"
                                   placeholder="0,00"
                                   class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                        </div>
                        <button type="button" onclick="this.closest('.tamanho-row').remove()"
                                class="w-8 h-8 flex items-center justify-center text-gray-300 hover:text-red-500 transition-colors flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($tamanhos)): ?>
                <p id="sem-tamanhos" class="text-xs text-gray-400 text-center py-2">Nenhum tamanho — produto tem preço único.</p>
                <?php endif; ?>
            </div>

            <!-- Descrição -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Descrição</label>
                <textarea name="descricao" rows="3"
                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent bg-gray-50 resize-none"
                          placeholder="Descreva os ingredientes…"><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
            </div>

            <!-- Imagem: URL ou Upload/Câmera -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Imagem do produto</label>

                <!-- Abas -->
                <div class="flex rounded-xl border border-gray-200 overflow-hidden mb-3 text-sm font-semibold">
                    <button type="button" id="tab-url"
                            onclick="setTab('url')"
                            class="flex-1 py-2 bg-gray-900 text-white transition-colors">
                        🔗 URL
                    </button>
                    <button type="button" id="tab-upload"
                            onclick="setTab('upload')"
                            class="flex-1 py-2 bg-gray-50 text-gray-500 hover:bg-gray-100 transition-colors">
                        📁 Upload / Câmera
                    </button>
                </div>

                <!-- Aba URL -->
                <div id="painel-url">
                    <input type="url" name="imagem_url" id="campo-url"
                           value="<?= htmlspecialchars($produto['imagem_url'] ?? '') ?>"
                           oninput="previewImg(this.value)"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 bg-gray-50"
                           placeholder="https://…">
                </div>

                <!-- Aba Upload -->
                <div id="painel-upload" class="hidden">
                    <!-- Área de drop / clique -->
                    <label for="imagem_upload"
                           class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 hover:border-red-400 rounded-xl py-6 px-4 cursor-pointer transition-colors bg-gray-50 hover:bg-red-50 group">
                        <svg class="w-8 h-8 text-gray-400 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-600 group-hover:text-red-600">Clique para selecionar ou arraste</span>
                        <span class="text-xs text-gray-400">JPG, PNG, WEBP ou GIF · máx. 5 MB</span>
                    </label>
                    <input type="file" id="imagem_upload" name="imagem_upload"
                           accept="image/*"
                           class="hidden"
                           onchange="previewUpload(this)">

                    <!-- Botão câmera (mobile) -->
                    <label for="imagem_camera"
                           class="mt-2 flex items-center justify-center gap-2 w-full py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50 cursor-pointer transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Tirar foto agora
                    </label>
                    <input type="file" id="imagem_camera" name="imagem_upload"
                           accept="image/*" capture="environment"
                           class="hidden"
                           onchange="previewUpload(this)">
                </div>

                <!-- Preview unificado -->
                <div id="preview-wrap" class="mt-3 <?= empty($produto['imagem_url']) ? 'hidden' : '' ?>">
                    <div class="relative inline-block">
                        <img id="img-preview"
                             src="<?= htmlspecialchars($produto['imagem_url'] ?? '') ?>"
                             class="w-32 h-32 object-cover rounded-xl border border-gray-200 shadow-sm"
                             onerror="this.parentElement.parentElement.classList.add('hidden')">
                        <button type="button" onclick="limparImagem()"
                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs font-bold shadow-md transition-colors">
                            ×
                        </button>
                    </div>
                    <p id="preview-nome" class="text-xs text-gray-400 mt-1"></p>
                </div>
            </div>

            <!-- Ativo -->
            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" id="toggle-ativo"
                           class="sr-only peer"
                           <?= ($produto['ativo'] ?? 1) ? 'checked' : '' ?>>
                    <div class="w-10 h-5 bg-gray-300 peer-checked:bg-red-500 rounded-full transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
                <div>
                    <p class="text-sm font-semibold text-gray-700">Produto ativo</p>
                    <p class="text-xs text-gray-400">Quando inativo, não aparece no cardápio</p>
                </div>
            </div>

            <!-- Estoque -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Estoque</label>
                <div class="flex items-center gap-3">
                    <input type="number" name="estoque" id="estoque-input" min="0" step="1"
                           value="<?= $produto && $produto['estoque'] !== null ? (int)$produto['estoque'] : '' ?>"
                           placeholder="Ilimitado"
                           class="w-36 border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                    <p class="text-xs text-gray-400">Deixe em branco para ilimitado. Quando chegar a 0, o produto aparece como esgotado.</p>
                </div>
                <?php if ($produto && $produto['estoque'] !== null && $produto['estoque'] == 0): ?>
                <p class="text-xs text-red-500 font-semibold mt-1">⚠️ Produto esgotado no momento.</p>
                <?php endif; ?>
            </div>

            <!-- Ingredientes -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Ingredientes
                    <span class="text-gray-400 font-normal ml-1">(o cliente pode remover)</span>
                </label>
                <div id="ings-lista" class="flex flex-wrap gap-2 mb-2 min-h-[32px]">
                    <?php foreach ($ingredientesExist as $ing): ?>
                    <span class="ing-tag inline-flex items-center gap-1.5 bg-orange-50 border border-orange-200 text-orange-800 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <?= htmlspecialchars($ing) ?>
                        <input type="hidden" name="ingredientes[]" value="<?= htmlspecialchars($ing) ?>">
                        <button type="button" onclick="removerIng(this)" class="text-orange-400 hover:text-red-600 leading-none">×</button>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div class="flex gap-2">
                    <input type="text" id="ing-input"
                           class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400"
                           placeholder="Ex: Manjericão — pressione Enter para adicionar">
                    <button type="button" onclick="adicionarIng()"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition-colors">
                        + Add
                    </button>
                </div>
            </div>

            <!-- Botões -->
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors shadow-sm">
                    <?= $id ? 'Salvar alterações' : 'Criar produto' ?>
                </button>
                <a href="<?= BASE ?>/admin/produtos.php"
                   class="px-5 py-3 border border-gray-200 text-gray-600 font-semibold rounded-xl hover:bg-gray-50 transition-colors text-center text-sm">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
// ── Abas URL / Upload ─────────────────────────────────────────
function setTab(tab) {
    const isUrl = tab === 'url';
    document.getElementById('painel-url').classList.toggle('hidden', !isUrl);
    document.getElementById('painel-upload').classList.toggle('hidden', isUrl);
    document.getElementById('tab-url').className    = `flex-1 py-2 transition-colors ${isUrl  ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'}`;
    document.getElementById('tab-upload').className = `flex-1 py-2 transition-colors ${!isUrl ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'}`;
    if (!isUrl) document.getElementById('campo-url').value = '';
}

// ── Preview por URL ───────────────────────────────────────────
function previewImg(url) {
    const wrap = document.getElementById('preview-wrap');
    const img  = document.getElementById('img-preview');
    document.getElementById('preview-nome').textContent = '';
    if (url) { img.src = url; wrap.classList.remove('hidden'); }
    else { wrap.classList.add('hidden'); }
}

// ── Preview por arquivo ───────────────────────────────────────
function previewUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = e => {
        const wrap = document.getElementById('preview-wrap');
        document.getElementById('img-preview').src = e.target.result;
        document.getElementById('preview-nome').textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
        wrap.classList.remove('hidden');
        // Sincroniza o outro input de câmera/arquivo para evitar duplo envio
        document.querySelectorAll('input[name="imagem_upload"]').forEach(el => {
            if (el !== input) el.value = '';
        });
    };
    reader.readAsDataURL(file);
}

// ── Limpar imagem ─────────────────────────────────────────────
function limparImagem() {
    document.getElementById('img-preview').src = '';
    document.getElementById('preview-nome').textContent = '';
    document.getElementById('preview-wrap').classList.add('hidden');
    document.getElementById('campo-url').value = '';
    document.querySelectorAll('input[name="imagem_upload"]').forEach(el => el.value = '');
}

// Detecta se já há imagem carregada por upload (editando produto com imagem local)
<?php if (!empty($produto['imagem_url']) && str_starts_with($produto['imagem_url'] ?? '', BASE . '/uploads/')): ?>
setTab('upload');
<?php endif; ?>

// ── Desconto ───────────────────────────────────────────────────
function toggleDesconto(ativo) {
    document.getElementById('painel-desconto').classList.toggle('hidden', !ativo);
    if (!ativo) {
        document.getElementById('campo-desconto').value = '';
        document.getElementById('desconto-hidden').value = '0';
        document.getElementById('preview-desconto').classList.add('hidden');
    } else {
        document.getElementById('campo-desconto').focus();
    }
}

function atualizarPreviewDesconto() {
    const preco = parseFloat(document.getElementById('campo-preco').value.replace(',','.')) || 0;
    const pct   = parseFloat(document.getElementById('campo-desconto')?.value) || 0;
    const hidden = document.getElementById('desconto-hidden');
    const preview = document.getElementById('preview-desconto');

    if (hidden) hidden.value = pct > 0 ? pct : '0';

    if (preco > 0 && pct > 0) {
        const final = preco * (1 - pct / 100);
        document.getElementById('preco-original-preview').textContent = 'R$ ' + preco.toLocaleString('pt-BR',{minimumFractionDigits:2});
        document.getElementById('preco-final-preview').textContent    = 'R$ ' + final.toLocaleString('pt-BR',{minimumFractionDigits:2});
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
}

function adicionarIng() {
    const input = document.getElementById('ing-input');
    const nome = input.value.trim();
    if (!nome) return;
    const lista = document.getElementById('ings-lista');
    const span = document.createElement('span');
    span.className = 'ing-tag inline-flex items-center gap-1.5 bg-orange-50 border border-orange-200 text-orange-800 text-xs font-semibold px-3 py-1.5 rounded-full';
    span.innerHTML = `${nome.replace(/</g,'&lt;')}<input type="hidden" name="ingredientes[]" value="${nome.replace(/"/g,'&quot;')}"><button type="button" onclick="removerIng(this)" class="text-orange-400 hover:text-red-600 leading-none">×</button>`;
    lista.appendChild(span);
    input.value = '';
    input.focus();
}

function removerIng(btn) { btn.closest('.ing-tag').remove(); }

document.getElementById('ing-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); adicionarIng(); }
});

// ── Tamanhos ───────────────────────────────────────────────────
function addTamanho() {
    document.getElementById('sem-tamanhos')?.remove();
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 tamanho-row';
    row.innerHTML = `
        <input type="text" name="tamanho_nome[]" placeholder="Ex: P, M, G, Broto"
               class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
        <div class="relative flex-shrink-0 w-32">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold pointer-events-none">R$</span>
            <input type="number" name="tamanho_preco[]" min="0" step="0.01" placeholder="0,00"
                   class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>
        <button type="button" onclick="this.closest('.tamanho-row').remove()"
                class="w-8 h-8 flex items-center justify-center text-gray-300 hover:text-red-500 transition-colors flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>`;
    document.getElementById('lista-tamanhos').appendChild(row);
}
</script>
