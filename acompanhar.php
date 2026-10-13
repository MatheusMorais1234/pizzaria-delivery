<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pedidoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pedidoId) {
    header('Location: /pizzaria/');
    exit;
}

$pdo = getPDO();

// Busca pedido
$stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header('Location: /pizzaria/');
    exit;
}

// Busca itens do pedido
$stmt = $pdo->prepare('
    SELECT pi.*, p.nome AS produto_nome
    FROM pedido_itens pi
    JOIN produtos p ON p.id = pi.produto_id
    WHERE pi.pedido_id = ?
');
$stmt->execute([$pedidoId]);
$itens = $stmt->fetchAll();

$pageTitle = 'Pedido #' . $pedidoId . ' — Bella Napoli';

// Mapeamento de status
$statusInfo = [
    'pendente'       => ['etapa' => 1, 'label' => 'Pedido recebido!',       'mensagem' => 'Recebemos seu pedido e em breve começaremos a prepará-lo.'],
    'em_preparo'     => ['etapa' => 2, 'label' => 'Em preparo!',            'mensagem' => 'Nossa equipe está preparando sua pizza com carinho.'],
    'saiu_entrega'   => ['etapa' => 3, 'label' => 'Saiu para entrega!',     'mensagem' => 'Seu pedido está a caminho. Em breve chega até você!'],
    'entregue'       => ['etapa' => 4, 'label' => 'Entregue! Bom apetite!', 'mensagem' => 'Esperamos que você aproveite muito! Obrigado pela preferência.'],
    'cancelado'      => ['etapa' => 0, 'label' => 'Pedido cancelado',       'mensagem' => 'Infelizmente seu pedido foi cancelado.'],
];

$info         = $statusInfo[$pedido['status']] ?? $statusInfo['pendente'];
$tempoEntrega = (int) getConfig('tempo_entrega_min', '40');
$segundosPassados = time() - strtotime($pedido['created_at']);
$segundosRestantes = max(0, ($tempoEntrega * 60) - $segundosPassados);

// Avaliação existente (só carrega se entregue)
$avaliacao = null;
if ($pedido['status'] === 'entregue') {
    $stmtAv = $pdo->prepare('SELECT nota, comentario FROM avaliacoes WHERE pedido_id = ?');
    $stmtAv->execute([$pedidoId]);
    $avaliacao = $stmtAv->fetch() ?: null;
}

// WhatsApp da pizzaria
$whatsappNum = preg_replace('/\D/', '', getConfig('whatsapp', ''));

// Tempo de espera dinâmico
$tempoEspera = (int) getConfig('tempo_espera_atual', '0');

include __DIR__ . '/includes/header.php';
?>

<!-- NAVBAR -->
<nav class="bg-red-600 shadow-lg">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pizzaria/" class="text-white font-bold text-xl">🍕 Bella Napoli</a>
        <span class="text-red-200 text-sm">Acompanhe seu pedido</span>
    </div>
</nav>

<main class="max-w-3xl mx-auto px-4 py-10 flex-1 w-full">

    <!-- Banner de notificações (oculto por padrão, exibido via JS) -->
    <div id="notif-banner" class="hidden bg-blue-50 border border-blue-200 rounded-2xl px-4 py-3 mb-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm text-blue-700">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span>Ativar notificações para saber quando seu pedido atualizar?</span>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <button onclick="ativarNotificacoes()" class="text-xs font-bold bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">Ativar</button>
            <button onclick="document.getElementById('notif-banner').classList.add('hidden')" class="text-xs text-blue-500 px-2 hover:text-blue-700 transition-colors">Agora não</button>
        </div>
    </div>

    <!-- Card de cancelamento -->
    <?php if ($pedido['status'] === 'cancelado'): ?>
    <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-6">
        <!-- Faixa vermelha -->
        <div class="bg-red-600 px-6 py-5 text-center">
            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <p class="text-red-100 text-xs font-semibold uppercase tracking-widest mb-1">Número do pedido</p>
            <h1 class="text-4xl font-extrabold text-white mb-1">#<?= $pedidoId ?></h1>
            <p class="text-red-100 font-semibold">Pedido cancelado</p>
        </div>
        <!-- Motivo -->
        <div class="px-6 py-5">
            <?php
                $motivoParts = $pedido['motivo_cancelamento']
                    ? explode(': ', $pedido['motivo_cancelamento'], 2)
                    : null;
                $motivoPrincipal = $motivoParts[0] ?? null;
                $motivoMensagem  = $motivoParts[1] ?? null;
            ?>
            <?php if ($motivoPrincipal): ?>
            <div class="flex items-start gap-3 mb-3">
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Motivo</p>
                    <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($motivoPrincipal) ?></p>
                </div>
            </div>
            <?php if ($motivoMensagem): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-600 italic leading-relaxed">
                "<?= htmlspecialchars($motivoMensagem) ?>"
            </div>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-gray-500 text-sm text-center">Infelizmente seu pedido foi cancelado pela loja.</p>
            <?php endif; ?>

            <a href="/pizzaria/cardapio.php"
               class="mt-5 w-full flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9M9 21a1 1 0 100-2 1 1 0 000 2zm6 0a1 1 0 100-2 1 1 0 000 2z"/>
                </svg>
                Fazer um novo pedido
            </a>
        </div>
    </div>
    <?php else: ?>

    <!-- Cabeçalho do pedido -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6 text-center">
        <p class="text-gray-500 text-sm mb-1">Número do pedido</p>
        <h1 class="text-5xl font-extrabold text-red-600 mb-3">#<?= $pedidoId ?></h1>
        <p class="text-gray-700 font-medium text-lg"><?= $info['mensagem'] ?></p>

        <?php if ($pedido['status'] !== 'entregue'): ?>
        <!-- Countdown de entrega -->
        <div class="mt-5 inline-flex flex-col items-center bg-orange-50 border border-orange-200 rounded-2xl px-8 py-4">
            <?php if ($segundosRestantes > 0): ?>
            <p class="text-orange-600 text-xs font-semibold uppercase tracking-wide mb-1">Tempo estimado</p>
            <div class="flex items-end gap-2">
                <span id="countdown-min" class="text-5xl font-extrabold text-orange-600 leading-none tabular-nums">
                    <?= floor($segundosRestantes / 60) ?>
                </span>
                <span class="text-orange-400 font-bold text-lg mb-1">min</span>
                <span id="countdown-sec" class="text-3xl font-bold text-orange-400 leading-none mb-0.5 tabular-nums">
                    <?= str_pad($segundosRestantes % 60, 2, '0', STR_PAD_LEFT) ?>
                </span>
                <span class="text-orange-400 text-base mb-1">s</span>
            </div>
            <div class="w-full bg-orange-100 rounded-full h-2 mt-3 overflow-hidden">
                <div id="progress-bar"
                     class="bg-orange-500 h-2 rounded-full transition-all duration-1000"
                     style="width: <?= min(100, round((1 - $segundosRestantes / ($tempoEntrega * 60)) * 100)) ?>%">
                </div>
            </div>
            <p class="text-orange-400 text-xs mt-2">Previsão de <?= $tempoEntrega ?> minutos</p>
            <?php else: ?>
            <p class="text-orange-600 font-bold text-lg">🛵 Chegando a qualquer momento!</p>
            <p class="text-orange-400 text-sm mt-1">Seu pedido está a caminho</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Barra de progresso -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="font-bold text-gray-700 mb-6">Status do pedido</h2>

        <div class="relative">
            <!-- Linha de progresso -->
            <div class="absolute top-5 left-0 right-0 h-1 bg-gray-200 z-0"></div>
            <div id="barra-progresso"
                 class="absolute top-5 left-0 h-1 bg-red-500 z-0 transition-all duration-700"
                 style="width: <?= [0, 0, 33, 66, 100][$info['etapa']] ?>%"></div>

            <!-- Etapas -->
            <div class="relative z-10 grid grid-cols-4 gap-2">
                <?php
                $etapas = [
                    ['icone' => '📋', 'label' => 'Recebido'],
                    ['icone' => '👨‍🍳', 'label' => 'Em preparo'],
                    ['icone' => '🛵', 'label' => 'Saiu'],
                    ['icone' => '✅', 'label' => 'Entregue'],
                ];
                foreach ($etapas as $i => $etapa):
                    $num    = $i + 1;
                    $ativa  = $num <= $info['etapa'];
                    $atual  = $num === $info['etapa'];
                ?>
                <div class="flex flex-col items-center text-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg mb-2
                        <?= $ativa ? 'bg-red-500 shadow-lg' : 'bg-gray-200' ?>
                        <?= $atual ? 'ring-4 ring-red-200' : '' ?>">
                        <?= $etapa['icone'] ?>
                    </div>
                    <span class="text-xs font-semibold <?= $ativa ? 'text-red-600' : 'text-gray-400' ?>">
                        <?= $etapa['label'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Status atual em destaque -->
        <div class="mt-8 text-center">
            <span id="status-badge"
                  class="inline-block px-6 py-2 rounded-full font-bold text-white
                  <?php
                    echo match($pedido['status']) {
                        'pendente'     => 'bg-yellow-500',
                        'em_preparo'   => 'bg-blue-500',
                        'saiu_entrega' => 'bg-orange-500',
                        'entregue'     => 'bg-green-500',
                        default        => 'bg-gray-500'
                    };
                  ?>">
                <?= $info['label'] ?>
            </span>
            <?php if ($tempoEspera > 0 && in_array($pedido['status'], ['pendente', 'em_preparo'])): ?>
            <p class="text-amber-600 text-sm font-semibold mt-3">
                ⏱ Previsão: ~<?= $tempoEspera ?> min
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Itens do pedido -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="font-bold text-gray-700 mb-4">Itens do pedido</h2>
        <div class="space-y-2">
            <?php foreach ($itens as $item): ?>
            <div class="flex justify-between text-sm text-gray-700 py-2 border-b last:border-0">
                <span><?= $item['quantidade'] ?>× <?= htmlspecialchars($item['produto_nome']) ?></span>
                <span class="font-semibold">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-between font-bold text-gray-800 text-lg mt-4 pt-2 border-t">
            <span>Total</span>
            <span class="text-red-600">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></span>
        </div>
    </div>

    <?php endif; // fim do else (não cancelado) ?>

    <!-- Dados de entrega -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
        <h2 class="font-bold text-gray-700 mb-3">Dados de entrega</h2>
        <div class="text-sm text-gray-600 space-y-1">
            <p><span class="font-medium">Nome:</span> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
            <p><span class="font-medium">Telefone:</span> <?= htmlspecialchars($pedido['cliente_telefone']) ?></p>
            <p><span class="font-medium">Endereço:</span> <?= htmlspecialchars($pedido['endereco']) ?></p>
            <?php if ($pedido['observacao']): ?>
            <p><span class="font-medium">Obs:</span> <?= htmlspecialchars($pedido['observacao']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Avaliação pós-entrega -->
    <?php if ($pedido['status'] === 'entregue'): ?>
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6" id="card-avaliacao">
        <?php if ($avaliacao): ?>
        <!-- Já avaliado -->
        <div class="text-center">
            <p class="font-bold text-gray-700 mb-3">Sua avaliação</p>
            <div class="flex justify-center gap-1 mb-2">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                <svg class="w-8 h-8 <?= $s <= $avaliacao['nota'] ? 'text-yellow-400' : 'text-gray-200' ?>"
                     fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                <?php endfor; ?>
            </div>
            <?php if ($avaliacao['comentario']): ?>
            <p class="text-sm text-gray-500 italic">"<?= htmlspecialchars($avaliacao['comentario']) ?>"</p>
            <?php endif; ?>
            <p class="text-xs text-gray-400 mt-2">Obrigado pelo feedback!</p>
        </div>
        <?php else: ?>
        <!-- Formulário de avaliação -->
        <p class="font-bold text-gray-700 mb-1">Como foi seu pedido?</p>
        <p class="text-sm text-gray-400 mb-4">Sua opinião nos ajuda a melhorar</p>

        <div id="av-form">
            <!-- Estrelas -->
            <div class="flex justify-center gap-2 mb-4" id="estrelas">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                <button type="button"
                        onclick="selecionarEstrela(<?= $s ?>)"
                        data-nota="<?= $s ?>"
                        class="estrela w-10 h-10 text-gray-200 hover:text-yellow-400 transition-colors">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </button>
                <?php endfor; ?>
            </div>
            <p id="av-label" class="text-center text-sm text-gray-400 mb-4 h-5"></p>

            <!-- Comentário opcional -->
            <textarea id="av-comentario" rows="2" placeholder="Comentário opcional..."
                      class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none mb-3"></textarea>

            <button onclick="enviarAvaliacao()"
                    id="btn-avaliar"
                    class="w-full bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white font-bold py-3 rounded-xl transition-colors text-sm">
                Enviar avaliação
            </button>
            <p id="av-erro" class="text-red-500 text-xs text-center mt-2 hidden">Selecione uma nota antes de enviar.</p>
        </div>
        <div id="av-obrigado" class="hidden text-center py-2">
            <p class="text-2xl mb-2">🎉</p>
            <p class="font-bold text-gray-800">Obrigado pela avaliação!</p>
            <p class="text-sm text-gray-400 mt-1">Seu feedback é muito importante para nós.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Botão WhatsApp -->
    <?php if ($whatsappNum): ?>
    <div class="mb-6">
        <?php
        $msgItens = implode(', ', array_map(fn($i) => $i['quantidade'].'x '.$i['produto_nome'], $itens));
        $msgWpp   = urlencode("Olá! Estou acompanhando o pedido #{$pedidoId} — {$msgItens}. Total: R$ " . number_format($pedido['total'], 2, ',', '.'));
        ?>
        <a href="https://wa.me/<?= $whatsappNum ?>?text=<?= $msgWpp ?>"
           target="_blank" rel="noopener"
           class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-2xl transition-colors shadow-sm text-sm">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            Falar com a pizzaria no WhatsApp
        </a>
    </div>
    <?php endif; ?>

    <!-- Auto-atualização -->
    <p class="text-center text-gray-400 text-xs" id="update-info">
        Atualiza automaticamente a cada 15 segundos...
    </p>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// =============================================
// Polling de status a cada 15 segundos
// =============================================
const pedidoId     = <?= $pedidoId ?>;
let etapaAtual     = <?= $info['etapa'] ?>;
let statusAnterior = null; // rastreia mudanças para notificações

async function verificarStatus() {
    try {
        const res  = await fetch('/pizzaria/api/status.php?id=' + pedidoId);
        const data = await res.json();

        // Notifica mudança de status (nunca na primeira verificação)
        if (data.status && statusAnterior !== null && data.status !== statusAnterior) {
            notificarMudancaStatus(data.status);
        }
        if (data.status) statusAnterior = data.status;

        if (data.status === 'cancelado' || (data.status && data.etapa !== etapaAtual)) {
            window.location.reload();
            return;
        }

        const info = document.getElementById('update-info');
        if (info) info.textContent =
            'Última verificação: ' + new Date().toLocaleTimeString('pt-BR') + ' — próxima em 15s';
    } catch (e) {}
}

// =============================================
// Notificações do navegador
// =============================================
function notificarMudancaStatus(novoStatus) {
    if (Notification.permission !== 'granted') return;
    const mensagens = {
        'confirmado':  '✅ Seu pedido foi confirmado!',
        'em_preparo':  '👨‍🍳 Seu pedido está sendo preparado',
        'saiu_entrega': '🛵 Seu pedido saiu para entrega!',
        'entregue':    '🎉 Pedido entregue! Bom apetite!',
        'pronto':      '✅ Pedido pronto para retirada!',
        'cancelado':   '❌ Seu pedido foi cancelado',
    };
    const msg = mensagens[novoStatus] || 'Seu pedido foi atualizado';
    try {
        new Notification('Bella Napoli 🍕', {
            body: msg,
            icon: '/pizzaria/assets/icon-192.png',
            badge: '/pizzaria/assets/icon-192.png',
            tag: 'pedido-status',
            renotify: true,
        });
    } catch (e) {}
}

function ativarNotificacoes() {
    if (!('Notification' in window)) {
        document.getElementById('notif-banner')?.classList.add('hidden');
        return;
    }
    Notification.requestPermission().then(function(permissao) {
        document.getElementById('notif-banner')?.classList.add('hidden');
        if (permissao === 'granted') {
            // Registra status atual como ponto de partida
            statusAnterior = <?= json_encode($pedido['status']) ?>;
        }
    });
}

// Exibe banner de notificação após 2s (apenas se permissão ainda não foi decidida)
if ('Notification' in window && Notification.permission === 'default') {
    setTimeout(function() {
        const banner = document.getElementById('notif-banner');
        if (banner) banner.classList.remove('hidden');
    }, 2000);
}

// Inicializa statusAnterior quando permissão já foi concedida anteriormente
if ('Notification' in window && Notification.permission === 'granted') {
    statusAnterior = <?= json_encode($pedido['status']) ?>;
}

// Não faz polling se já concluído
if (etapaAtual !== 4 && <?= $pedido['status'] === 'cancelado' ? 'false' : 'true' ?>) {
    setInterval(verificarStatus, 15000);
}

// ── Avaliação por estrelas ──
const AV_LABELS = ['', 'Péssimo', 'Ruim', 'Regular', 'Bom', 'Excelente!'];
let notaSelecionada = 0;

function selecionarEstrela(nota) {
    notaSelecionada = nota;
    document.querySelectorAll('.estrela').forEach((el, i) => {
        el.classList.toggle('text-yellow-400', i < nota);
        el.classList.toggle('text-gray-200',   i >= nota);
    });
    const lbl = document.getElementById('av-label');
    if (lbl) lbl.textContent = AV_LABELS[nota] || '';
    const erroEl = document.getElementById('av-erro');
    if (erroEl) erroEl.classList.add('hidden');
}

async function enviarAvaliacao() {
    if (!notaSelecionada) {
        document.getElementById('av-erro').classList.remove('hidden');
        return;
    }
    const btn = document.getElementById('btn-avaliar');
    btn.disabled = true;
    btn.textContent = 'Enviando…';
    const comentario = document.getElementById('av-comentario').value.trim();
    try {
        const res = await fetch('/pizzaria/api/avaliacao.php?id=' + pedidoId, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nota: notaSelecionada, comentario })
        });
        if (res.ok) {
            document.getElementById('av-form').classList.add('hidden');
            document.getElementById('av-obrigado').classList.remove('hidden');
        }
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Erro — tente novamente';
    }
}

// ── Countdown de entrega ──
let secsLeft = <?= (int) $segundosRestantes ?>;
const minEl  = document.getElementById('countdown-min');
const secEl  = document.getElementById('countdown-sec');
const barEl  = document.getElementById('progress-bar');
const totalSecs = <?= $tempoEntrega * 60 ?>;

if (minEl && secsLeft > 0) {
    const tick = () => {
        if (secsLeft <= 0) {
            minEl.closest('div.inline-flex').innerHTML =
                '<p class="text-orange-600 font-bold text-lg">🛵 Chegando a qualquer momento!</p>' +
                '<p class="text-orange-400 text-sm mt-1">Seu pedido está a caminho</p>';
            return;
        }
        secsLeft--;
        minEl.textContent = Math.floor(secsLeft / 60);
        secEl.textContent = String(secsLeft % 60).padStart(2, '0');
        const pct = Math.min(100, Math.round((1 - secsLeft / totalSecs) * 100));
        if (barEl) barEl.style.width = pct + '%';
    };
    setInterval(tick, 1000);
}
</script>
