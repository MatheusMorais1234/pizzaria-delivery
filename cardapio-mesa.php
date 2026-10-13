<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pdo = getPDO();

$mesa = trim($_GET['mesa'] ?? '');
$mesaNum = $mesa !== '' ? (int)$mesa : null;
$mesaLabel = $mesaNum ? "Mesa $mesaNum" : 'Mesa';

// Carrega dados do cardápio
$categorias = $pdo->query('SELECT * FROM categorias ORDER BY ordem')->fetchAll();

$produtos = $pdo->query('
    SELECT p.*, c.nome AS categoria_nome, c.id AS categoria_id_ref
    FROM produtos p
    JOIN categorias c ON c.id = p.categoria_id
    WHERE p.ativo = 1
    ORDER BY c.ordem, p.nome
')->fetchAll();

$tamRows = $pdo->query('SELECT * FROM produto_tamanhos WHERE ativo=1 ORDER BY produto_id, ordem')->fetchAll();
$tamanhosPorProduto = [];
foreach ($tamRows as $t) { $tamanhosPorProduto[$t['produto_id']][] = $t; }

$combos = $pdo->query('SELECT * FROM combos WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();

$nomePizzaria = getConfig('nome_pizzaria', 'Bella Napoli');
$whatsapp     = getConfig('whatsapp', '');
$tempoEspera  = (int) getConfig('tempo_espera_atual', '0');

// Agrupar produtos por categoria
$produtosPorCat = [];
foreach ($produtos as $p) {
    $produtosPorCat[$p['categoria_id_ref']][] = $p;
}

function fmtPreco(float $v): string {
    return number_format($v, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mesaNum ? "Mesa $mesaNum — Cardápio" : 'Cardápio' ?> — <?= htmlspecialchars($nomePizzaria) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 2px; }
        html { scroll-behavior: smooth; }
        .cat-pill.active { background: #dc2626; color: #fff; }
        .cat-pill { scroll-snap-align: start; }
        /* Cart drawer */
        #cart-drawer {
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        #cart-drawer.open {
            transform: translateY(0);
        }
        #cart-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        #cart-overlay.open {
            opacity: 1;
            pointer-events: all;
        }
        /* Size pill buttons */
        .size-pill { transition: all 0.15s; }
        .size-pill.selected { background: #dc2626; color: #fff; border-color: #dc2626; }
        /* Modal */
        #modal-checkout {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        #modal-checkout.open {
            opacity: 1;
            pointer-events: all;
        }
        /* Tela sucesso */
        #tela-sucesso {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-32">

<!-- ===== TELA DE SUCESSO (overlay) ===== -->
<div id="tela-sucesso" class="fixed inset-0 bg-white z-[100] flex flex-col items-center justify-center px-6 text-center">
    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Pedido enviado!</h2>
    <p class="text-gray-500 mb-1">Seu pedido foi recebido com sucesso.</p>
    <p id="sucesso-mesa-label" class="text-lg font-bold text-orange-600 mb-6"></p>
    <div id="sucesso-pedido-info" class="bg-gray-50 border border-gray-200 rounded-2xl px-6 py-4 mb-6 w-full max-w-sm">
        <p id="sucesso-pedido-numero" class="font-extrabold text-gray-900 text-lg mb-2"></p>
        <a id="sucesso-link-acompanhar" href="#" class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 hover:text-red-700 underline underline-offset-2">
            Acompanhar meu pedido
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
        </a>
    </div>
    <button onclick="voltarCardapio()" class="text-gray-500 text-sm font-semibold underline">
        Voltar ao cardápio
    </button>
</div>

<!-- ===== HEADER ===== -->
<header class="bg-red-600 shadow-lg sticky top-0 z-30">
    <div class="max-w-3xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🍕</div>
            <div>
                <p class="text-white font-extrabold text-lg leading-tight font-display"><?= htmlspecialchars($nomePizzaria) ?></p>
                <p class="text-red-200 text-xs">Cardápio digital</p>
            </div>
        </div>
        <?php if ($mesaNum !== null): ?>
        <div class="flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 bg-white text-red-600 font-extrabold text-sm px-4 py-2 rounded-full shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M5 14h14M7 18h10"/>
                </svg>
                Mesa <?= $mesaNum ?>
            </span>
        </div>
        <?php else: ?>
        <div class="flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 bg-white/20 text-white font-semibold text-sm px-4 py-2 rounded-full">
                Cardápio
            </span>
        </div>
        <?php endif; ?>
    </div>
</header>

<!-- ===== PILLS DE CATEGORIA ===== -->
<?php if (!empty($categorias)): ?>
<div class="bg-white border-b border-gray-100 sticky top-[72px] z-20 shadow-sm">
    <div class="max-w-3xl mx-auto px-4">
        <div class="flex gap-2 py-3 overflow-x-auto no-scrollbar" style="scroll-snap-type:x mandatory;">
            <?php if (!empty($combos)): ?>
            <button onclick="scrollToSection('sec-combos')"
                    class="cat-pill flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-red-600 hover:text-white transition-colors whitespace-nowrap">
                🎁 Combos
            </button>
            <?php endif; ?>
            <?php foreach ($categorias as $cat): ?>
            <?php if (!empty($produtosPorCat[$cat['id']])): ?>
            <button onclick="scrollToSection('sec-cat-<?= $cat['id'] ?>')"
                    class="cat-pill flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-red-600 hover:text-white transition-colors whitespace-nowrap">
                <?= htmlspecialchars($cat['nome']) ?>
            </button>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tempoEspera > 0): ?>
<!-- ══ BANNER TEMPO DE ESPERA ══ -->
<div class="bg-amber-50 border-b border-amber-200 py-2.5 px-4 text-center text-sm font-semibold text-amber-700">
    ⏱ Tempo estimado hoje: ~<?= $tempoEspera ?> min
</div>
<?php endif; ?>

<!-- ===== CONTEÚDO ===== -->
<main class="max-w-3xl mx-auto px-4 py-6 space-y-10">

    <!-- Combos -->
    <?php if (!empty($combos)): ?>
    <section id="sec-combos">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-8 h-8 bg-orange-100 rounded-xl flex items-center justify-center text-lg flex-shrink-0">🎁</div>
            <h2 class="text-xl font-extrabold text-gray-900">Combos Especiais</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($combos as $combo): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                <?php if ($combo['imagem']): ?>
                <div class="relative">
                    <img src="<?= htmlspecialchars($combo['imagem']) ?>" alt="<?= htmlspecialchars($combo['nome']) ?>"
                         class="w-full h-40 object-cover"
                         onerror="this.style.display='none'">
                    <span class="absolute top-3 left-3 bg-orange-500 text-white text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wide shadow">
                        Combo
                    </span>
                </div>
                <?php else: ?>
                <div class="h-28 bg-gradient-to-br from-orange-100 to-red-100 flex items-center justify-center relative">
                    <span class="text-5xl">🎁</span>
                    <span class="absolute top-3 left-3 bg-orange-500 text-white text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wide">
                        Combo
                    </span>
                </div>
                <?php endif; ?>
                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="font-extrabold text-gray-900 text-base"><?= htmlspecialchars($combo['nome']) ?></h3>
                    <?php if ($combo['descricao']): ?>
                    <p class="text-sm text-gray-500 mt-1 flex-1 leading-relaxed"><?= htmlspecialchars($combo['descricao']) ?></p>
                    <?php endif; ?>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <span class="text-orange-600 font-extrabold text-xl">R$ <?= fmtPreco((float)$combo['preco_combo']) ?></span>
                        <?php if ($mesaNum !== null): ?>
                        <button onclick="addToCart(null, <?= htmlspecialchars(json_encode($combo['nome'])) ?>, <?= (float)$combo['preco_combo'] ?>, null, true, <?= (int)$combo['id'] ?>)"
                                class="flex-shrink-0 bg-red-600 hover:bg-red-700 active:scale-95 text-white text-sm font-bold px-4 py-2 rounded-xl transition-all">
                            + Adicionar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Categorias e Produtos -->
    <?php foreach ($categorias as $cat):
        if (empty($produtosPorCat[$cat['id']])) continue;
    ?>
    <section id="sec-cat-<?= $cat['id'] ?>">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-8 h-8 bg-red-100 rounded-xl flex items-center justify-center text-lg flex-shrink-0">
                <?= !empty($cat['icone']) ? htmlspecialchars($cat['icone']) : '🍽️' ?>
            </div>
            <h2 class="text-xl font-extrabold text-gray-900"><?= htmlspecialchars($cat['nome']) ?></h2>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($produtosPorCat[$cat['id']] as $prod):
                $tamanhos = $tamanhosPorProduto[$prod['id']] ?? [];
                $precoBase = (float) $prod['preco'];
                $temDesconto = (float)$prod['desconto_pct'] > 0;
                $precoFinal = $temDesconto ? round($precoBase * (1 - (float)$prod['desconto_pct'] / 100), 2) : $precoBase;
                $esgotado = $prod['estoque'] !== null && (int)$prod['estoque'] <= 0;
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col <?= $esgotado ? 'opacity-60' : '' ?>">
                <?php if (!empty($prod['imagem'])): ?>
                <div class="relative">
                    <img src="<?= htmlspecialchars($prod['imagem']) ?>" alt="<?= htmlspecialchars($prod['nome']) ?>"
                         class="w-full h-40 object-cover"
                         onerror="this.style.display='none'">
                    <?php if ($esgotado): ?>
                    <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                        <span class="bg-black/70 text-white text-sm font-bold px-4 py-2 rounded-full">Esgotado</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($temDesconto && !$esgotado): ?>
                    <span class="absolute top-3 left-3 bg-red-600 text-white text-xs font-extrabold px-2.5 py-1 rounded-full">
                        -<?= (int)$prod['desconto_pct'] ?>%
                    </span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="h-28 bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center">
                    <span class="text-4xl opacity-50">🍕</span>
                </div>
                <?php endif; ?>

                <div class="p-4 flex-1 flex flex-col">
                    <h3 class="font-extrabold text-gray-900 text-base leading-tight"><?= htmlspecialchars($prod['nome']) ?></h3>
                    <?php if ($prod['descricao']): ?>
                    <p class="text-sm text-gray-500 mt-1 flex-1 leading-relaxed"><?= htmlspecialchars($prod['descricao']) ?></p>
                    <?php endif; ?>

                    <!-- Preços / Tamanhos -->
                    <div class="mt-3">
                        <?php if (!empty($tamanhos)): ?>
                        <?php if ($mesaNum !== null && !$esgotado): ?>
                        <!-- Tamanhos como pills clicáveis -->
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($tamanhos as $tam): ?>
                            <button
                                onclick="addToCart(<?= (int)$prod['id'] ?>, <?= htmlspecialchars(json_encode($prod['nome'])) ?>, <?= (float)$tam['preco'] ?>, <?= htmlspecialchars(json_encode($tam['nome'])) ?>, false, null)"
                                class="size-pill flex-shrink-0 border-2 border-gray-200 text-gray-700 hover:border-red-500 hover:text-red-600 active:bg-red-600 active:text-white active:border-red-600 text-xs font-bold px-3 py-1.5 rounded-xl transition-all">
                                <?= htmlspecialchars($tam['nome']) ?> · R$ <?= fmtPreco((float)$tam['preco']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="space-y-1">
                            <?php foreach ($tamanhos as $tam): ?>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500"><?= htmlspecialchars($tam['nome']) ?></span>
                                <span class="font-bold text-gray-900">R$ <?= fmtPreco((float)$tam['preco']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <?php if ($temDesconto): ?>
                                <span class="text-gray-400 line-through text-sm">R$ <?= fmtPreco($precoBase) ?></span>
                                <?php endif; ?>
                                <span class="font-extrabold text-<?= $temDesconto ? 'red' : 'gray' ?>-<?= $temDesconto ? '600' : '900' ?> text-xl">
                                    R$ <?= fmtPreco($precoFinal) ?>
                                </span>
                            </div>
                            <?php if ($mesaNum !== null && !$esgotado): ?>
                            <button onclick="addToCart(<?= (int)$prod['id'] ?>, <?= htmlspecialchars(json_encode($prod['nome'])) ?>, <?= $precoFinal ?>, null, false, null)"
                                    class="flex-shrink-0 bg-red-600 hover:bg-red-700 active:scale-95 text-white text-sm font-bold px-4 py-2 rounded-xl transition-all">
                                + Adicionar
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($esgotado): ?>
                    <div class="mt-3">
                        <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">
                            Temporariamente indisponível
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

</main>

<!-- ===== FOOTER ===== -->
<footer class="mt-10 bg-white border-t border-gray-100 py-8">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <?php if ($mesaNum !== null): ?>
        <div class="mb-6">
            <div class="inline-flex items-center gap-3 bg-red-50 border border-red-200 rounded-2xl px-6 py-4">
                <span class="text-3xl">🛎️</span>
                <div class="text-left">
                    <p class="font-extrabold text-gray-900">Precisa de ajuda?</p>
                    <p class="text-sm text-gray-500 mt-0.5">Chame o garçom ou use o WhatsApp</p>
                </div>
                <?php if ($whatsapp): ?>
                <a href="https://wa.me/<?= preg_replace('/\D/', '', $whatsapp) ?>?text=<?= urlencode('Olá! Estou na Mesa ' . $mesaNum . ' e preciso de ajuda.') ?>"
                   target="_blank"
                   class="flex-shrink-0 bg-green-500 hover:bg-green-600 text-white font-bold px-4 py-2 rounded-xl text-sm transition-colors">
                    WhatsApp
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex items-center justify-center gap-3 mb-3">
            <div class="w-10 h-10 bg-red-600 rounded-xl flex items-center justify-center text-2xl">🍕</div>
            <div>
                <p class="font-extrabold text-gray-900"><?= htmlspecialchars($nomePizzaria) ?></p>
                <?php $endereco = getConfig('endereco', ''); if ($endereco): ?>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($endereco) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-xs text-gray-300 mt-4">Cardápio digital · Preços sujeitos a alteração sem aviso prévio</p>
    </div>
</footer>

<?php if ($mesaNum !== null): ?>
<!-- ===== BOTÃO FLUTUANTE DO CARRINHO ===== -->
<button id="btn-cart" onclick="toggleCart()"
        style="display:none"
        class="fixed bottom-6 right-6 z-40 bg-red-600 hover:bg-red-700 text-white rounded-full w-16 h-16 shadow-xl flex items-center justify-center transition-all active:scale-95">
    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
    </svg>
    <span id="cart-count" class="absolute -top-1 -right-1 bg-yellow-400 text-gray-900 text-xs font-extrabold rounded-full w-6 h-6 flex items-center justify-center">0</span>
</button>

<!-- ===== OVERLAY DO CARRINHO ===== -->
<div id="cart-overlay" onclick="closeCart()" class="fixed inset-0 bg-black/50 z-40"></div>

<!-- ===== DRAWER DO CARRINHO ===== -->
<div id="cart-drawer" class="fixed bottom-0 left-0 right-0 z-50 bg-white rounded-t-3xl shadow-2xl max-h-[85vh] flex flex-col
                              sm:left-auto sm:right-6 sm:bottom-6 sm:top-auto sm:max-h-[600px] sm:w-96 sm:rounded-2xl">
    <!-- Header drawer -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
        <div class="flex items-center gap-3">
            <h3 class="font-extrabold text-gray-900 text-lg">Seu Pedido</h3>
            <span class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full" id="cart-header-count">0 itens</span>
        </div>
        <button onclick="closeCart()" class="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Lista de itens -->
    <div id="cart-items" class="flex-1 overflow-y-auto px-5 py-3 space-y-3">
        <p class="text-center text-gray-400 text-sm py-8">Nenhum item ainda.<br>Adicione algo do cardápio!</p>
    </div>

    <!-- Footer carrinho -->
    <div class="flex-shrink-0 border-t border-gray-100 px-5 py-4 space-y-3">
        <div class="flex items-center justify-between">
            <span class="font-semibold text-gray-700">Total</span>
            <span id="cart-total" class="font-extrabold text-red-600 text-xl">R$ 0,00</span>
        </div>
        <button onclick="openCheckout()"
                class="w-full bg-red-600 hover:bg-red-700 active:scale-[0.98] text-white font-extrabold py-4 rounded-2xl text-base transition-all shadow-sm">
            Fazer Pedido
        </button>
    </div>
</div>

<!-- ===== MODAL DE CHECKOUT ===== -->
<div id="modal-checkout" class="fixed inset-0 bg-black/60 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div class="bg-white w-full sm:rounded-2xl sm:max-w-md shadow-2xl rounded-t-3xl" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="bg-red-600 px-6 py-5 flex items-center justify-between rounded-t-3xl sm:rounded-t-2xl">
            <div>
                <p class="text-red-200 text-xs font-semibold uppercase tracking-widest mb-0.5">Confirmar pedido</p>
                <h3 class="text-white font-extrabold text-lg"><?= htmlspecialchars($mesaLabel) ?></h3>
            </div>
            <button onclick="closeCheckout()"
                    class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="px-6 pt-5 pb-6 space-y-4">
            <!-- Mesa (read-only) -->
            <div class="flex items-center gap-3 bg-orange-50 border border-orange-200 rounded-xl px-4 py-3">
                <svg class="w-5 h-5 text-orange-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M5 14h14M7 18h10"/>
                </svg>
                <span class="font-bold text-orange-700"><?= htmlspecialchars($mesaLabel) ?></span>
            </div>

            <!-- Nome opcional -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Seu nome <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <input type="text" id="checkout-nome" maxlength="80" placeholder="Ex: João"
                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent">
            </div>

            <!-- Observação -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Alguma observação? <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea id="checkout-obs" rows="2" maxlength="300"
                          placeholder="Ex: sem cebola na pizza, bebida gelada..."
                          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
            </div>

            <!-- Resumo do total -->
            <div class="flex items-center justify-between py-2 border-t border-gray-100">
                <span class="font-semibold text-gray-700">Total do pedido</span>
                <span id="checkout-total" class="font-extrabold text-red-600 text-xl">R$ 0,00</span>
            </div>

            <!-- Erro -->
            <p id="checkout-erro" class="hidden text-sm text-red-600 font-semibold bg-red-50 border border-red-200 rounded-xl px-4 py-3"></p>

            <!-- Botão enviar -->
            <button id="btn-enviar-pedido" onclick="enviarPedido()"
                    class="w-full bg-red-600 hover:bg-red-700 active:scale-[0.98] text-white font-extrabold py-4 rounded-2xl text-base transition-all shadow-sm flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                Enviar Pedido
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function scrollToSection(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const offset = 130; // header + pills height
    const top = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: 'smooth' });
}

// Highlight active category pill on scroll
const sections = document.querySelectorAll('section[id^="sec-"]');
const pills    = document.querySelectorAll('.cat-pill');

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const id = entry.target.id;
            pills.forEach(p => {
                const matches = p.getAttribute('onclick')?.includes(id);
                p.classList.toggle('active', !!matches);
            });
        }
    });
}, { rootMargin: '-30% 0px -60% 0px' });

sections.forEach(s => observer.observe(s));

<?php if ($mesaNum !== null): ?>
// ══════════════════════════════════════════════════════════════
// CARRINHO
// ══════════════════════════════════════════════════════════════
const MESA_NUM  = <?= json_encode((string)$mesaNum) ?>;
const MESA_LABEL = <?= json_encode($mesaLabel) ?>;
const SESSION_KEY = 'cart_mesa_' + MESA_NUM;

// Carrega estado do sessionStorage
let cartItems = [];
try {
    const saved = sessionStorage.getItem(SESSION_KEY);
    if (saved) cartItems = JSON.parse(saved);
} catch(e) {}

function saveCart() {
    try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(cartItems)); } catch(e) {}
}

function fmtBRL(v) {
    return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function addToCart(prodId, nome, preco, tamanhoNome = null, isCombo = false, comboId = null) {
    // Tenta agrupar se o mesmo item já existir
    const idx = cartItems.findIndex(i =>
        i.id == prodId &&
        i.comboId == comboId &&
        i.tamanhoNome == tamanhoNome &&
        i.isCombo == isCombo
    );
    if (idx >= 0) {
        cartItems[idx].quantidade++;
    } else {
        cartItems.push({ id: prodId, comboId, nome, preco: parseFloat(preco), quantidade: 1, tamanhoNome, isCombo });
    }
    saveCart();
    renderCart();
    showCartBtn();

    // Feedback visual rápido
    const btn = event && event.currentTarget ? event.currentTarget : null;
    if (btn) {
        const orig = btn.textContent;
        btn.textContent = '✓ Adicionado';
        btn.disabled = true;
        setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 700);
    }
}

function removeFromCart(index) {
    cartItems.splice(index, 1);
    saveCart();
    renderCart();
    if (cartItems.length === 0) hideCartBtn();
}

function updateQty(index, delta) {
    cartItems[index].quantidade += delta;
    if (cartItems[index].quantidade <= 0) {
        removeFromCart(index);
        return;
    }
    saveCart();
    renderCart();
}

function getTotal() {
    return cartItems.reduce((sum, i) => sum + i.preco * i.quantidade, 0);
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const countEl   = document.getElementById('cart-count');
    const headerEl  = document.getElementById('cart-header-count');
    const totalEl   = document.getElementById('cart-total');

    const totalQty = cartItems.reduce((s, i) => s + i.quantidade, 0);
    const total    = getTotal();

    countEl.textContent  = totalQty;
    headerEl.textContent = totalQty + (totalQty === 1 ? ' item' : ' itens');
    totalEl.textContent  = 'R$ ' + fmtBRL(total);

    if (cartItems.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 text-sm py-8">Nenhum item ainda.<br>Adicione algo do cardápio!</p>';
        return;
    }

    container.innerHTML = cartItems.map((item, idx) => `
        <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-3">
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 text-sm leading-tight">${esc(item.nome)}</p>
                ${item.tamanhoNome ? `<p class="text-xs text-gray-500 mt-0.5">${esc(item.tamanhoNome)}</p>` : ''}
                <p class="text-red-600 font-bold text-sm mt-1">R$ ${fmtBRL(item.preco * item.quantidade)}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0 mt-0.5">
                <button onclick="updateQty(${idx}, -1)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition-colors font-bold text-lg leading-none">−</button>
                <span class="font-bold text-gray-900 w-5 text-center text-sm">${item.quantidade}</span>
                <button onclick="updateQty(${idx}, 1)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-green-50 hover:border-green-300 hover:text-green-600 transition-colors font-bold text-lg leading-none">+</button>
            </div>
        </div>
    `).join('');
}

function showCartBtn() {
    document.getElementById('btn-cart').style.display = 'flex';
}
function hideCartBtn() {
    document.getElementById('btn-cart').style.display = 'none';
}

function toggleCart() {
    const drawer  = document.getElementById('cart-drawer');
    const overlay = document.getElementById('cart-overlay');
    if (drawer.classList.contains('open')) {
        closeCart();
    } else {
        drawer.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeCart() {
    const drawer  = document.getElementById('cart-drawer');
    const overlay = document.getElementById('cart-overlay');
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}

function openCheckout() {
    closeCart();
    const modal = document.getElementById('modal-checkout');
    document.getElementById('checkout-total').textContent = 'R$ ' + fmtBRL(getTotal());
    document.getElementById('checkout-nome').value = '';
    document.getElementById('checkout-obs').value  = '';
    document.getElementById('checkout-erro').classList.add('hidden');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeCheckout() {
    document.getElementById('modal-checkout').classList.remove('open');
    document.body.style.overflow = '';
}

async function enviarPedido() {
    if (cartItems.length === 0) return;

    const nome = document.getElementById('checkout-nome').value.trim();
    const obs  = document.getElementById('checkout-obs').value.trim();
    const erroEl = document.getElementById('checkout-erro');
    const btn    = document.getElementById('btn-enviar-pedido');

    erroEl.classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Enviando…`;

    const itens = cartItems.map(item => ({
        produto_id: item.isCombo ? null : item.id,
        combo_id:   item.isCombo ? item.comboId : null,
        quantidade: item.quantidade,
        tamanho_nome: item.tamanhoNome || null,
        ingredientes_removidos: [],
        adicionais: [],
    }));

    const payload = {
        tipo_entrega:      'mesa',
        mesa:              MESA_NUM,
        cliente_nome:      nome || (MESA_LABEL),
        cliente_telefone:  'MESA-' + MESA_NUM,
        endereco:          MESA_LABEL,
        bairro:            null,
        observacao:        obs,
        itens:             itens,
        total:             getTotal(),
        taxa_entrega:      0,
        forma_pagamento:   'mesa',
        cupom_codigo:      '',
    };

    try {
        const res  = await fetch('/pizzaria/api/pedido.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.sucesso) {
            closeCheckout();
            // Limpa carrinho
            cartItems = [];
            saveCart();
            renderCart();
            hideCartBtn();
            // Mostra tela de sucesso
            const pedidoId = data.pedido_id;
            const tel = 'MESA-' + MESA_NUM;
            document.getElementById('sucesso-mesa-label').textContent = MESA_LABEL;
            document.getElementById('sucesso-pedido-numero').textContent = 'Pedido #' + pedidoId;
            const linkEl = document.getElementById('sucesso-link-acompanhar');
            linkEl.href = '/pizzaria/acompanhar.php?pedido=' + pedidoId + '&tel=' + encodeURIComponent(tel);
            document.getElementById('tela-sucesso').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            erroEl.textContent = data.mensagem || 'Erro ao enviar pedido. Tente novamente.';
            erroEl.classList.remove('hidden');
        }
    } catch(e) {
        erroEl.textContent = 'Erro de conexão. Verifique sua internet e tente novamente.';
        erroEl.classList.remove('hidden');
    }

    btn.disabled = false;
    btn.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Enviar Pedido`;
}

function voltarCardapio() {
    document.getElementById('tela-sucesso').style.display = 'none';
    document.body.style.overflow = '';
}

// Init: restaura estado do carrinho da sessão
(function init() {
    if (cartItems.length > 0) {
        renderCart();
        showCartBtn();
    }
})();
<?php endif; ?>
</script>
</body>
</html>
