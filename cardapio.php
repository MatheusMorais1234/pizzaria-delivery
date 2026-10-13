<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['carrinho'])) { $_SESSION['carrinho'] = []; }

$pdo = getPDO();

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY ordem')->fetchAll();

$produtos = $pdo->query('
    SELECT p.*, c.nome AS categoria_nome
    FROM produtos p
    JOIN categorias c ON c.id = p.categoria_id
    WHERE p.ativo = 1
    ORDER BY c.ordem, p.nome
')->fetchAll();

$ingRows = $pdo->query('SELECT produto_id, nome FROM produto_ingredientes ORDER BY id')->fetchAll();
$ingredientesPorProduto = [];
foreach ($ingRows as $r) { $ingredientesPorProduto[$r['produto_id']][] = $r['nome']; }

$tamRows = $pdo->query('SELECT * FROM produto_tamanhos WHERE ativo=1 ORDER BY produto_id, ordem')->fetchAll();
$tamanhosPorProduto = [];
foreach ($tamRows as $t) { $tamanhosPorProduto[$t['produto_id']][] = $t; }

$topIds = $pdo->query('
    SELECT produto_id FROM pedido_itens
    GROUP BY produto_id ORDER BY SUM(quantidade) DESC LIMIT 3
')->fetchAll(PDO::FETCH_COLUMN);
$topIds = array_flip($topIds);

$combos = $pdo->query('SELECT * FROM combos WHERE ativo = 1 ORDER BY ordem, id')->fetchAll();

$totalCarrinho = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
$aberto        = estaAberto();
$tempoEntrega  = (int) getConfig('tempo_entrega_min', '40');
$whatsapp      = getConfig('whatsapp', '');
$tempoEspera   = (int) getConfig('tempo_espera_atual', '0');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio — Pizzaria Bella Napoli</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }

        /* Hero background */
        .hero-bg {
            background-image:
                linear-gradient(to bottom, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.45) 60%, rgba(0,0,0,0.75) 100%),
                url('https://images.unsplash.com/photo-1513104890138-7c749659a591?w=1600&q=85');
            background-size: cover;
            background-position: center;
        }

        /* Fade-in */
        .fade-in { opacity: 0; transform: translateY(20px); transition: opacity .6s ease, transform .6s ease; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }
        .delay-1 { transition-delay: .1s; }
        .delay-2 { transition-delay: .2s; }
        .delay-3 { transition-delay: .3s; }

        /* Cart badge bounce */
        @keyframes bounce-badge {
            0%,100% { transform: scale(1); }
            50%      { transform: scale(1.4); }
        }
        .cart-bounce { animation: bounce-badge .35s ease; }

        /* Toast */
        @keyframes wa-pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(37,211,102,.45); }
            50%      { box-shadow: 0 0 0 12px rgba(37,211,102,0); }
        }
        .wa-pulse { animation: wa-pulse 2.2s ease-in-out infinite; }

        /* Card hover */
        .produto-card { transition: transform .25s ease, box-shadow .25s ease; }
        .produto-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,.12); }
        .produto-card:hover .card-img { transform: scale(1.06); }
        .card-img { transition: transform .45s ease; }

        /* Filtro ativo */
        .filtro-btn { transition: all .2s ease; }
        .filtro-btn.ativo {
            background: #DC2626;
            color: #fff;
            border-color: #DC2626;
            box-shadow: 0 4px 14px rgba(220,38,38,.35);
        }

        /* Scrollbar fina */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<!-- ════════════════════════════════════
     NAVBAR
════════════════════════════════════ -->
<nav class="bg-gray-950 shadow-xl sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 py-3.5 flex items-center justify-between gap-4">

        <!-- Logo -->
        <a href="<?= BASE ?>/" class="flex items-center gap-2.5 shrink-0">
            <span class="text-2xl leading-none">🍕</span>
            <div class="leading-none">
                <span class="text-white font-black text-lg tracking-tight block">Bella Napoli</span>
                <span class="text-gray-500 text-[10px] font-medium">Pizzaria Artesanal</span>
            </div>
        </a>

        <!-- Links centro -->
        <div class="hidden sm:flex items-center gap-5 flex-1 justify-center">
            <a href="<?= BASE ?>/"
               class="text-gray-400 hover:text-white text-sm font-medium transition-colors flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Início
            </a>
            <a href="<?= BASE ?>/historico.php"
               class="text-gray-400 hover:text-white text-sm font-medium transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Meus Pedidos
            </a>
        </div>

        <!-- Status + Carrinho -->
        <div class="flex items-center gap-3 shrink-0">
            <!-- Badge aberto/fechado -->
            <?php if ($aberto): ?>
            <span class="hidden sm:flex items-center gap-1.5 text-green-400 text-xs font-semibold">
                <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                Aberto · ~<?= $tempoEntrega ?>min
            </span>
            <?php else: ?>
            <span class="hidden sm:flex items-center gap-1.5 text-red-400 text-xs font-semibold">
                <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span>
                Fechado
            </span>
            <?php endif; ?>

            <!-- Carrinho -->
            <a href="<?= BASE ?>/carrinho.php"
               class="relative flex items-center gap-2 bg-red-600 hover:bg-red-700 transition-colors text-white font-bold px-4 py-2.5 rounded-full shadow-lg shadow-red-900/30 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9M9 21a1 1 0 100-2 1 1 0 000 2zm6 0a1 1 0 100-2 1 1 0 000 2z"/>
                </svg>
                <span>Carrinho</span>
                <span id="cart-count"
                      class="absolute -top-1.5 -right-1.5 bg-yellow-400 text-gray-900 text-[10px] font-black rounded-full w-5 h-5 flex items-center justify-center <?= $totalCarrinho === 0 ? 'hidden' : '' ?>">
                    <?= $totalCarrinho ?>
                </span>
            </a>
        </div>
    </div>
</nav>

<?php if (!$aberto): ?>
<!-- ════ MODAL LOJA FECHADA ════ -->
<div id="modal-fechado"
     class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 px-8 py-8 text-center">
            <div class="w-20 h-20 bg-gray-700/50 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">🕐</div>
            <h2 class="text-white font-black text-2xl font-display">Estamos fechados</h2>
            <p class="text-gray-400 text-sm mt-1">no momento não aceitamos pedidos</p>
        </div>
        <div class="px-8 py-6 text-center">
            <div class="bg-orange-50 border border-orange-100 rounded-2xl px-5 py-4 mb-5 inline-block">
                <p class="text-orange-500 text-xs font-semibold uppercase tracking-wide mb-1">Próxima abertura</p>
                <p class="text-gray-900 font-black text-3xl"><?= getConfig('hora_abertura','18:00') ?>h</p>
            </div>
            <p class="text-gray-500 text-sm mb-6">
                Horário de funcionamento:<br>
                <strong class="text-gray-700"><?= getConfig('hora_abertura','18:00') ?> às <?= getConfig('hora_fechamento','23:00') ?></strong>
            </p>
            <button onclick="document.getElementById('modal-fechado').classList.add('hidden')"
                    class="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold py-3.5 rounded-xl transition-colors text-sm">
                Ver cardápio mesmo assim
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════
     HERO
════════════════════════════════════ -->
<section class="hero-bg py-14 px-4 text-center">
    <div class="max-w-2xl mx-auto">
        <p class="text-red-400 text-xs font-semibold uppercase tracking-[0.2em] mb-3 fade-in">
            Forno a lenha · Ingredientes frescos
        </p>
        <h1 class="font-display text-white text-4xl sm:text-5xl font-black leading-tight mb-4 fade-in delay-1">
            Escolha sua <span class="text-red-400">pizza</span> favorita
        </h1>
        <p class="text-white/60 text-base mb-8 fade-in delay-2">
            Entrega em ~<?= $tempoEntrega ?> minutos direto na sua porta.
        </p>

        <!-- Busca -->
        <div class="max-w-md mx-auto relative fade-in delay-3">
            <svg class="w-5 h-5 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
            </svg>
            <input type="search" id="busca"
                   placeholder="Buscar pizza, bebida…"
                   oninput="buscar(this.value)"
                   class="w-full pl-12 pr-5 py-3.5 rounded-2xl text-gray-800 font-medium placeholder-gray-400
                          shadow-xl focus:outline-none focus:ring-4 focus:ring-red-500/30 text-sm bg-white">
        </div>
    </div>
</section>

<?php if ($tempoEspera > 0): ?>
<!-- ══ BANNER TEMPO DE ESPERA ══ -->
<div class="bg-amber-50 border-b border-amber-200 py-2.5 px-4 text-center text-sm font-semibold text-amber-700">
    ⏱ Tempo estimado hoje: ~<?= $tempoEspera ?> min
</div>
<?php endif; ?>

<!-- ════════════════════════════════════
     FILTROS
════════════════════════════════════ -->
<div class="bg-white border-b border-gray-100 sticky top-[61px] z-40 shadow-sm">
    <div class="max-w-6xl mx-auto px-4 py-3">
        <div class="flex gap-2 overflow-x-auto pb-0.5 scrollbar-hide" id="filtros" style="scrollbar-width:none;">
            <button onclick="filtrar('todos')"
                    class="filtro-btn ativo flex-shrink-0 px-5 py-2 rounded-full font-semibold text-sm border-2 border-red-600 text-red-600"
                    data-cat="todos">
                Todos
            </button>
            <?php foreach ($categorias as $cat): ?>
            <button onclick="filtrar('<?= $cat['id'] ?>')"
                    class="filtro-btn flex-shrink-0 px-5 py-2 rounded-full font-semibold text-sm border-2 border-gray-200 text-gray-500 hover:border-red-500 hover:text-red-600"
                    data-cat="<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['nome']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($combos)): ?>
<!-- ════════════════════════════════════
     COMBOS ESPECIAIS
════════════════════════════════════ -->
<section class="max-w-6xl mx-auto px-4 pt-8 pb-2 w-full">
    <div class="flex items-center gap-3 mb-5">
        <div class="w-8 h-8 bg-orange-100 rounded-xl flex items-center justify-center text-lg">🍕</div>
        <div>
            <h2 class="font-extrabold text-gray-900 text-xl">Combos Especiais</h2>
            <p class="text-gray-400 text-xs">Ofertas exclusivas por tempo limitado</p>
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($combos as $c): ?>
        <div class="bg-white rounded-2xl border border-orange-100 shadow-sm overflow-hidden flex flex-col combo-card"
             style="border-top: 3px solid #f97316;">
            <?php if ($c['imagem_url']): ?>
            <div class="h-40 overflow-hidden bg-gray-100">
                <img src="<?= htmlspecialchars($c['imagem_url']) ?>" alt="<?= htmlspecialchars($c['nome']) ?>"
                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                     onerror="this.parentElement.style.display='none'">
            </div>
            <?php endif; ?>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex items-start gap-2 mb-1">
                    <h3 class="font-bold text-gray-900 text-base leading-tight flex-1"><?= htmlspecialchars($c['nome']) ?></h3>
                    <?php if ($c['badge']): ?>
                    <span class="text-[10px] font-extrabold bg-orange-500 text-white px-2 py-0.5 rounded-full uppercase tracking-wide flex-shrink-0"><?= htmlspecialchars($c['badge']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($c['descricao']): ?>
                <p class="text-gray-500 text-sm flex-1 mb-3 leading-relaxed line-clamp-2"><?= htmlspecialchars($c['descricao']) ?></p>
                <?php endif; ?>
                <div class="flex items-center justify-between mt-auto gap-2">
                    <div class="leading-none">
                        <?php if ($c['preco_original'] > 0): ?>
                        <p class="text-gray-400 text-xs line-through mb-0.5">R$ <?= number_format($c['preco_original'], 2, ',', '.') ?></p>
                        <?php endif; ?>
                        <p class="text-orange-600 font-black text-xl">R$ <?= number_format($c['preco_combo'], 2, ',', '.') ?></p>
                    </div>
                    <button
                        onclick="<?= $aberto ? "adicionarComboAoCarrinho({$c['id']}, '".addslashes($c['nome'])."', {$c['preco_combo']}, '".addslashes($c['imagem_url'] ?? '')."')" : "mostrarFechado()" ?>"
                        class="<?= $aberto ? 'bg-orange-500 hover:bg-orange-600 shadow-md' : 'bg-gray-200 cursor-not-allowed' ?> text-white text-xs font-bold px-4 py-2.5 rounded-full transition-all flex items-center gap-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Adicionar
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════════
     GRID DE PRODUTOS
════════════════════════════════════ -->
<main class="max-w-6xl mx-auto px-4 py-10 flex-1 w-full">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" id="produtos-grid">
        <?php foreach ($produtos as $p):
            $descPct    = (float)($p['desconto_pct'] ?? 0);
            $precoFinal = $descPct > 0 ? round($p['preco'] * (1 - $descPct / 100), 2) : $p['preco'];
            $rank       = isset($topIds[$p['id']]) ? array_search($p['id'], array_keys($topIds)) + 1 : null;
            $badges     = [
                1 => ['🔥 Mais Pedido', 'bg-red-600'],
                2 => ['⭐ Popular',     'bg-orange-500'],
                3 => ['👍 Destaque',    'bg-yellow-500'],
            ];
            $ingsCard    = $ingredientesPorProduto[$p['id']] ?? [];
            $temTamanhos = !empty($tamanhosPorProduto[$p['id']]);
            $esgotado    = ($p['estoque'] !== null && (int)$p['estoque'] === 0);
            $onclickBtn  = $esgotado ? '' : ($aberto
                ? ($temTamanhos
                    ? "abrirSizePicker({$p['id']}, '".addslashes($p['nome'])."', '".addslashes($p['imagem_url'] ?? '')."')"
                    : "adicionarAoCarrinho({$p['id']}, '".addslashes($p['nome'])."', {$precoFinal}, '".addslashes($p['imagem_url'] ?? '')."')")
                : "mostrarFechado()");
            if ($temTamanhos) {
                $precos   = array_column($tamanhosPorProduto[$p['id']], 'preco');
                $minPreco = min($precos);
                $maxPreco = max($precos);
            }
        ?>
        <div class="produto-card bg-white rounded-2xl shadow-sm overflow-hidden flex flex-col border border-gray-100"
             data-cat="<?= $p['categoria_id'] ?>">

            <!-- Imagem -->
            <div class="relative h-48 overflow-hidden bg-gray-100">
                <img src="<?= htmlspecialchars($p['imagem_url'] ?? '') ?>"
                     alt="<?= htmlspecialchars($p['nome']) ?>"
                     class="card-img w-full h-full object-cover"
                     onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&q=80'">

                <!-- Overlay gradiente suave no rodapé da imagem -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent pointer-events-none"></div>

                <!-- Badge mais pedido / popular -->
                <?php if ($rank && isset($badges[$rank])): ?>
                <span class="absolute top-2.5 left-2.5 <?= $badges[$rank][1] ?> text-white text-[10px] font-extrabold px-2.5 py-1 rounded-full shadow-md">
                    <?= $badges[$rank][0] ?>
                </span>
                <?php endif; ?>

                <!-- Badge desconto -->
                <?php if ($descPct > 0): ?>
                <span class="absolute top-2.5 right-2.5 bg-orange-500 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-full shadow-md">
                    <?= number_format($descPct, 0) ?>% OFF
                </span>
                <?php endif; ?>

                <!-- Overlay esgotado -->
                <?php if ($esgotado): ?>
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                    <span class="bg-gray-900 text-white text-xs font-extrabold px-3 py-1.5 rounded-full uppercase tracking-wide">Esgotado</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Conteúdo -->
            <div class="p-4 flex flex-col flex-1">
                <span class="text-[10px] font-semibold text-orange-500 uppercase tracking-widest mb-1">
                    <?= htmlspecialchars($p['categoria_nome']) ?>
                </span>
                <h3 class="font-bold text-gray-900 text-base leading-tight mb-1">
                    <?= htmlspecialchars($p['nome']) ?>
                </h3>
                <p class="text-gray-500 text-sm flex-1 mb-3 leading-relaxed line-clamp-2">
                    <?= htmlspecialchars($p['descricao']) ?>
                </p>

                <!-- Ingredientes expansível -->
                <?php if ($ingsCard): ?>
                <div class="mb-3">
                    <button type="button"
                            onclick="toggleIng(this)"
                            class="text-xs text-gray-400 hover:text-red-500 flex items-center gap-1 transition-colors">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Ver ingredientes</span>
                    </button>
                    <div class="hidden mt-2 flex flex-wrap gap-1">
                        <?php foreach ($ingsCard as $ing): ?>
                        <span class="text-[10px] bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-full font-medium">
                            <?= htmlspecialchars($ing) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Preço + botão -->
                <div class="flex items-center justify-between mt-auto gap-2">
                    <?php if ($temTamanhos): ?>
                    <div class="leading-none">
                        <p class="text-gray-400 text-xs mb-0.5">A partir de</p>
                        <p class="text-red-600 font-black text-xl">R$ <?= number_format($minPreco, 2, ',', '.') ?></p>
                        <?php if ($minPreco < $maxPreco): ?>
                        <p class="text-gray-400 text-xs">até R$ <?= number_format($maxPreco, 2, ',', '.') ?></p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="leading-none">
                        <?php if ($descPct > 0): ?>
                        <p class="text-gray-400 text-xs line-through mb-0.5">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                        <?php endif; ?>
                        <p class="text-red-600 font-black text-xl">R$ <?= number_format($precoFinal, 2, ',', '.') ?></p>
                    </div>
                    <?php endif; ?>

                    <button
                        onclick="<?= $esgotado ? '' : $onclickBtn ?>"
                        <?= $esgotado ? 'disabled' : '' ?>
                        class="<?= $esgotado
                            ? 'bg-gray-300 cursor-not-allowed text-gray-500'
                            : ($aberto ? 'bg-red-600 hover:bg-red-700 shadow-md shadow-red-100 hover:shadow-red-200' : 'bg-gray-200 cursor-not-allowed') ?>
                               text-white text-xs font-bold px-4 py-2.5 rounded-full transition-all flex items-center gap-1 shrink-0">
                        <?php if (!$esgotado): ?>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php endif; ?>
                        <?= $esgotado ? 'Esgotado' : ($temTamanhos ? 'Escolher' : 'Adicionar') ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p id="sem-produtos" class="hidden text-center text-gray-400 py-20 text-base">
        Nenhum produto encontrado. Tente outra busca.
    </p>
</main>

<!-- ════════════════════════════════════
     FOOTER MINI
════════════════════════════════════ -->
<footer class="bg-gray-950 text-gray-600 text-center py-5 mt-auto text-xs border-t border-gray-900">
    <p>&copy; <?= date('Y') ?> Pizzaria Bella Napoli &mdash; Todos os direitos reservados</p>
</footer>

<!-- Toast -->
<div id="toast"
     class="fixed bottom-24 right-5 bg-gray-900 text-white px-5 py-3 rounded-2xl shadow-2xl
            opacity-0 translate-y-4 transition-all duration-300 pointer-events-none z-50 flex items-center gap-2.5 text-sm">
    <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    <span id="toast-msg">Item adicionado!</span>
</div>

<!-- WhatsApp flutuante -->
<?php if ($whatsapp): ?>
<a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>?text=Olá! Gostaria de fazer um pedido 🍕"
   target="_blank" rel="noopener"
   class="wa-pulse fixed bottom-6 right-5 z-50 w-13 h-13 bg-green-500 hover:bg-green-600 text-white rounded-full
          flex items-center justify-center shadow-2xl transition-transform hover:scale-110"
   style="width:52px;height:52px;"
   aria-label="WhatsApp">
    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
</a>
<?php endif; ?>

<!-- Modal de escolha de tamanho -->
<div id="modal-tamanho" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" onclick="fecharSizePicker()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="bg-red-600 px-5 py-4 flex items-center justify-between">
            <h3 class="text-white font-extrabold text-base" id="size-picker-nome">Escolha o tamanho</h3>
            <button onclick="fecharSizePicker()" class="text-white/80 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-5 space-y-2" id="size-options"></div>
    </div>
</div>

<script>
const TAMANHOS = <?= json_encode($tamanhosPorProduto, JSON_UNESCAPED_UNICODE) ?>;

// ── Carrinho ──────────────────────────────────────────
let carrinho = JSON.parse(localStorage.getItem('pizzaria_carrinho') || '[]');
atualizarContador();

function adicionarAoCarrinho(id, nome, preco, imagem) {
    const plain = carrinho.find(i => i.id === id && !(i.ingredientesRemovidos?.length) && !(i.adicionais?.length));
    if (plain) { plain.quantidade++; }
    else { carrinho.push({ id, nome, preco, precoBase: preco, imagem, quantidade: 1, ingredientesRemovidos: [], adicionais: [] }); }
    localStorage.setItem('pizzaria_carrinho', JSON.stringify(carrinho));
    atualizarContador();
    mostrarToast('🍕 ' + nome + ' adicionado!');
}

function adicionarComboAoCarrinho(comboId, nome, preco, imagem) {
    const existing = carrinho.find(i => i.comboId === comboId && !(i.ingredientesRemovidos?.length) && !(i.adicionais?.length));
    if (existing) { existing.quantidade++; }
    else {
        carrinho.push({
            id: null, comboId, nome, preco, precoBase: preco,
            imagem, quantidade: 1, ingredientesRemovidos: [], adicionais: [],
            isCombo: true
        });
    }
    localStorage.setItem('pizzaria_carrinho', JSON.stringify(carrinho));
    atualizarContador();
    mostrarToast('🍕 ' + nome + ' adicionado!');
}

function atualizarContador() {
    const total = carrinho.reduce((s, i) => s + i.quantidade, 0);
    const badge = document.getElementById('cart-count');
    badge.textContent = total;
    if (total > 0) {
        badge.classList.remove('hidden');
        badge.classList.add('cart-bounce');
        setTimeout(() => badge.classList.remove('cart-bounce'), 400);
    } else {
        badge.classList.add('hidden');
    }
}

function mostrarFechado() {
    mostrarToast('🕐 Fechado agora. Abrimos às <?= getConfig('hora_abertura','18:00') ?>h!');
}

function mostrarToast(msg) {
    const toast = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    toast.classList.remove('opacity-0', 'translate-y-4');
    toast.classList.add('opacity-100', 'translate-y-0');
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4');
        toast.classList.remove('opacity-100', 'translate-y-0');
    }, 2500);
}

// ── Helpers ───────────────────────────────────────────
function fmt(v) {
    return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Size picker ───────────────────────────────────────
let _sizeProdId, _sizeNome, _sizeImagem;

function abrirSizePicker(id, nome, imagem) {
    _sizeProdId = id; _sizeNome = nome; _sizeImagem = imagem;
    const sizes = TAMANHOS[id] || [];
    document.getElementById('size-picker-nome').textContent = nome;
    document.getElementById('size-options').innerHTML = sizes.map(t => `
        <button onclick="adicionarComTamanho('${esc(t.nome)}', ${t.preco})"
                class="w-full flex items-center justify-between px-4 py-3 border-2 border-gray-200 hover:border-red-500 hover:bg-red-50 rounded-xl transition-all text-left">
            <span class="font-bold text-gray-800">${esc(t.nome)}</span>
            <span class="font-extrabold text-red-600">R$ ${parseFloat(t.preco).toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>
        </button>`).join('');
    const m = document.getElementById('modal-tamanho');
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function fecharSizePicker() {
    const m = document.getElementById('modal-tamanho');
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}

function adicionarComTamanho(tamanhoNome, tamanhoPreco) {
    fecharSizePicker();
    const plain = carrinho.find(i => i.id === _sizeProdId && i.tamanhoNome === tamanhoNome && !(i.ingredientesRemovidos?.length) && !(i.adicionais?.length));
    if (plain) { plain.quantidade++; }
    else {
        carrinho.push({
            id: _sizeProdId, nome: _sizeNome + ' (' + tamanhoNome + ')',
            preco: parseFloat(tamanhoPreco), precoBase: parseFloat(tamanhoPreco),
            imagem: _sizeImagem, quantidade: 1,
            ingredientesRemovidos: [], adicionais: [],
            tamanhoNome: tamanhoNome,
            produtoId: _sizeProdId
        });
    }
    localStorage.setItem('pizzaria_carrinho', JSON.stringify(carrinho));
    atualizarContador();
    mostrarToast('🍕 ' + _sizeNome + ' (' + tamanhoNome + ') adicionado!');
}

// ── Ingredientes toggle ───────────────────────────────
function toggleIng(btn) {
    const lista = btn.nextElementSibling;
    const aberto = !lista.classList.contains('hidden');
    lista.classList.toggle('hidden');
    btn.querySelector('span').textContent = aberto ? 'Ver ingredientes' : 'Ocultar ingredientes';
}

// ── Filtro + busca ────────────────────────────────────
let catAtiva = 'todos';

function filtrar(catId) {
    catAtiva = catId;
    document.querySelectorAll('.filtro-btn').forEach(btn => {
        const ativo = btn.dataset.cat === catId;
        btn.classList.toggle('ativo', ativo);
        btn.classList.toggle('border-gray-200', !ativo);
        btn.classList.toggle('text-gray-500', !ativo);
        btn.classList.toggle('border-red-600', ativo);
    });
    document.getElementById('busca').value = '';
    aplicarFiltros('');
}

function buscar(termo) {
    catAtiva = 'todos';
    document.querySelectorAll('.filtro-btn').forEach(btn => {
        const ativo = btn.dataset.cat === 'todos';
        btn.classList.toggle('ativo', ativo);
        btn.classList.toggle('border-gray-200', !ativo);
        btn.classList.toggle('text-gray-500', !ativo);
        btn.classList.toggle('border-red-600', ativo);
    });
    aplicarFiltros(termo);
}

function aplicarFiltros(termo) {
    const q = termo.toLowerCase().trim();
    const cards = document.querySelectorAll('.produto-card');
    let visiveis = 0;
    cards.forEach(card => {
        const nome = card.querySelector('h3').textContent.toLowerCase();
        const desc = card.querySelector('p.text-gray-500')?.textContent.toLowerCase() ?? '';
        const catOk   = catAtiva === 'todos' || card.dataset.cat === catAtiva;
        const buscaOk = !q || nome.includes(q) || desc.includes(q);
        card.style.display = (catOk && buscaOk) ? '' : 'none';
        if (catOk && buscaOk) visiveis++;
    });
    document.getElementById('sem-produtos').classList.toggle('hidden', visiveis > 0);
}

// ── Animações fade-in ─────────────────────────────────
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
}, { threshold: 0.08 });
document.querySelectorAll('.fade-in').forEach(el => obs.observe(el));
</script>
</body>
</html>
