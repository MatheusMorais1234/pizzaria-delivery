<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pdo = getPDO();

// Cria tabela combos se não existir (auto-migration)
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

// Combos reais do banco
$combos = $pdo->query('
    SELECT * FROM combos WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT 6
')->fetchAll();

// Promoções ativas
$promocoes = $pdo->query('
    SELECT p.*, c.nome AS categoria_nome
    FROM produtos p
    JOIN categorias c ON c.id = p.categoria_id
    WHERE p.ativo = 1 AND p.desconto_pct > 0
    ORDER BY p.desconto_pct DESC
    LIMIT 4
')->fetchAll();

// Destaques (top 3 mais pedidos)
$destaques = $pdo->query('
    SELECT p.*, c.nome AS categoria_nome,
           COALESCE(SUM(pi.quantidade), 0) AS total_pedidos
    FROM produtos p
    JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN pedido_itens pi ON pi.produto_id = p.id
    WHERE p.ativo = 1
    GROUP BY p.id
    ORDER BY total_pedidos DESC, p.id ASC
    LIMIT 3
')->fetchAll();

$aberto        = estaAberto();
$tempoEntrega  = (int) getConfig('tempo_entrega_min', '40');
$telefone      = getConfig('telefone',  '(11) 3456-7890');
$whatsapp      = getConfig('whatsapp',  '5511934567890');
$endereco      = getConfig('endereco',  'Rua das Pizzas, 123 — Centro, São Paulo/SP');
$instagram     = getConfig('instagram', 'bellanapoli.pizza');
$facebook      = getConfig('facebook',  'pizzariabellanapoli');
$mapsEmbed     = getConfig('maps_embed', '');
$imagemPizzaiolo   = getConfig('imagem_pizzaiolo', '');
$sobreNosTexto     = getConfig('sobre_nos_texto', '');
$sobreNosAnos      = getConfig('sobre_nos_anos', '15+');
$sobreNosSabores   = getConfig('sobre_nos_sabores', '30+');
$aniversBeneficio  = getConfig('aniversariante_beneficio', '');

$horariosSemana  = getConfig('horarios_semana', '');
$horariosParsed  = $horariosSemana ? json_decode($horariosSemana, true) : null;
$diasNomes       = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$diaAtual        = (int) date('w');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizzaria Bella Napoli — A pizza mais gostosa da cidade</title>
    <meta name="description" content="Pizzaria artesanal com forno a lenha, ingredientes frescos e entrega rápida. Peça agora!">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pizza: { red: '#C41E1E', orange: '#EA580C', cream: '#FFF8F0', dark: '#1C0A00' }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        html { scroll-behavior: smooth; }

        .hero-bg {
            background-image:
                linear-gradient(to bottom, rgba(0,0,0,0.60) 0%, rgba(0,0,0,0.35) 50%, rgba(0,0,0,0.75) 100%),
                url('https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=1800&q=90');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .fade-in {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }
        .fade-in.visible { opacity: 1; transform: translateY(0); }
        .delay-1 { transition-delay: 0.12s; }
        .delay-2 { transition-delay: 0.24s; }
        .delay-3 { transition-delay: 0.36s; }

        @keyframes bounce-y {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50%       { transform: translateX(-50%) translateY(8px); }
        }
        .scroll-arrow { animation: bounce-y 1.6s ease-in-out infinite; }

        @keyframes wa-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(37,211,102,0.45); }
            50%       { box-shadow: 0 0 0 14px rgba(37,211,102,0); }
        }
        .wa-pulse { animation: wa-pulse 2.2s ease-in-out infinite; }

        #navbar { transition: background-color 0.3s ease, box-shadow 0.3s ease; }

        .combo-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .combo-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.12); }
        .combo-card:hover .combo-img { transform: scale(1.06); }
        .combo-img { transition: transform 0.5s ease; }

        .promo-card:hover { transform: translateY(-4px); }
        .promo-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .promo-card:hover { box-shadow: 0 20px 40px rgba(0,0,0,0.1); }

        #mobile-menu { transition: max-height 0.3s ease, opacity 0.3s ease; }

        .gallery-img { transition: transform 0.5s ease; }
        .gallery-img:hover { transform: scale(1.04); }
    </style>
</head>
<body class="bg-white overflow-x-hidden">

<!-- ════════════════════════════════════
     NAVBAR
════════════════════════════════════ -->
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">

            <!-- Logo -->
            <a href="#hero" class="flex items-center gap-2.5 shrink-0">
                <span class="text-3xl leading-none">🍕</span>
                <div class="leading-none">
                    <span class="text-white font-black text-xl tracking-tight block">Bella Napoli</span>
                    <span class="text-red-300 text-[11px] font-medium">Pizzaria Artesanal</span>
                </div>
            </a>

            <!-- Links desktop -->
            <div class="hidden lg:flex items-center gap-7">
                <a href="#sobre"         class="text-white/75 hover:text-white text-sm font-medium transition-colors">Sobre nós</a>
                <a href="#ambiente"      class="text-white/75 hover:text-white text-sm font-medium transition-colors">Ambiente</a>
                <a href="#combos"        class="text-white/75 hover:text-white text-sm font-medium transition-colors">Combos</a>
                <a href="#promocoes"     class="text-white/75 hover:text-white text-sm font-medium transition-colors">Promoções</a>
                <a href="#aniversariantes" class="text-white/75 hover:text-white text-sm font-medium transition-colors">Aniversário</a>
                <a href="#contato"       class="text-white/75 hover:text-white text-sm font-medium transition-colors">Contato</a>
            </div>

            <!-- CTA + hamburger -->
            <div class="flex items-center gap-3">
                <a href="/pizzaria/cardapio.php"
                   class="bg-red-600 hover:bg-red-700 text-white text-sm font-bold px-5 py-2.5 rounded-full transition-colors shadow-lg whitespace-nowrap">
                    🍕 Ver Cardápio
                </a>
                <button id="hamburger" class="lg:hidden text-white p-1" aria-label="Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobile-menu" class="lg:hidden overflow-hidden max-h-0 opacity-0">
            <div class="bg-gray-950/95 backdrop-blur-md rounded-2xl mb-4 px-5 py-3 flex flex-col gap-0.5 border border-white/10">
                <a href="#sobre"          class="text-gray-300 hover:text-white py-2.5 text-sm font-medium border-b border-white/5" onclick="closeMobile()">Sobre nós</a>
                <a href="#ambiente"       class="text-gray-300 hover:text-white py-2.5 text-sm font-medium border-b border-white/5" onclick="closeMobile()">Ambiente</a>
                <a href="#combos"         class="text-gray-300 hover:text-white py-2.5 text-sm font-medium border-b border-white/5" onclick="closeMobile()">Combos</a>
                <a href="#promocoes"      class="text-gray-300 hover:text-white py-2.5 text-sm font-medium border-b border-white/5" onclick="closeMobile()">Promoções</a>
                <a href="#aniversariantes" class="text-gray-300 hover:text-white py-2.5 text-sm font-medium border-b border-white/5" onclick="closeMobile()">Aniversário</a>
                <a href="#contato"        class="text-gray-300 hover:text-white py-2.5 text-sm font-medium" onclick="closeMobile()">Contato</a>
            </div>
        </div>
    </div>
</nav>

<!-- ════════════════════════════════════
     HERO
════════════════════════════════════ -->
<section id="hero" class="hero-bg min-h-screen flex flex-col items-center justify-center text-center px-4 relative">

    <!-- Status badge -->
    <div class="absolute top-24 lg:top-28 left-1/2 -translate-x-1/2 whitespace-nowrap">
        <?php if ($aberto): ?>
        <span class="inline-flex items-center gap-2 bg-green-500/15 border border-green-400/25 text-green-300 text-xs font-semibold px-5 py-2 rounded-full backdrop-blur-sm">
            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse inline-block"></span>
            Aberto agora &nbsp;·&nbsp; Entrega em ~<?= $tempoEntrega ?> min
        </span>
        <?php else: ?>
        <span class="inline-flex items-center gap-2 bg-red-500/15 border border-red-400/25 text-red-300 text-xs font-semibold px-5 py-2 rounded-full backdrop-blur-sm">
            <span class="w-2 h-2 bg-red-400 rounded-full inline-block"></span>
            Fechado agora &nbsp;·&nbsp; Abrimos às <?= getConfig('hora_abertura','18:00') ?>h
        </span>
        <?php endif; ?>
    </div>

    <!-- Conteúdo principal -->
    <div class="max-w-3xl mx-auto">
        <p class="text-red-400 font-semibold text-xs sm:text-sm uppercase tracking-[0.22em] mb-5 fade-in">
            Desde 2010 — Feita com amor, assada no forno a lenha
        </p>
        <h1 class="font-display text-white text-5xl sm:text-6xl lg:text-7xl font-black leading-[1.05] mb-6 fade-in delay-1">
            A pizza mais<br>
            <span class="text-red-400">gostosa</span> da cidade
        </h1>
        <p class="text-white/65 text-lg sm:text-xl max-w-xl mx-auto mb-10 leading-relaxed fade-in delay-2">
            Ingredientes frescos selecionados, massa artesanal e forno a lenha.
            O sabor que sua família merece, entregue rapidinho na sua porta.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 fade-in delay-3">
            <a href="/pizzaria/cardapio.php"
               class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white font-extrabold px-9 py-4 rounded-full text-base transition-all shadow-2xl hover:shadow-red-500/25 hover:-translate-y-0.5">
                🍕 Ver Cardápio Completo
            </a>
            <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>?text=Olá! Gostaria de fazer um pedido 🍕"
               target="_blank" rel="noopener"
               class="w-full sm:w-auto bg-white/10 hover:bg-white/20 backdrop-blur-sm border border-white/25 text-white font-bold px-9 py-4 rounded-full text-base transition-all hover:-translate-y-0.5">
                💬 Pedir pelo WhatsApp
            </a>
        </div>
    </div>

    <!-- Seta scroll -->
    <div class="scroll-arrow absolute bottom-8 left-1/2 text-white/40 flex flex-col items-center gap-1.5">
        <span class="text-[11px] font-medium uppercase tracking-widest">role</span>
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>

<!-- ════════════════════════════════════
     SOBRE NÓS
════════════════════════════════════ -->
<section id="sobre" class="py-20 lg:py-28 px-4 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-14 lg:gap-24 items-center">

            <!-- Texto -->
            <div class="fade-in">
                <p class="text-red-600 font-semibold text-xs uppercase tracking-[0.22em] mb-3">Nossa história</p>
                <h2 class="font-display text-gray-900 text-4xl lg:text-[2.8rem] font-bold leading-tight mb-6">
                    Feita com paixão,<br>servida com amor
                </h2>
                <?php if ($sobreNosTexto): ?>
                <p class="text-gray-600 text-lg leading-relaxed mb-10"><?= nl2br(htmlspecialchars($sobreNosTexto)) ?></p>
                <?php else: ?>
                <p class="text-gray-600 text-lg leading-relaxed mb-5">
                    A Pizzaria Bella Napoli nasceu do sonho de trazer as verdadeiras receitas
                    napolitanas para a sua mesa. Desde 2010, preparamos cada pizza com a
                    dedicação e o capricho que você merece.
                </p>
                <p class="text-gray-500 leading-relaxed mb-10">
                    Nosso forno a lenha, aquecido a mais de 400°C, garante aquela massa
                    crocante por fora e macia por dentro. Ingredientes frescos selecionados
                    toda manhã, molho de tomate artesanal e mussarela de primeira.
                </p>
                <?php endif; ?>

                <!-- Números -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="text-center p-4 bg-red-50 rounded-2xl">
                        <p class="font-display text-3xl font-black text-red-600"><?= htmlspecialchars($sobreNosAnos) ?></p>
                        <p class="text-xs text-gray-500 font-medium mt-1 leading-snug">anos de<br>história</p>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-2xl">
                        <p class="font-display text-3xl font-black text-orange-600"><?= htmlspecialchars($sobreNosSabores) ?></p>
                        <p class="text-xs text-gray-500 font-medium mt-1 leading-snug">sabores no<br>cardápio</p>
                    </div>
                    <div class="text-center p-4 bg-yellow-50 rounded-2xl">
                        <p class="font-display text-2xl font-black text-yellow-600">⭐ 4.9</p>
                        <p class="text-xs text-gray-500 font-medium mt-1 leading-snug">avaliação<br>média</p>
                    </div>
                </div>
            </div>

            <!-- Imagens empilhadas -->
            <div class="relative fade-in delay-2">
                <img src="<?= $imagemPizzaiolo ?: 'https://images.unsplash.com/photo-1571997478779-2adcbbe9ab2f?w=800&q=85' ?>"
                     alt="Pizzaiolo preparando pizza"
                     class="rounded-3xl w-full h-80 lg:h-[420px] object-cover shadow-2xl"
                     onerror="this.src='https://images.unsplash.com/photo-1571997478779-2adcbbe9ab2f?w=800&q=85'">
                <div class="absolute -bottom-5 -left-5 w-32 h-32 rounded-2xl overflow-hidden border-4 border-white shadow-xl hidden sm:block">
                    <img src="https://images.unsplash.com/photo-1571066811602-716837d681de?w=300&q=85"
                         alt="Detalhe da pizza"
                         class="w-full h-full object-cover">
                </div>
                <div class="absolute -top-4 -right-4 w-20 h-20 bg-red-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-xl hidden sm:flex text-center leading-tight">
                    Forno<br>a<br>Lenha
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     AMBIENTE
════════════════════════════════════ -->
<section id="ambiente" class="py-20 lg:py-28 bg-gray-950">
    <div class="max-w-6xl mx-auto px-4">
        <div class="text-center mb-14 fade-in">
            <p class="text-red-400 font-semibold text-xs uppercase tracking-[0.22em] mb-3">Nosso espaço</p>
            <h2 class="font-display text-white text-4xl lg:text-5xl font-bold">
                Um ambiente feito para<br>momentos especiais
            </h2>
            <p class="text-gray-400 mt-4 max-w-md mx-auto">Venha nos visitar ou peça pelo delivery. Aqui, cada detalhe foi pensado para sua comodidade.</p>
        </div>

        <!-- Grade de fotos -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="col-span-2 lg:col-span-1 lg:row-span-2 overflow-hidden rounded-2xl">
                <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=900&q=85"
                     alt="Interior da pizzaria"
                     class="gallery-img w-full h-52 lg:h-full object-cover">
            </div>
            <div class="overflow-hidden rounded-2xl fade-in delay-1">
                <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&q=85"
                     alt="Ambiente"
                     class="gallery-img w-full h-40 lg:h-52 object-cover">
            </div>
            <div class="overflow-hidden rounded-2xl fade-in delay-2">
                <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=85"
                     alt="Salão"
                     class="gallery-img w-full h-40 lg:h-52 object-cover">
            </div>
            <div class="overflow-hidden rounded-2xl fade-in delay-1">
                <img src="https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=600&q=85"
                     alt="Pizza saindo do forno"
                     class="gallery-img w-full h-40 lg:h-52 object-cover">
            </div>
            <div class="overflow-hidden rounded-2xl fade-in delay-2">
                <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&q=85"
                     alt="Pizza especial"
                     class="gallery-img w-full h-40 lg:h-52 object-cover">
            </div>
            <div class="overflow-hidden rounded-2xl fade-in delay-3">
                <img src="https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&q=85"
                     alt="Fatia de pizza"
                     class="gallery-img w-full h-40 lg:h-52 object-cover">
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     COMBOS
════════════════════════════════════ -->
<section id="combos" class="py-20 lg:py-28 bg-white px-4">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14 fade-in">
            <p class="text-red-600 font-semibold text-xs uppercase tracking-[0.22em] mb-3">Ofertas especiais</p>
            <h2 class="font-display text-gray-900 text-4xl lg:text-5xl font-bold">Combos irresistíveis</h2>
            <p class="text-gray-500 mt-3 max-w-lg mx-auto">Pizza + bebida com preço especial. Escolha o seu e aproveite!</p>
        </div>

        <?php
        // Imagens de fallback para combos sem foto
        $comboFallbacks = [
            'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=700&q=80',
            'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=700&q=80',
            'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=700&q=80',
        ];
        // Combos estáticos de fallback quando banco está vazio
        $combosEstaticos = [
            ['nome'=>'Combo Família','descricao'=>'Pizza grande + 2 Refrigerantes 2L','preco_original'=>82.70,'preco_combo'=>69.90,'badge'=>'MAIS PEDIDO','imagem_url'=>''],
            ['nome'=>'Combo Casal',  'descricao'=>'Pizza média + 1 Refrigerante 2L',  'preco_original'=>63.80,'preco_combo'=>54.90,'badge'=>'POPULAR',   'imagem_url'=>''],
            ['nome'=>'Combo Solo',   'descricao'=>'Pizza pequena + 1 Lata',            'preco_original'=>47.80,'preco_combo'=>39.90,'badge'=>'ECONÔMICO', 'imagem_url'=>''],
        ];
        $combosExibir = count($combos) > 0 ? $combos : $combosEstaticos;
        $cols = count($combosExibir) === 2 ? 'md:grid-cols-2' : (count($combosExibir) === 1 ? 'md:grid-cols-1 max-w-sm mx-auto' : 'md:grid-cols-3');
        ?>
        <div class="grid <?= $cols ?> gap-6">
            <?php foreach ($combosExibir as $i => $c):
                $imgSrc = !empty($c['imagem_url']) ? $c['imagem_url'] : ($comboFallbacks[$i % 3]);
                $economia = ($c['preco_original'] > 0 && $c['preco_original'] > $c['preco_combo'])
                    ? round((1 - $c['preco_combo'] / $c['preco_original']) * 100) . '% OFF'
                    : '';
            ?>
            <div class="combo-card bg-white rounded-3xl shadow-md overflow-hidden border border-gray-100 fade-in delay-<?= min($i + 1, 3) ?>">
                <div class="relative overflow-hidden h-48">
                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                         alt="<?= htmlspecialchars($c['nome']) ?>"
                         class="combo-img w-full h-full object-cover"
                         onerror="this.src='<?= $comboFallbacks[$i % 3] ?>'">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                    <?php if (!empty($c['badge'])): ?>
                    <span class="absolute top-3 right-3 bg-red-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-full tracking-wide shadow">
                        <?= htmlspecialchars($c['badge']) ?>
                    </span>
                    <?php elseif ($economia): ?>
                    <span class="absolute top-3 right-3 bg-orange-500 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-full tracking-wide shadow">
                        <?= $economia ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="p-6">
                    <h3 class="font-bold text-gray-900 text-xl mb-1"><?= htmlspecialchars($c['nome']) ?></h3>
                    <p class="text-gray-500 text-sm mb-5 leading-relaxed"><?= htmlspecialchars($c['descricao']) ?></p>
                    <div class="flex items-end justify-between">
                        <div>
                            <?php if ($c['preco_original'] > 0 && $c['preco_original'] > $c['preco_combo']): ?>
                            <p class="text-gray-400 text-xs line-through mb-0.5">R$ <?= number_format($c['preco_original'], 2, ',', '.') ?></p>
                            <?php endif; ?>
                            <p class="text-red-600 font-black text-2xl font-display">R$ <?= number_format($c['preco_combo'], 2, ',', '.') ?></p>
                        </div>
                        <a href="/pizzaria/cardapio.php"
                           class="bg-red-600 hover:bg-red-700 text-white font-bold text-sm px-6 py-3 rounded-full transition-colors shadow-md shadow-red-100">
                            Pedir →
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     NOVIDADES & PROMOÇÕES
════════════════════════════════════ -->
<section id="promocoes" class="py-20 lg:py-28 px-4" style="background-color: #FFF5F0;">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14 fade-in">
            <p class="text-red-600 font-semibold text-xs uppercase tracking-[0.22em] mb-3">Não perca!</p>
            <h2 class="font-display text-gray-900 text-4xl lg:text-5xl font-bold">Novidades & Promoções</h2>
            <p class="text-gray-500 mt-3 max-w-md mx-auto">Aproveite nossos preços especiais por tempo limitado.</p>
        </div>

        <?php if (count($promocoes) > 0): ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($promocoes as $i => $p):
                $precoFinal = round($p['preco'] * (1 - $p['desconto_pct'] / 100), 2);
            ?>
            <div class="promo-card bg-white rounded-2xl shadow-sm overflow-hidden border border-red-50 fade-in delay-<?= min($i + 1, 3) ?>">
                <div class="relative h-44 overflow-hidden bg-gray-100">
                    <img src="<?= htmlspecialchars($p['imagem_url']) ?>"
                         alt="<?= htmlspecialchars($p['nome']) ?>"
                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                         onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&q=80'">
                    <div class="absolute top-2 left-2 bg-red-600 text-white text-xs font-extrabold px-2.5 py-1 rounded-full">
                        <?= number_format($p['desconto_pct'], 0) ?>% OFF
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-[11px] text-orange-500 font-semibold uppercase tracking-wide mb-1"><?= htmlspecialchars($p['categoria_nome']) ?></p>
                    <h3 class="font-bold text-gray-800 mb-2 leading-tight"><?= htmlspecialchars($p['nome']) ?></h3>
                    <div class="flex items-baseline gap-2 mb-4">
                        <span class="text-red-600 font-black text-xl">R$ <?= number_format($precoFinal, 2, ',', '.') ?></span>
                        <span class="text-gray-400 text-sm line-through">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                    </div>
                    <a href="/pizzaria/cardapio.php"
                       class="block w-full text-center bg-red-600 hover:bg-red-700 text-white font-bold text-sm py-2.5 rounded-xl transition-colors">
                        Aproveitar
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Destaques quando não há promoções ativas -->
        <div class="grid sm:grid-cols-3 gap-6">
            <?php foreach ($destaques as $i => $p): ?>
            <div class="promo-card bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100 fade-in delay-<?= $i + 1 ?>">
                <div class="h-48 overflow-hidden bg-gray-100">
                    <img src="<?= htmlspecialchars($p['imagem_url']) ?>"
                         alt="<?= htmlspecialchars($p['nome']) ?>"
                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                         onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&q=80'">
                </div>
                <div class="p-5">
                    <p class="text-[11px] text-orange-500 font-semibold uppercase tracking-wide mb-1"><?= htmlspecialchars($p['categoria_nome']) ?></p>
                    <h3 class="font-bold text-gray-800 text-lg mb-1 leading-tight"><?= htmlspecialchars($p['nome']) ?></h3>
                    <p class="text-gray-500 text-sm mb-4 leading-relaxed line-clamp-2"><?= htmlspecialchars($p['descricao']) ?></p>
                    <div class="flex items-center justify-between">
                        <span class="text-red-600 font-black text-xl">R$ <?= number_format($p['preco'], 2, ',', '.') ?></span>
                        <a href="/pizzaria/cardapio.php"
                           class="bg-red-600 hover:bg-red-700 text-white font-bold text-sm px-5 py-2 rounded-full transition-colors">
                            Pedir
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="text-center mt-12 fade-in">
            <a href="/pizzaria/cardapio.php"
               class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold px-10 py-4 rounded-full transition-all shadow-xl shadow-red-100 hover:-translate-y-0.5 text-base">
                Ver cardápio completo
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     ANIVERSARIANTES
════════════════════════════════════ -->
<section id="aniversariantes" class="py-20 lg:py-28 px-4 relative overflow-hidden"
         style="background: linear-gradient(135deg, #b91c1c 0%, #dc2626 45%, #ea580c 100%);">

    <!-- Emojis decorativos de fundo -->
    <div class="absolute inset-0 select-none pointer-events-none overflow-hidden" aria-hidden="true">
        <span class="absolute top-8 left-8 text-8xl opacity-10">🎂</span>
        <span class="absolute top-8 right-8 text-7xl opacity-10">🎉</span>
        <span class="absolute bottom-8 left-1/4 text-6xl opacity-10">🍕</span>
        <span class="absolute bottom-8 right-1/4 text-7xl opacity-10">🎈</span>
        <span class="absolute top-1/2 -translate-y-1/2 left-2 text-9xl opacity-5">🎊</span>
        <span class="absolute top-1/2 -translate-y-1/2 right-2 text-9xl opacity-5">🎊</span>
    </div>

    <div class="max-w-3xl mx-auto text-center relative z-10 fade-in">
        <div class="text-6xl mb-6">🎂</div>
        <p class="text-red-200 font-semibold text-xs uppercase tracking-[0.22em] mb-3">Dia especial</p>
        <h2 class="font-display text-white text-4xl lg:text-5xl font-bold mb-5">
            Aniversariante do dia?
        </h2>
        <?php if ($aniversBeneficio): ?>
        <p class="text-white/80 text-lg lg:text-xl max-w-2xl mx-auto mb-10 leading-relaxed">
            <?= nl2br(htmlspecialchars($aniversBeneficio)) ?>
        </p>
        <?php else: ?>
        <p class="text-white/80 text-lg lg:text-xl max-w-2xl mx-auto mb-3 leading-relaxed">
            Apresente seu documento no balcão e ganhe uma
            <strong class="text-yellow-300">sobremesa grátis</strong> no seu aniversário!
            Válido apenas no dia, para pedidos no local.
        </p>
        <p class="text-white/55 text-sm mb-10 max-w-lg mx-auto">
            Também fazemos <strong class="text-white/75">encomendas para festas e eventos</strong> —
            pizzas personalizadas para grupos de 10 a 200 pessoas. Consulte disponibilidade!
        </p>
        <?php endif; ?>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>?text=Olá! Sou aniversariante hoje e gostaria de saber sobre o desconto 🎂"
               target="_blank" rel="noopener"
               class="w-full sm:w-auto bg-white text-red-700 font-extrabold px-9 py-4 rounded-full transition-all shadow-2xl hover:shadow-white/20 hover:-translate-y-0.5 text-base">
                🎉 Quero meu presente!
            </a>
            <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>?text=Olá! Gostaria de encomendar pizzas para uma festa 🍕"
               target="_blank" rel="noopener"
               class="w-full sm:w-auto bg-white/10 border-2 border-white/30 text-white font-bold px-9 py-4 rounded-full transition-all hover:bg-white/20 hover:-translate-y-0.5 text-base">
                📅 Encomendar para festa
            </a>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     CONTATO & MAPA
════════════════════════════════════ -->
<section id="contato" class="py-20 lg:py-28 bg-white px-4">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14 fade-in">
            <p class="text-red-600 font-semibold text-xs uppercase tracking-[0.22em] mb-3">Fale conosco</p>
            <h2 class="font-display text-gray-900 text-4xl lg:text-5xl font-bold">Onde nos encontrar</h2>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-start">

            <!-- Info de contato -->
            <div class="space-y-4 fade-in">

                <!-- Telefone -->
                <a href="tel:<?= preg_replace('/\D/', '', $telefone) ?>"
                   class="flex items-center gap-4 p-5 bg-gray-50 hover:bg-red-50 rounded-2xl transition-colors group">
                    <div class="w-12 h-12 bg-red-100 group-hover:bg-red-200 rounded-xl flex items-center justify-center shrink-0 transition-colors">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Telefone</p>
                        <p class="text-gray-900 font-bold text-lg"><?= htmlspecialchars($telefone) ?></p>
                    </div>
                </a>

                <!-- WhatsApp -->
                <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>?text=Olá! Gostaria de fazer um pedido 🍕"
                   target="_blank" rel="noopener"
                   class="flex items-center gap-4 p-5 bg-gray-50 hover:bg-green-50 rounded-2xl transition-colors group">
                    <div class="w-12 h-12 bg-green-100 group-hover:bg-green-200 rounded-xl flex items-center justify-center shrink-0 transition-colors">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-0.5">WhatsApp</p>
                        <p class="text-gray-900 font-bold text-lg"><?= htmlspecialchars($telefone) ?></p>
                        <p class="text-gray-400 text-xs">Clique para iniciar uma conversa</p>
                    </div>
                </a>

                <!-- Endereço -->
                <div class="flex items-start gap-4 p-5 bg-gray-50 rounded-2xl">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-0.5">Endereço</p>
                        <p class="text-gray-900 font-bold leading-snug"><?= htmlspecialchars($endereco) ?></p>
                    </div>
                </div>

                <!-- Horários -->
                <div class="p-5 bg-gray-50 rounded-2xl">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide">Horário de funcionamento</p>
                            <?php if ($aberto): ?>
                            <span class="text-green-600 text-xs font-bold flex items-center gap-1 mt-0.5">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse inline-block"></span>
                                Aberto agora
                            </span>
                            <?php else: ?>
                            <span class="text-red-500 text-xs font-bold">Fechado agora</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($horariosParsed): ?>
                    <div class="space-y-2">
                        <?php foreach ($horariosParsed as $dia => $info): ?>
                        <div class="flex justify-between items-center text-sm border-b border-gray-100 last:border-0 pb-2 last:pb-0
                                    <?= $diaAtual === (int)$dia ? 'text-red-600 font-bold' : 'text-gray-600' ?>">
                            <span class="<?= $diaAtual === (int)$dia ? '' : 'text-gray-500' ?>"><?= $diasNomes[$dia] ?></span>
                            <?php if (!empty($info['ativo'])): ?>
                            <span><?= $info['abre'] ?> – <?= $info['fecha'] ?></span>
                            <?php else: ?>
                            <span class="text-gray-400 font-normal">Fechado</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span class="text-gray-500">Todos os dias</span>
                        <span class="font-bold"><?= getConfig('hora_abertura','18:00') ?>h – <?= getConfig('hora_fechamento','23:00') ?>h</span>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 bg-orange-50 rounded-xl px-4 py-3 flex items-center justify-between">
                        <p class="text-xs text-orange-600 font-medium">Tempo médio de entrega</p>
                        <p class="text-orange-700 font-extrabold text-lg">~<?= $tempoEntrega ?> min</p>
                    </div>
                </div>
            </div>

            <!-- Mapa -->
            <div class="fade-in delay-2">
                <div class="rounded-3xl overflow-hidden shadow-2xl bg-gray-100" style="height: 520px;">
                    <?php if ($mapsEmbed): ?>
                    <iframe src="<?= htmlspecialchars($mapsEmbed) ?>"
                            width="100%" height="100%" style="border:0;"
                            allowfullscreen loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                    <?php else: ?>
                    <div class="w-full h-full flex flex-col items-center justify-center text-center px-8 bg-gray-100">
                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-5 text-3xl">📍</div>
                        <p class="text-gray-700 font-bold text-xl mb-2">Rua das Pizzas, 123</p>
                        <p class="text-gray-500 text-sm mb-1">Centro, São Paulo/SP</p>
                        <p class="text-gray-400 text-xs mb-8">CEP 01310-100</p>
                        <a href="https://maps.google.com/?q=<?= urlencode($endereco) ?>"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-bold px-7 py-3.5 rounded-xl text-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Ver no Google Maps
                        </a>
                        <p class="text-gray-400 text-xs mt-6 max-w-xs">
                            Configure o link do Google Maps no painel admin
                            para exibir o mapa aqui.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════
     FOOTER
════════════════════════════════════ -->
<footer class="bg-gray-950 text-gray-400">
    <div class="max-w-6xl mx-auto px-4 pt-14 pb-10 lg:pt-16">
        <div class="grid md:grid-cols-3 gap-10 lg:gap-14">

            <!-- Brand -->
            <div>
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="text-3xl leading-none">🍕</span>
                    <div class="leading-none">
                        <span class="text-white font-black text-xl tracking-tight block">Bella Napoli</span>
                        <span class="text-gray-600 text-xs">Pizzaria Artesanal</span>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-gray-500 mb-7">
                    Desde 2010, o sabor autêntico da pizza napolitana feita
                    no forno a lenha com ingredientes frescos selecionados todos os dias.
                </p>
                <!-- Redes sociais -->
                <div class="flex items-center gap-3">
                    <a href="https://instagram.com/<?= htmlspecialchars($instagram) ?>" target="_blank" rel="noopener"
                       class="w-10 h-10 bg-gray-800 hover:bg-gradient-to-br hover:from-purple-600 hover:to-pink-500 rounded-xl flex items-center justify-center transition-all group" aria-label="Instagram">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                    </a>
                    <a href="https://facebook.com/<?= htmlspecialchars($facebook) ?>" target="_blank" rel="noopener"
                       class="w-10 h-10 bg-gray-800 hover:bg-blue-600 rounded-xl flex items-center justify-center transition-all group" aria-label="Facebook">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank" rel="noopener"
                       class="w-10 h-10 bg-gray-800 hover:bg-green-600 rounded-xl flex items-center justify-center transition-all group" aria-label="WhatsApp">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Links rápidos -->
            <div>
                <h4 class="text-white font-bold text-sm uppercase tracking-wide mb-5">Navegação</h4>
                <ul class="space-y-3">
                    <li><a href="#sobre"          class="text-sm hover:text-white transition-colors">Sobre nós</a></li>
                    <li><a href="#ambiente"        class="text-sm hover:text-white transition-colors">Nosso ambiente</a></li>
                    <li><a href="#combos"          class="text-sm hover:text-white transition-colors">Combos</a></li>
                    <li><a href="#promocoes"       class="text-sm hover:text-white transition-colors">Promoções</a></li>
                    <li><a href="#aniversariantes" class="text-sm hover:text-white transition-colors">Aniversariantes</a></li>
                    <li><a href="#contato"         class="text-sm hover:text-white transition-colors">Contato</a></li>
                    <li>
                        <a href="/pizzaria/cardapio.php"
                           class="text-sm text-red-400 hover:text-red-300 font-semibold transition-colors">
                            🍕 Fazer pedido →
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Expediente -->
            <div>
                <h4 class="text-white font-bold text-sm uppercase tracking-wide mb-5">Expediente</h4>
                <?php if ($horariosParsed): ?>
                <div class="space-y-2.5">
                    <?php foreach ($horariosParsed as $dia => $info): ?>
                    <div class="flex justify-between text-sm <?= $diaAtual === (int)$dia ? 'text-red-400 font-semibold' : '' ?>">
                        <span class="<?= $diaAtual === (int)$dia ? '' : 'text-gray-500' ?>"><?= $diasNomes[$dia] ?></span>
                        <?php if (!empty($info['ativo'])): ?>
                        <span class="<?= $diaAtual === (int)$dia ? '' : 'text-gray-300' ?>"><?= $info['abre'] ?> – <?= $info['fecha'] ?></span>
                        <?php else: ?>
                        <span class="text-gray-600">Fechado</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-300 font-medium">
                    <?= getConfig('hora_abertura','18:00') ?>h às <?= getConfig('hora_fechamento','23:00') ?>h
                </p>
                <p class="text-gray-600 text-sm mt-1">Todos os dias</p>
                <?php endif; ?>

                <div class="mt-6 p-4 bg-gray-900 rounded-2xl border border-gray-800">
                    <p class="text-xs text-gray-500 mb-1">Endereço</p>
                    <p class="text-gray-300 text-sm leading-snug"><?= htmlspecialchars($endereco) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom bar -->
    <div class="border-t border-gray-900">
        <div class="max-w-6xl mx-auto px-4 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
            <p>&copy; <?= date('Y') ?> Pizzaria Bella Napoli — Todos os direitos reservados</p>
            <p>Feito com ❤️ e muito queijo</p>
        </div>
    </div>
</footer>

<!-- ════════════════════════════════════
     BOTÃO WHATSAPP FLUTUANTE
════════════════════════════════════ -->
<a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>?text=Olá! Gostaria de fazer um pedido 🍕"
   target="_blank" rel="noopener"
   class="wa-pulse fixed bottom-6 right-6 z-50 w-14 h-14 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center shadow-2xl transition-transform hover:scale-110"
   aria-label="WhatsApp — Fazer pedido">
    <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
</a>

<script>
// ── Navbar: transparente → escura ao rolar ─────────
const navbar = document.getElementById('navbar');
function updateNav() {
    if (window.scrollY > 60) {
        navbar.classList.add('bg-gray-950', 'shadow-xl', 'shadow-black/30');
    } else {
        navbar.classList.remove('bg-gray-950', 'shadow-xl', 'shadow-black/30');
    }
}
window.addEventListener('scroll', updateNav, { passive: true });
updateNav();

// ── Menu mobile ────────────────────────────────────
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobile-menu');
let open = false;

hamburger.addEventListener('click', () => {
    open = !open;
    mobileMenu.style.maxHeight = open ? mobileMenu.scrollHeight + 'px' : '0';
    mobileMenu.style.opacity   = open ? '1' : '0';
});

function closeMobile() {
    open = false;
    mobileMenu.style.maxHeight = '0';
    mobileMenu.style.opacity   = '0';
}

// ── Animações de scroll (IntersectionObserver) ─────
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
</script>
</body>
</html>
