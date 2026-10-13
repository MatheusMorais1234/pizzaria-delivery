<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo      = getPDO();
$pendentes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
$totalDia  = $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status='entregue' AND DATE(created_at)=CURDATE()")->fetchColumn();

$pageTitle = 'Pedidos — Bella Napoli';
include __DIR__ . '/includes/layout_top.php';
?>

<style>
    /* Kanban: colunas com scroll independente */
    #kanban-board {
        display: grid;
        grid-template-columns: repeat(4, minmax(280px, 1fr));
        gap: 16px;
        height: calc(100vh - 210px);
        overflow-x: auto;
    }
    .kanban-col {
        display: flex;
        flex-direction: column;
        min-height: 0;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,.07);
        overflow: hidden;
    }
    .kanban-col-body {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .kanban-col-body::-webkit-scrollbar { width: 4px; }
    .kanban-col-body::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }

    /* Card */
    .pedido-card {
        background: #fff;
        border-radius: 10px;
        border-left: 4px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
        cursor: pointer;
        transition: box-shadow .15s, transform .15s;
        overflow: hidden;
    }
    .pedido-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); transform: translateY(-1px); }
    .pedido-card.status-pendente     { border-left-color: #F59E0B; }
    .pedido-card.status-em_preparo   { border-left-color: #3B82F6; }
    .pedido-card.status-saiu_entrega { border-left-color: #F97316; }
    .pedido-card.status-entregue     { border-left-color: #22C55E; }

    /* Barra de tempo no topo do card */
    .time-bar {
        height: 3px;
        border-radius: 0;
        transition: width .5s;
    }
    .time-bar.ok       { background: #22C55E; }
    .time-bar.warning  { background: #F59E0B; }
    .time-bar.late     { background: #EF4444; }

    /* Animação de entrada do card */
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .card-new { animation: slideIn .3s ease forwards; }

    /* Toggle switch */
    .toggle-input:checked + .toggle-track { background: #DC2626; }
    .toggle-input:checked + .toggle-track .toggle-thumb { transform: translateX(18px); }
</style>

<?php
$tempoEsperaAtual = (int)getConfig('tempo_espera_atual', '0');
?>

<!-- ══ WIDGET TEMPO DE ESPERA ══════════════════════════════════ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 mb-4 flex flex-wrap items-center gap-3">
    <!-- Ícone + label -->
    <div class="flex items-center gap-2 flex-shrink-0">
        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">Tempo de espera:</span>
        <span id="tempo-espera-display"
              class="text-sm font-extrabold <?= $tempoEsperaAtual > 0 ? 'text-amber-600' : 'text-gray-400' ?> whitespace-nowrap">
            <?= $tempoEsperaAtual > 0 ? $tempoEsperaAtual . ' min' : 'Desativado' ?>
        </span>
    </div>

    <!-- Botões de preset -->
    <div class="flex flex-wrap items-center gap-1.5">
        <?php foreach ([20, 30, 45, 60] as $min): ?>
        <button onclick="setTempoEspera(<?= $min ?>)"
                class="text-xs font-bold px-3 py-1.5 rounded-lg border-2 border-gray-200 text-gray-600
                       hover:border-amber-400 hover:text-amber-700 hover:bg-amber-50 transition-colors">
            <?= $min ?>min
        </button>
        <?php endforeach; ?>

        <!-- Outro -->
        <button onclick="mostrarInputCustom()"
                id="btn-outro-tempo"
                class="text-xs font-bold px-3 py-1.5 rounded-lg border-2 border-gray-200 text-gray-600
                       hover:border-amber-400 hover:text-amber-700 hover:bg-amber-50 transition-colors">
            Outro
        </button>

        <!-- Input custom (oculto por padrão) -->
        <div id="input-custom-tempo" class="hidden flex items-center gap-1">
            <input type="number" id="valor-custom-tempo" min="1" max="240" placeholder="min"
                   class="w-16 text-xs border-2 border-amber-400 rounded-lg px-2 py-1.5 text-center font-bold focus:outline-none focus:ring-2 focus:ring-amber-300">
            <button onclick="confirmarCustom()"
                    class="text-xs font-bold px-2 py-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors">
                OK
            </button>
        </div>

        <!-- Desativar -->
        <button onclick="setTempoEspera(0)"
                class="text-xs font-bold px-3 py-1.5 rounded-lg border-2 border-gray-200 text-gray-500
                       hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition-colors">
            Desativar
        </button>
    </div>
</div>

<script>
function setTempoEspera(min) {
    const fd = new FormData();
    fd.append('minutos', min);
    fetch('/pizzaria/api/tempo-espera.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok !== undefined) {
                const el = document.getElementById('tempo-espera-display');
                if (d.minutos > 0) {
                    el.textContent = d.minutos + ' min';
                    el.className = 'text-sm font-extrabold text-amber-600 whitespace-nowrap';
                } else {
                    el.textContent = 'Desativado';
                    el.className = 'text-sm font-extrabold text-gray-400 whitespace-nowrap';
                }
                // Oculta input custom se estava aberto
                document.getElementById('input-custom-tempo').classList.add('hidden');
                document.getElementById('btn-outro-tempo').classList.remove('hidden');
            }
        })
        .catch(() => {});
}

function mostrarInputCustom() {
    document.getElementById('input-custom-tempo').classList.remove('hidden');
    document.getElementById('input-custom-tempo').classList.add('flex');
    document.getElementById('btn-outro-tempo').classList.add('hidden');
    document.getElementById('valor-custom-tempo').focus();
}

function confirmarCustom() {
    const v = parseInt(document.getElementById('valor-custom-tempo').value, 10);
    if (v > 0) setTempoEspera(v);
}

document.addEventListener('keydown', function(e) {
    const inp = document.getElementById('valor-custom-tempo');
    if (e.key === 'Enter' && document.activeElement === inp) confirmarCustom();
    if (e.key === 'Escape' && document.activeElement === inp) {
        document.getElementById('input-custom-tempo').classList.add('hidden');
        document.getElementById('input-custom-tempo').classList.remove('flex');
        document.getElementById('btn-outro-tempo').classList.remove('hidden');
    }
});
</script>

<?php if (isset($_GET['adicionado'])): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Pedido do WhatsApp adicionado ao kanban com sucesso!
</div>
<?php endif; ?>

<!-- ══ TOPBAR ══════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center gap-3 mb-5">

    <!-- Título + badge data -->
    <div class="flex items-center gap-3 mr-2">
        <h1 class="text-xl font-extrabold text-gray-900">Pedidos</h1>
        <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-full">
            <?= date('d/m/Y') ?>
        </span>
        <span id="badge-total" class="bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full">
            <?= $pendentes ?> pendente<?= $pendentes != 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Toggle cancelados -->
    <button id="btn-cancelados" onclick="toggleCancelados()"
            class="hidden items-center gap-2 text-xs font-bold px-3 py-2 rounded-xl border border-red-200 text-red-500 bg-red-50 hover:bg-red-100 transition-colors whitespace-nowrap">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span id="btn-cancelados-label">Cancelados (0)</span>
    </button>

    <!-- Busca -->
    <div class="relative flex-1 min-w-[200px] max-w-xs">
        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
        </svg>
        <input id="busca-pedido" type="search" placeholder="Nome ou #número…"
               oninput="filtrarBusca(this.value)"
               class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50
                      focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent">
    </div>

    <!-- Spacer -->
    <div class="flex-1"></div>

    <!-- Novo pedido WhatsApp -->
    <a href="/pizzaria/admin/pedido-manual.php"
       class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors whitespace-nowrap shadow-sm">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        Pedido WhatsApp
    </a>

    <!-- Atualização -->
    <span class="text-xs text-gray-400 bg-white border border-gray-200 px-3 py-1.5 rounded-full whitespace-nowrap">
        Atualiza em <span id="countdown" class="font-bold text-gray-700">30</span>s
    </span>
</div>

<!-- ══ KANBAN ══════════════════════════════════════════════════ -->
<div id="kanban-board">

    <!-- Col 1: Novos -->
    <div class="kanban-col">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 flex-shrink-0"></span>
                <span class="font-bold text-gray-800 text-sm">Novos</span>
                <span id="count-pendente"
                      class="bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-0.5 rounded-full">0</span>
            </div>
            <!-- Toggle confirmação automática -->
            <label class="flex items-center gap-1.5 cursor-pointer" title="Confirmação automática">
                <span class="text-xs text-gray-400 hidden lg:block">Auto</span>
                <div class="relative flex-shrink-0">
                    <input type="checkbox" id="auto-confirm" class="toggle-input sr-only">
                    <div class="toggle-track w-9 h-5 bg-gray-300 rounded-full transition-colors duration-200 relative">
                        <div class="toggle-thumb absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"></div>
                    </div>
                </div>
            </label>
        </div>
        <div class="kanban-col-body" id="col-pendente">
            <div class="text-center text-gray-300 text-sm py-8" id="empty-pendente">Nenhum pedido novo</div>
        </div>
    </div>

    <!-- Col 2: Em andamento -->
    <div class="kanban-col">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2 flex-shrink-0">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 flex-shrink-0"></span>
            <span class="font-bold text-gray-800 text-sm">Em andamento</span>
            <span id="count-em_preparo"
                  class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full">0</span>
        </div>
        <div class="kanban-col-body" id="col-em_preparo">
            <div class="text-center text-gray-300 text-sm py-8" id="empty-em_preparo">Nenhum em preparo</div>
        </div>
    </div>

    <!-- Col 3: Saiu para entrega -->
    <div class="kanban-col">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2 flex-shrink-0">
            <span class="w-2.5 h-2.5 rounded-full bg-orange-400 flex-shrink-0"></span>
            <span class="font-bold text-gray-800 text-sm">Saiu p/ entrega</span>
            <span id="count-saiu_entrega"
                  class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-0.5 rounded-full">0</span>
        </div>
        <div class="kanban-col-body" id="col-saiu_entrega">
            <div class="text-center text-gray-300 text-sm py-8" id="empty-saiu_entrega">Nenhum em rota</div>
        </div>
    </div>

    <!-- Col 4: Concluídos -->
    <div class="kanban-col">
        <div class="px-4 py-3 border-b border-gray-100 flex-shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 flex-shrink-0"></span>
                <span class="font-bold text-gray-800 text-sm">Concluídos</span>
                <span id="count-entregue"
                      class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">0</span>
            </div>
            <p class="text-xs text-gray-400">
                Total do dia:
                <span id="total-dia" class="font-bold text-green-600">
                    R$ <?= number_format($totalDia, 2, ',', '.') ?>
                </span>
            </p>
        </div>
        <div class="kanban-col-body" id="col-entregue">
            <div class="text-center text-gray-300 text-sm py-8" id="empty-entregue">Nenhum entregue ainda</div>
        </div>
    </div>

</div>

<!-- ══ MODAL CANCELAMENTO ══════════════════════════════════════ -->
<div id="modal-cancelamento"
     class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden"
         onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="bg-red-600 px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-red-200 text-xs font-semibold uppercase tracking-widest mb-0.5">Cancelar pedido</p>
                <h3 class="text-white font-extrabold text-lg" id="cancel-titulo">Pedido #—</h3>
            </div>
            <button onclick="fecharModalCancel()"
                    class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="px-6 pt-5 pb-6 space-y-5">

            <!-- Motivo (chips) -->
            <div>
                <p class="text-sm font-semibold text-gray-700 mb-3">Selecione o motivo <span class="text-red-500">*</span></p>
                <div class="grid grid-cols-2 gap-2" id="motivos-grid">
                    <?php
                    $motivos = [
                        'Produto indisponível',
                        'Fora da área de entrega',
                        'Sem contato com cliente',
                        'Endereço não localizado',
                        'Pedido duplicado',
                        'Problema no preparo',
                    ];
                    foreach ($motivos as $m): ?>
                    <button type="button"
                            onclick="selecionarMotivo(this)"
                            data-motivo="<?= htmlspecialchars($m) ?>"
                            class="motivo-chip text-left text-sm px-3 py-2.5 rounded-xl border-2 border-gray-200 text-gray-600 hover:border-red-300 hover:text-red-700 hover:bg-red-50 transition-all font-medium">
                        <?= htmlspecialchars($m) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <p id="erro-motivo" class="hidden text-xs text-red-500 mt-2 font-semibold">Selecione um motivo para continuar.</p>
            </div>

            <!-- Mensagem adicional -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Mensagem para o cliente
                    <span class="text-gray-400 font-normal">(opcional)</span>
                </label>
                <textarea id="cancel-mensagem" rows="3" maxlength="300"
                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
                          placeholder="Ex: Pedimos desculpas pelo inconveniente. Entre em contato pelo WhatsApp para mais informações…"></textarea>
                <p class="text-xs text-gray-400 mt-1">Será exibida ao cliente na página de acompanhamento.</p>
            </div>

            <!-- Botões -->
            <div class="flex gap-3 pt-1">
                <button onclick="fecharModalCancel()"
                        class="flex-1 border-2 border-gray-200 text-gray-600 font-semibold py-2.5 rounded-xl hover:bg-gray-50 transition-colors text-sm">
                    Voltar
                </button>
                <button id="btn-confirmar-cancel" onclick="confirmarCancelamento()"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Confirmar cancelamento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ SECAO CANCELADOS ════════════════════════════════════════ -->
<div id="secao-cancelados" class="mt-4 hidden">
    <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-2.5 h-2.5 rounded-full bg-red-400 flex-shrink-0"></span>
            <span class="font-bold text-gray-700 text-sm">Pedidos cancelados</span>
            <span id="count-cancelado" class="bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">0</span>
            <span class="text-xs text-gray-400 ml-1">— clique para ver detalhes e desfazer</span>
        </div>
        <div id="col-cancelado" class="flex flex-wrap gap-3"></div>
    </div>
</div>

<!-- ══ MODAL DETALHE ══════════════════════════════════════════ -->
<div id="modal-overlay"
     class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4"
     onclick="fecharModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col"
         onclick="event.stopPropagation()">

        <!-- Header colorido por status -->
        <div id="modal-header"
             class="flex items-center justify-between px-6 py-5 flex-shrink-0 rounded-t-2xl bg-gray-900">
            <div>
                <p class="text-white/60 text-xs font-semibold uppercase tracking-widest mb-0.5">Pedido</p>
                <h2 class="font-extrabold text-white text-2xl" id="modal-titulo">#—</h2>
            </div>
            <div class="flex items-center gap-3">
                <span id="modal-badge" class="px-3 py-1.5 rounded-full text-xs font-bold"></span>
                <button onclick="fecharModal()"
                        class="w-9 h-9 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Body com scroll -->
        <div class="flex-1 overflow-y-auto" id="modal-corpo">
            <div class="flex justify-center py-12">
                <svg class="w-8 h-8 text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
            </div>
        </div>

        <!-- Footer com botão de ação -->
        <div class="px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50 rounded-b-2xl"
             id="modal-footer"></div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
// ══════════════════════════════════════════════════════════════
// Config
// ══════════════════════════════════════════════════════════════
const COLS = ['pendente', 'em_preparo', 'saiu_entrega', 'entregue'];

const CFG = {
    pendente:     { cor: '#F59E0B', badge: 'bg-yellow-100 text-yellow-800', label: 'Pendente',     proximo: 'em_preparo',   btnLabel: 'Iniciar preparo',   btnCor: 'bg-blue-600 hover:bg-blue-700' },
    em_preparo:   { cor: '#3B82F6', badge: 'bg-blue-100 text-blue-800',     label: 'Em preparo',   proximo: 'saiu_entrega', btnLabel: 'Saiu p/ entrega',   btnCor: 'bg-orange-500 hover:bg-orange-600' },
    saiu_entrega: { cor: '#F97316', badge: 'bg-orange-100 text-orange-800', label: 'Em entrega',   proximo: 'entregue',     btnLabel: 'Confirmar entrega', btnCor: 'bg-green-600 hover:bg-green-700' },
    entregue:     { cor: '#22C55E', badge: 'bg-green-100 text-green-800',   label: 'Entregue',     proximo: null,           btnLabel: null,                btnCor: '' },
    cancelado:    { cor: '#6B7280', badge: 'bg-gray-100 text-gray-600',     label: 'Cancelado',    proximo: null,           btnLabel: null,                btnCor: '' },
};

// Status anterior para navegar de volta
const ANTERIOR = {
    em_preparo:   { status: 'pendente',     label: '← Voltar para Novos' },
    saiu_entrega: { status: 'em_preparo',   label: '← Voltar para Em preparo' },
    entregue:     { status: 'saiu_entrega', label: '← Voltar para Em rota' },
};

let dadosAtuais      = { pendente: [], em_preparo: [], saiu_entrega: [], entregue: [], cancelado: [] };
let termoBusca       = '';
let modalAberto      = null;
let canceladosVisiveis = false;

// ══════════════════════════════════════════════════════════════
// Barra de tempo (quanto tempo faz o pedido foi feito)
// ══════════════════════════════════════════════════════════════
function calcTimebar(createdAt) {
    const minutos = (Date.now() - new Date(createdAt.replace(' ', 'T')).getTime()) / 60000;
    const pct     = Math.min(100, (minutos / 60) * 100);
    const cls     = minutos < 50 ? 'ok' : minutos < 60 ? 'warning' : 'late';
    return { pct, cls, minutos: Math.round(minutos) };
}

// ══════════════════════════════════════════════════════════════
// Renderiza um card
// ══════════════════════════════════════════════════════════════
function renderCard(p) {
    const cfg  = CFG[p.status];
    const tb   = calcTimebar(p.created_at);
    const hora = new Date(p.created_at.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const data = new Date(p.created_at.replace(' ', 'T')).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

    const isRetirada = p.tipo_entrega === 'retirada';
    const isMesa     = p.tipo_entrega === 'mesa';
    const PAG_INFO = {
        dinheiro: { emoji: '💵', label: 'Dinheiro', cls: 'bg-yellow-50 text-yellow-700' },
        credito:  { emoji: '💳', label: 'Crédito',  cls: 'bg-blue-50 text-blue-700'    },
        debito:   { emoji: '💳', label: 'Débito',   cls: 'bg-indigo-50 text-indigo-700' },
        cartao:   { emoji: '💳', label: 'Cartão',   cls: 'bg-blue-50 text-blue-700'    },
        pix:      { emoji: '📱', label: 'PIX',      cls: 'bg-green-50 text-green-700'  },
        mesa:     { emoji: '🍽️', label: 'Na mesa',  cls: 'bg-orange-50 text-orange-700' },
        'Na mesa':{ emoji: '🍽️', label: 'Na mesa',  cls: 'bg-orange-50 text-orange-700' },
    };
    const pag = PAG_INFO[p.forma_pagamento] || PAG_INFO.dinheiro;
    const btnLabel   = cfg.proximo
        ? (isRetirada && cfg.proximo === 'saiu_entrega' ? 'Pronto p/ retirar'
          : isMesa && cfg.proximo === 'saiu_entrega'    ? 'Pronto p/ servir'
          : cfg.btnLabel)
        : null;

    const btnHtml = cfg.proximo
        ? `<button onclick="event.stopPropagation(); avancarStatus(${p.id}, '${cfg.proximo}', this)"
                   class="${cfg.btnCor} text-white text-xs font-bold py-2 px-3 rounded-lg w-full
                          flex items-center justify-center gap-1 transition-colors active:scale-95 mt-3">
               ${btnLabel} ›
           </button>`
        : `<div class="mt-3 flex items-center justify-center gap-1.5 text-green-600 text-xs font-bold">
               <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
               </svg>
               ${isRetirada ? 'Retirado' : 'Entregue'}
           </div>`;

    const card = document.createElement('div');
    card.className = `pedido-card status-${p.status} card-new`;
    card.dataset.id     = p.id;
    card.dataset.nome   = p.cliente_nome.toLowerCase();
    card.dataset.status = p.status;
    card.onclick = () => abrirModal(p);

    // Itens com customizações inline por item
    const itensCardHtml = (p.itens || []).map(i => {
        const sems = i.ingredientes_removidos || [];
        const adds = (i.adicionais || []).map(a => a.nome);
        const tags = [
            ...sems.map(s => `<span class="text-red-400">sem ${esc(s)}</span>`),
            ...adds.map(a => `<span class="text-green-600">+ ${esc(a)}</span>`),
        ].join('<span class="text-gray-300 mx-0.5">·</span>');
        return `<div class="text-xs leading-snug">
            <span class="text-gray-600 font-medium">${i.quantidade}× ${esc(i.nome)}</span>
            ${tags ? `<div class="pl-3 mt-0.5">${tags}</div>` : ''}
        </div>`;
    }).join('');

    card.innerHTML = `
        <div class="time-bar ${tb.cls}" style="width:100%"></div>
        <div class="p-3">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="min-w-0">
                    <span class="font-bold text-gray-900 text-sm leading-tight block truncate">${esc(p.cliente_nome)}</span>
                    <div class="flex flex-wrap gap-1 mt-0.5">
                        ${isRetirada ? `<span class="text-xs font-bold bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">Retirada</span>` : ''}
                        ${isMesa && p.mesa ? `<span class="text-xs font-bold bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">Mesa ${esc(p.mesa)}</span>` : ''}
                        <span class="text-xs font-bold ${pag.cls} px-1.5 py-0.5 rounded">${pag.emoji} ${pag.label}${p.troco_para ? ' · troco R$' + fmtBRL(p.troco_para) : ''}</span>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="font-extrabold text-red-600 text-base leading-tight block">R$ ${fmtBRL(p.total)}</span>
                    <span class="text-gray-400 text-xs">às ${hora}</span>
                </div>
            </div>
            ${itensCardHtml
                ? `<div class="space-y-1 mb-1">${itensCardHtml}</div>`
                : ''}
            <div class="flex items-center gap-2 mt-2">
                <div class="flex-1 h-1 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full time-bar ${tb.cls}" style="width:${tb.pct}%"></div>
                </div>
                <button onclick="event.stopPropagation(); imprimirComanda(${p.id})"
                        title="Imprimir comanda"
                        class="flex-shrink-0 w-6 h-6 flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                </button>
                <span class="text-gray-400 text-xs flex-shrink-0 font-medium">${fmtTempo(tb.minutos)}</span>
            </div>
            ${btnHtml}
        </div>`;

    return card;
}

// ══════════════════════════════════════════════════════════════
// Renderiza todas as colunas
// ══════════════════════════════════════════════════════════════
function renderTudo(dados) {
    dadosAtuais = dados.pedidos;

    COLS.forEach(status => {
        const col   = document.getElementById('col-' + status);
        const empty = document.getElementById('empty-' + status);
        const count = document.getElementById('count-' + status);
        const lista = dadosAtuais[status] || [];

        // Filtra pela busca
        const filtrada = lista.filter(p =>
            !termoBusca ||
            p.cliente_nome.toLowerCase().includes(termoBusca) ||
            String(p.id).includes(termoBusca)
        );

        // Limpa cards (mantém o empty placeholder)
        Array.from(col.querySelectorAll('.pedido-card')).forEach(el => el.remove());

        count.textContent = lista.length;
        empty.classList.toggle('hidden', filtrada.length > 0);

        filtrada.forEach(p => col.appendChild(renderCard(p)));
    });

    // Cancelados
    const cancelados = dadosAtuais.cancelado || [];
    const colCancel  = document.getElementById('col-cancelado');
    const btnCancel  = document.getElementById('btn-cancelados');
    const secCancel  = document.getElementById('secao-cancelados');
    document.getElementById('count-cancelado').textContent = cancelados.length;
    document.getElementById('btn-cancelados-label').textContent = `Cancelados (${cancelados.length})`;
    colCancel.innerHTML = '';
    cancelados.forEach(p => colCancel.appendChild(renderCardCancelado(p)));

    if (cancelados.length > 0) {
        btnCancel.classList.remove('hidden');
        btnCancel.classList.add('flex');
        secCancel.classList.toggle('hidden', !canceladosVisiveis);
    } else {
        btnCancel.classList.add('hidden');
        btnCancel.classList.remove('flex');
        secCancel.classList.add('hidden');
    }

    // Atualiza total do dia
    if (dados.total_dia !== undefined) {
        document.getElementById('total-dia').textContent = 'R$ ' + fmtBRL(dados.total_dia);
    }

    // Atualiza badge de pendentes no header
    const pend = (dadosAtuais.pendente || []).length;
    document.getElementById('badge-total').textContent =
        pend + ' pendente' + (pend !== 1 ? 's' : '');
}

function toggleCancelados() {
    canceladosVisiveis = !canceladosVisiveis;
    document.getElementById('secao-cancelados').classList.toggle('hidden', !canceladosVisiveis);
}

function renderCardCancelado(p) {
    const hora = new Date(p.created_at.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const chip = document.createElement('div');
    chip.className = 'flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors opacity-70 hover:opacity-100';
    chip.onclick = () => abrirModal(p);
    chip.innerHTML = `
        <div class="w-7 h-7 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold text-gray-700 text-sm leading-tight">#${p.id} ${esc(p.cliente_nome)}</p>
            <p class="text-xs text-gray-400">R$ ${fmtBRL(p.total)} · ${hora}</p>
        </div>`;
    return chip;
}

// ══════════════════════════════════════════════════════════════
// Fetch do kanban
// ══════════════════════════════════════════════════════════════
async function fetchKanban(primeiro = false) {
    try {
        const res  = await fetch('/pizzaria/api/pedidos-kanban.php');
        const data = await res.json();

        if (!primeiro) {
            // Verifica novos pedidos pendentes
            const novosPend = (data.pedidos.pendente || []).length;
            const atualPend = (dadosAtuais.pendente || []).length;
            if (novosPend > atualPend) {
                tocarSom();
                mostrarBanner(novosPend - atualPend);
                // Confirmação automática
                if (document.getElementById('auto-confirm').checked) {
                    const novos = data.pedidos.pendente.slice(0, novosPend - atualPend);
                    for (const p of novos) {
                        await enviarStatus(p.id, 'em_preparo');
                    }
                    // Re-fetch após auto-confirmar
                    const res2 = await fetch('/pizzaria/api/pedidos-kanban.php');
                    renderTudo(await res2.json());
                    return;
                }
            }
        }

        renderTudo(data);
    } catch(e) { /* silencioso */ }
}

// ══════════════════════════════════════════════════════════════
// Avançar status
// ══════════════════════════════════════════════════════════════
async function avancarStatus(pedidoId, novoStatus, btn) {
    btn.disabled  = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>';
    const ok = await enviarStatus(pedidoId, novoStatus);
    if (ok) {
        await fetchKanban();
        // Se modal estava aberto para esse pedido, fecha
        if (modalAberto === pedidoId) fecharModal();
    } else {
        btn.disabled  = false;
        btn.textContent = 'Erro — tente novamente';
    }
}

async function voltarStatus(pedidoId, statusAnterior, btn) {
    btn.disabled  = true;
    btn.style.opacity = '0.5';
    const ok = await enviarStatus(pedidoId, statusAnterior);
    if (ok) {
        await fetchKanban();
        if (modalAberto === pedidoId) fecharModal();
    } else {
        btn.disabled = false;
        btn.style.opacity = '';
    }
}

// ── Modal de cancelamento ─────────────────────────────────────
let cancelPedidoId = null;
let motivoSelecionado = null;

function cancelarPedido(pedidoId) {
    cancelPedidoId = pedidoId;
    motivoSelecionado = null;

    // Reset visual
    document.querySelectorAll('.motivo-chip').forEach(c => {
        c.classList.remove('border-red-500', 'bg-red-50', 'text-red-700');
        c.classList.add('border-gray-200', 'text-gray-600');
    });
    document.getElementById('cancel-mensagem').value = '';
    document.getElementById('erro-motivo').classList.add('hidden');
    document.getElementById('cancel-titulo').textContent = 'Pedido #' + pedidoId;

    const m = document.getElementById('modal-cancelamento');
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function fecharModalCancel() {
    const m = document.getElementById('modal-cancelamento');
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}

function selecionarMotivo(btn) {
    document.querySelectorAll('.motivo-chip').forEach(c => {
        c.classList.remove('border-red-500', 'bg-red-50', 'text-red-700');
        c.classList.add('border-gray-200', 'text-gray-600');
    });
    btn.classList.remove('border-gray-200', 'text-gray-600');
    btn.classList.add('border-red-500', 'bg-red-50', 'text-red-700');
    motivoSelecionado = btn.dataset.motivo;
    document.getElementById('erro-motivo').classList.add('hidden');
}

async function confirmarCancelamento() {
    if (!motivoSelecionado) {
        document.getElementById('erro-motivo').classList.remove('hidden');
        return;
    }

    const mensagem = document.getElementById('cancel-mensagem').value.trim();
    const motivo   = mensagem ? motivoSelecionado + ': ' + mensagem : motivoSelecionado;

    const btn = document.getElementById('btn-confirmar-cancel');
    btn.disabled = true;
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
    </svg> Cancelando…`;

    try {
        const res = await fetch('/pizzaria/api/status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pedido_id: cancelPedidoId, status: 'cancelado', motivo_cancelamento: motivo })
        });
        const d = await res.json();
        if (d.sucesso) {
            fecharModalCancel();
            fecharModal();
            await fetchKanban();
        }
    } catch {}

    btn.disabled = false;
    btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    </svg> Confirmar cancelamento`;
}

// ══════════════════════════════════════════════════════════════
// Impressão de comanda
// ══════════════════════════════════════════════════════════════
function imprimirComanda(pedidoId) {
    // Busca pedido nos dados carregados
    let pedido = null;
    for (const status of Object.keys(dadosAtuais)) {
        pedido = (dadosAtuais[status] || []).find(p => p.id === pedidoId);
        if (pedido) break;
    }
    if (!pedido) return;

    const hora = new Date(pedido.created_at.replace(' ', 'T')).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const data = new Date(pedido.created_at.replace(' ', 'T')).toLocaleDateString('pt-BR');

    const itensHtml = (pedido.itens || []).map(i => {
        const sems = (i.ingredientes_removidos || []).length
            ? `<div style="font-size:11px;color:#555;margin-left:12px">sem ${i.ingredientes_removidos.join(', ')}</div>` : '';
        const adds = (i.adicionais || []).map(a =>
            `<div style="font-size:11px;color:#555;margin-left:12px">+ ${a.nome} (+R$ ${fmtBRL(a.preco)})</div>`).join('');
        return `<tr>
            <td style="padding:5px 4px;border-bottom:1px dashed #ddd;vertical-align:top">
                <strong>${i.quantidade}×</strong> ${esc(i.nome)}${sems}${adds}
            </td>
            <td style="padding:5px 4px;border-bottom:1px dashed #ddd;text-align:right;white-space:nowrap;vertical-align:top">
                R$ ${fmtBRL(i.subtotal)}
            </td>
        </tr>`;
    }).join('');

    const PAG_LABEL = { dinheiro: 'Dinheiro', cartao: 'Cartão', pix: 'PIX', mesa: 'Na mesa', 'Na mesa': 'Na mesa' };
    const tipoLabel = pedido.tipo_entrega === 'retirada' ? 'Retirada na loja'
                    : pedido.tipo_entrega === 'mesa'     ? `Mesa ${pedido.mesa || ''}`
                    : 'Entrega';

    const html = `<!DOCTYPE html><html lang="pt-BR"><head>
    <meta charset="utf-8">
    <title>Comanda #${pedido.id}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 13px; color: #111; width: 80mm; padding: 10px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 2px; }
        .sub { text-align: center; font-size: 11px; color: #555; margin-bottom: 10px; }
        .divider { border: none; border-top: 1px dashed #aaa; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        .total-row td { font-weight: bold; font-size: 15px; padding-top: 8px; }
        .info { font-size: 11px; line-height: 1.6; }
        @media print { body { width: 100%; } }
    </style>
    </head><body>
    <h1>Bella Napoli</h1>
    <div class="sub">Pedido #${pedido.id} — ${data} às ${hora}</div>
    <hr class="divider">
    <table>
        ${itensHtml}
        <tr class="total-row">
            <td style="padding-top:8px">TOTAL</td>
            <td style="padding-top:8px;text-align:right">R$ ${fmtBRL(pedido.total)}</td>
        </tr>
    </table>
    <hr class="divider">
    <div class="info">
        <strong>Cliente:</strong> ${esc(pedido.cliente_nome)}<br>
        <strong>Tel:</strong> ${esc(pedido.cliente_telefone || '—')}<br>
        <strong>Tipo:</strong> ${tipoLabel}<br>
        ${pedido.tipo_entrega !== 'retirada' && pedido.tipo_entrega !== 'mesa' ? `<strong>End:</strong> ${esc(pedido.endereco || '—')}<br>` : ''}
        <strong>Pagamento:</strong> ${PAG_LABEL[pedido.forma_pagamento] || pedido.forma_pagamento}
        ${pedido.troco_para ? ` · Troco p/ R$ ${fmtBRL(pedido.troco_para)}` : ''}<br>
        ${pedido.observacao ? `<strong>Obs:</strong> ${esc(pedido.observacao)}<br>` : ''}
    </div>
    <hr class="divider">
    <div style="text-align:center;font-size:10px;margin-top:4px">Obrigado pela preferência!</div>
    <script>window.onload=()=>{window.print();}<\/script>
    </body></html>`;

    const w = window.open('', '_blank', 'width=380,height=600');
    w.document.write(html);
    w.document.close();
}

async function enviarStatus(pedidoId, novoStatus) {
    try {
        const res  = await fetch('/pizzaria/api/status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pedido_id: pedidoId, status: novoStatus })
        });
        const d = await res.json();
        return d.sucesso;
    } catch { return false; }
}

// ══════════════════════════════════════════════════════════════
// Modal de detalhe — redesenhado
// ══════════════════════════════════════════════════════════════
const HEADER_COR = {
    pendente:     'bg-yellow-500',
    em_preparo:   'bg-blue-600',
    saiu_entrega: 'bg-orange-500',
    entregue:     'bg-green-600',
    cancelado:    'bg-gray-600',
};

function abrirModal(p) {
    modalAberto = p.id;
    const cfg = CFG[p.status];
    const PAG_INFO = {
        dinheiro: { emoji: '💵', label: 'Dinheiro',          cls: 'bg-yellow-50 text-yellow-700 border border-yellow-200'  },
        credito:  { emoji: '💳', label: 'Crédito na entrega', cls: 'bg-blue-50 text-blue-700 border border-blue-200'       },
        debito:   { emoji: '💳', label: 'Débito na entrega',  cls: 'bg-indigo-50 text-indigo-700 border border-indigo-200' },
        cartao:   { emoji: '💳', label: 'Cartão na entrega',  cls: 'bg-blue-50 text-blue-700 border border-blue-200'       },
        pix:      { emoji: '📱', label: 'PIX na entrega',     cls: 'bg-green-50 text-green-700 border border-green-200'    },
    };
    const pag = PAG_INFO[p.forma_pagamento] || { emoji: '💵', label: p.forma_pagamento, cls: 'bg-yellow-50 text-yellow-700 border border-yellow-200' };

    // Header colorido por status
    const header = document.getElementById('modal-header');
    header.className = 'flex items-center justify-between px-6 py-5 flex-shrink-0 rounded-t-2xl ' + (HEADER_COR[p.status] || 'bg-gray-900');
    document.getElementById('modal-titulo').textContent = '#' + p.id;

    // Badge no header
    const badge = document.getElementById('modal-badge');
    badge.className = 'px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 text-white';
    badge.textContent = cfg.label;

    // Itens
    const itens = p.itens || [];
    const itensHtml = itens.length
        ? itens.map(i => {
            const semIng = (i.ingredientes_removidos || []).length
                ? `<p class="text-xs text-red-500 font-semibold mt-1">sem ${i.ingredientes_removidos.join(', ')}</p>` : '';
            const comAdd = (i.adicionais || []).length
                ? `<div class="flex flex-wrap gap-1 mt-1">${(i.adicionais).map(a =>
                    `<span class="text-xs bg-green-100 text-green-700 font-semibold px-2 py-0.5 rounded-full">+ ${esc(a.nome)} +R$ ${fmtBRL(a.preco)}</span>`
                  ).join('')}</div>` : '';
            return `
            <div class="py-3 border-b border-gray-100 last:border-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold text-gray-600 flex-shrink-0 mt-0.5">
                            ${i.quantidade}
                        </span>
                        <div class="min-w-0">
                            <span class="font-semibold text-gray-900 text-sm">${esc(i.nome)}</span>
                            ${semIng}${comAdd}
                        </div>
                    </div>
                    <span class="font-bold text-gray-800 text-sm flex-shrink-0">R$ ${fmtBRL(i.subtotal)}</span>
                </div>
            </div>`;
          }).join('')
        : '<p class="text-gray-400 text-sm py-2">Sem itens registrados</p>';

    // Data/hora do pedido
    const dt = new Date(p.created_at.replace(' ', 'T'));
    const dtFmt = dt.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });

    document.getElementById('modal-corpo').innerHTML = `
        <!-- Cliente -->
        <div class="px-6 pt-5 pb-4 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Cliente</p>
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-white font-extrabold text-base flex-shrink-0">
                    ${esc(p.cliente_nome).charAt(0).toUpperCase()}
                </div>
                <div>
                    <p class="font-extrabold text-gray-900 text-base leading-tight">${esc(p.cliente_nome)}</p>
                    <p class="text-gray-500 text-sm">${esc(p.cliente_telefone)}</p>
                </div>
                <span class="ml-auto text-gray-400 text-xs">${dtFmt}</span>
            </div>
        </div>

        <!-- Endereço / Retirada / Mesa -->
        <div class="px-6 py-4 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">
                ${p.tipo_entrega === 'retirada' ? 'Retirada na loja' : p.tipo_entrega === 'mesa' ? 'Atendimento na mesa' : 'Endereço de entrega'}
            </p>
            ${p.tipo_entrega === 'retirada'
                ? `<span class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-lg">
                       <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                       </svg>
                       Cliente retira na loja
                   </span>`
                : p.tipo_entrega === 'mesa'
                ? `<span class="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-700 bg-orange-50 border border-orange-200 px-3 py-1.5 rounded-lg">
                       <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M5 14h14M7 18h10"/>
                       </svg>
                       Mesa ${p.mesa ? esc(p.mesa) : '—'}
                   </span>`
                : `<p class="text-gray-900 font-medium text-sm leading-relaxed">${esc(p.endereco)}</p>`}

            ${p.observacao
                ? `<div class="mt-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2">
                       <p class="text-xs font-bold text-yellow-700 mb-0.5">Observação</p>
                       <p class="text-yellow-900 text-sm italic">${esc(p.observacao)}</p>
                   </div>`
                : ''}
        </div>

        <!-- Itens -->
        <div class="px-6 py-4 border-b border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Itens do pedido</p>
            ${itensHtml}
        </div>

        <!-- Pagamento -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Pagamento</p>
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold ${pag.cls} px-3 py-1.5 rounded-lg">
                    ${pag.emoji} ${pag.label}
                </span>
                ${p.troco_para ? `<p class="text-xs text-gray-500 mt-1 font-medium">Troco para R$ ${fmtBRL(p.troco_para)}</p>` : ''}
            </div>
        </div>

        <!-- Total -->
        <div class="px-6 py-4 flex items-center justify-between">
            <p class="font-bold text-gray-700 text-base">Total do pedido</p>
            <p class="font-extrabold text-red-600 text-2xl">R$ ${fmtBRL(p.total)}</p>
        </div>
    `;

    // Footer — avançar / voltar / cancelar
    const footer = document.getElementById('modal-footer');
    const ant    = ANTERIOR[p.status];
    const podeCancelar = p.status !== 'entregue' && p.status !== 'cancelado';

    if (p.status === 'cancelado') {
        footer.innerHTML = `
            <div class="space-y-3">
                <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-center">
                    <p class="text-red-800 font-bold text-sm">Pedido cancelado</p>
                    <p class="text-red-600 text-xs mt-1">Se o cliente realizou pagamento, o reembolso deve ser feito manualmente pelo método de pagamento original.</p>
                </div>
                <button onclick="voltarStatus(${p.id}, 'pendente', this)"
                        class="w-full text-gray-600 border border-gray-200 font-semibold py-2.5 px-4 rounded-xl text-sm hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Desfazer cancelamento
                </button>
            </div>`;
    } else {
        let html = '';

        // Botão principal: avançar
        if (cfg.proximo) {
            html += `
            <button onclick="avancarStatus(${p.id}, '${cfg.proximo}', this)"
                    class="${cfg.btnCor} text-white font-extrabold py-3.5 px-6 rounded-xl w-full
                           transition-colors text-sm flex items-center justify-center gap-2 shadow-sm mb-2">
                <span>${cfg.btnLabel}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </button>`;
        } else {
            const conclusaoLabel = p.tipo_entrega === 'mesa' ? 'Pedido servido' : 'Pedido concluído com sucesso';
            html += `
            <div class="flex items-center justify-center gap-2 text-green-700 font-bold py-1 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                ${conclusaoLabel}
            </div>`;
        }

        // Botões secundários: voltar e cancelar
        const secBtns = [];
        if (ant) {
            secBtns.push(`
            <button onclick="voltarStatus(${p.id}, '${ant.status}', this)"
                    class="flex-1 text-gray-600 border border-gray-200 font-semibold py-2.5 px-3 rounded-xl text-xs hover:bg-gray-50 transition-colors flex items-center justify-center gap-1.5 min-w-0">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="truncate">${ant.label}</span>
            </button>`);
        }
        if (podeCancelar) {
            secBtns.push(`
            <button onclick="cancelarPedido(${p.id})"
                    class="flex-1 text-red-500 border border-red-200 font-semibold py-2.5 px-3 rounded-xl text-xs hover:bg-red-50 transition-colors flex items-center justify-center gap-1.5 min-w-0">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="truncate">Cancelar pedido</span>
            </button>`);
        }

        if (secBtns.length) {
            html += `<div class="flex gap-2">${secBtns.join('')}</div>`;
        }

        footer.innerHTML = html;
    }

    const overlay = document.getElementById('modal-overlay');
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
}

function fecharModal() {
    modalAberto = null;
    const o = document.getElementById('modal-overlay');
    o.classList.add('hidden');
    o.classList.remove('flex');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });

// ══════════════════════════════════════════════════════════════
// Busca em tempo real
// ══════════════════════════════════════════════════════════════
function filtrarBusca(valor) {
    termoBusca = valor.toLowerCase().trim();
    renderTudo({ pedidos: dadosAtuais, total_dia: null });
}

// ══════════════════════════════════════════════════════════════
// Banner de novo pedido
// ══════════════════════════════════════════════════════════════
let bannerTimer = null;
function mostrarBanner(qtd) {
    // Banner injetado dinamicamente na topbar
    let el = document.getElementById('banner-kanban');
    if (!el) {
        el = document.createElement('div');
        el.id = 'banner-kanban';
        el.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-40 bg-yellow-400 text-yellow-900 font-bold px-6 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm';
        document.body.appendChild(el);
    }
    el.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span>${qtd} novo${qtd > 1 ? 's pedidos' : ' pedido'}!</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-yellow-700 font-bold text-sm">×</button>`;

    clearTimeout(bannerTimer);
    bannerTimer = setTimeout(() => el.remove(), 5000);
}

// ══════════════════════════════════════════════════════════════
// Som (Web Audio API, sem arquivo externo)
// ══════════════════════════════════════════════════════════════
function tocarSom() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [[880, 0], [1100, 0.2], [880, 0.4]].forEach(([freq, delay]) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime + delay);
            gain.gain.setValueAtTime(0.35, ctx.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.25);
            osc.start(ctx.currentTime + delay);
            osc.stop(ctx.currentTime + delay + 0.26);
        });
    } catch(e) {}
}

// ══════════════════════════════════════════════════════════════
// Helpers
// ══════════════════════════════════════════════════════════════
function fmtBRL(v) {
    return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}
function fmtTempo(minutos) {
    if (minutos < 60) return minutos + 'min';
    const h = Math.floor(minutos / 60);
    const m = minutos % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
}
function esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ══════════════════════════════════════════════════════════════
// Countdown + polling
// ══════════════════════════════════════════════════════════════
let secs = 30;
const cd = document.getElementById('countdown');
setInterval(() => {
    secs--;
    cd.textContent = secs;
    if (secs <= 0) { secs = 30; fetchKanban(); }
}, 1000);

// Carrega inicial
fetchKanban(true);
</script>
