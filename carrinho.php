<?php
session_start();
require_once __DIR__ . '/includes/db.php';
$pdo = getPDO();

// Ingredientes por produto + adicionais disponíveis
$ingRows = $pdo->query('SELECT produto_id, nome FROM produto_ingredientes ORDER BY id')->fetchAll();
$INGS_DB = [];
foreach ($ingRows as $r) { $INGS_DB[$r['produto_id']][] = $r['nome']; }

$ADDS_DB = $pdo->query('SELECT id, nome, preco FROM adicionais WHERE ativo=1 ORDER BY nome')->fetchAll();

require_once __DIR__ . '/includes/config.php';
$taxaCreditoPct = (float) getConfig('taxa_credito_pct', '0');
$taxaDebitoPct  = (float) getConfig('taxa_debito_pct',  '0');
$pedidoMinimo   = (float) getConfig('pedido_minimo', '0');
$tempoEspera    = (int)   getConfig('tempo_espera_atual', '0');

// Fidelidade
$fidelidadeAtivo = getConfig('fidelidade_ativo', '0') === '1';
$resgateMinimo   = (int) getConfig('fidelidade_resgate_minimo', '50');
$valorPonto      = (float) getConfig('fidelidade_valor_ponto', '0.10');

$pageTitle = 'Carrinho — Pizzaria Bella Napoli';
include __DIR__ . '/includes/header.php';
?>

<!-- ===== NAVBAR SIMPLIFICADA ===== -->
<nav class="bg-red-600 shadow-lg">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pizzaria/cardapio.php" class="flex items-center gap-2 text-white hover:text-red-200 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            <span class="font-semibold">Voltar ao cardápio</span>
        </a>
        <span class="text-white font-extrabold text-xl">Bella Napoli</span>
    </div>
</nav>

<main class="max-w-4xl mx-auto px-4 py-10 flex-1 w-full">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 flex items-center gap-3">
        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9M9 21a1 1 0 100-2 1 1 0 000 2zm6 0a1 1 0 100-2 1 1 0 000 2z"/>
        </svg>
        Meu Carrinho
    </h1>

    <?php if ($tempoEspera > 0): ?>
    <!-- ══ BANNER TEMPO DE ESPERA ══ -->
    <div class="bg-amber-50 border border-amber-200 rounded-2xl py-2.5 px-4 text-center text-sm font-semibold text-amber-700 mb-8">
        ⏱ Tempo estimado hoje: ~<?= $tempoEspera ?> min
    </div>
    <?php endif; ?>

    <div id="carrinho-vazio" class="hidden text-center py-20">
        <div class="text-gray-300 text-8xl mb-6">🛒</div>
        <p class="text-gray-500 text-xl mb-6">Seu carrinho está vazio.</p>
        <a href="/pizzaria/cardapio.php" class="bg-red-600 text-white px-8 py-3 rounded-full font-semibold hover:bg-orange-600 transition-colors">
            Ver cardápio
        </a>
    </div>

    <div id="carrinho-conteudo" class="hidden">
        <div class="grid lg:grid-cols-3 gap-8">

            <!-- Lista de itens -->
            <div class="lg:col-span-2 space-y-4" id="lista-itens"></div>

            <!-- Resumo + formulário -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h2 class="font-bold text-gray-700 text-lg mb-4">Resumo do pedido</h2>
                    <div id="resumo-itens" class="space-y-2 text-sm text-gray-600 mb-4"></div>
                    <div id="aviso-minimo" class="hidden bg-orange-50 border border-orange-200 text-orange-700 text-xs rounded-xl px-3 py-2 mt-2 flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span id="aviso-minimo-texto"></span>
                    </div>
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between items-center text-sm text-gray-500">
                            <span>Subtotal</span>
                            <span id="subtotal-display">R$ 0,00</span>
                        </div>
                        <div id="desconto-cupom-row" style="display:none" class="justify-between items-center text-sm text-green-600 font-semibold">
                            <span id="desconto-cupom-label">Cupom</span>
                            <span id="desconto-cupom-valor">−R$ 0,00</span>
                        </div>
                        <div id="desconto-fidelidade-row" style="display:none" class="justify-between items-center text-sm text-yellow-600 font-semibold">
                            <span>⭐ Fidelidade</span>
                            <span id="desconto-fidelidade-valor">−R$ 0,00</span>
                        </div>
                        <div id="taxa-cartao-row" style="display:none" class="justify-between items-center text-sm text-orange-600 font-semibold">
                            <span id="taxa-cartao-label">Taxa do cartão</span>
                            <span id="taxa-cartao-valor">+R$ 0,00</span>
                        </div>
                        <div class="flex justify-between items-center pt-1 border-t border-gray-100">
                            <span class="font-bold text-gray-800 text-lg">Total</span>
                            <span class="font-extrabold text-red-600 text-2xl" id="total-display">R$ 0,00</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h2 class="font-bold text-gray-700 text-lg mb-4">Seus dados</h2>
                    <form id="form-pedido" class="space-y-4">

                        <!-- Entrega vs Retirada -->
                        <div class="flex rounded-xl border border-gray-200 overflow-hidden text-sm font-semibold">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="tipo_entrega" id="tipo-entrega" value="entrega" class="sr-only peer" checked>
                                <div class="peer-checked:bg-red-600 peer-checked:text-white bg-white text-gray-500 py-2.5 text-center transition-colors flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                    </svg>
                                    Entrega
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="tipo_entrega" id="tipo-retirada" value="retirada" class="sr-only peer">
                                <div class="peer-checked:bg-red-600 peer-checked:text-white bg-white text-gray-500 py-2.5 text-center transition-colors flex items-center justify-center gap-2 border-l border-gray-200">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    Retirada na loja
                                </div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo *</label>
                            <input type="text" id="cliente_nome" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                                   placeholder="Seu nome">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefone / WhatsApp *</label>
                            <input type="tel" id="cliente_telefone" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                                   placeholder="(11) 99999-9999">
                            <div id="sugestao-endereco" class="hidden mt-2 bg-blue-50 border border-blue-200 rounded-xl px-3 py-2.5 text-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-blue-600 uppercase tracking-wide mb-0.5">Último endereço usado</p>
                                        <p class="text-gray-700 text-xs leading-relaxed" id="sugestao-texto"></p>
                                    </div>
                                    <button type="button" onclick="usarEnderecoSugerido()"
                                            class="flex-shrink-0 text-xs bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-1.5 rounded-lg transition-colors">
                                        Usar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Endereço detalhado -->
                        <div id="bloco-endereco" class="border border-gray-200 rounded-xl p-3 space-y-3 bg-gray-50">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Endereço de entrega</p>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rua / Avenida *</label>
                                <input type="text" id="end_rua" required data-req="1"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400"
                                       placeholder="Ex: Rua das Flores">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Número *</label>
                                    <input type="text" id="end_numero" required data-req="1"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400"
                                           placeholder="Ex: 123">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                    <input type="text" id="end_complemento"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400"
                                           placeholder="Apto, bloco…">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bairro *</label>
                                <select id="end_bairro" required data-req="1"
                                        onchange="atualizarTaxaEntrega()"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400">
                                    <option value="">Selecione o bairro...</option>
                                </select>
                                <div id="taxa-entrega-info" class="hidden mt-1.5 text-xs text-gray-500 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                    </svg>
                                    Taxa de entrega: <span id="taxa-entrega-valor" class="font-semibold text-green-600">grátis</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" id="end_cep"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400"
                                       placeholder="00000-000" maxlength="9">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações gerais</label>
                            <textarea id="observacao" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                                      placeholder="Ex: campainha no 2º andar…"></textarea>
                        </div>
                        <!-- Cupom -->
                        <div class="border border-dashed border-gray-300 rounded-xl p-3 bg-gray-50">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Cupom de desconto</p>
                            <div class="flex gap-2">
                                <input type="text" id="cupom-input" maxlength="30"
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400 uppercase font-mono tracking-widest"
                                       placeholder="CÓDIGO"
                                       oninput="this.value=this.value.toUpperCase()">
                                <button type="button" id="btn-cupom" onclick="aplicarCupom()"
                                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white font-semibold rounded-lg text-sm transition-colors flex-shrink-0">
                                    Aplicar
                                </button>
                            </div>
                            <div id="cupom-feedback" class="hidden mt-2 text-sm font-semibold rounded-lg px-3 py-2"></div>
                        </div>

                        <?php if ($fidelidadeAtivo): ?>
                        <!-- Fidelidade -->
                        <div id="bloco-fidelidade" class="border border-yellow-200 rounded-xl p-3 bg-yellow-50 hidden">
                            <p class="text-xs font-bold text-yellow-700 uppercase tracking-widest mb-2">⭐ Fidelidade</p>
                            <div id="fidelidade-info" class="mb-3 hidden">
                                <p class="text-sm text-gray-700">
                                    Você tem <strong id="fidelidade-pontos" class="text-yellow-700">0</strong> pontos
                                    <?php if ($valorPonto > 0): ?>
                                    = <strong id="fidelidade-valor-total" class="text-green-600">R$ 0,00</strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div id="fidelidade-resgatar" class="hidden">
                                <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-yellow-100 transition-colors">
                                    <input type="checkbox" id="chk-resgatar" onchange="toggleResgatar()"
                                           class="accent-yellow-600 w-4 h-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700">Resgatar pontos neste pedido</p>
                                        <p class="text-xs text-gray-500" id="fidelidade-desconto-label">—</p>
                                    </div>
                                </label>
                            </div>
                            <p id="fidelidade-zero-msg" class="text-xs text-gray-400 hidden">
                                Você ainda não tem pontos suficientes para resgatar (mínimo <?= $resgateMinimo ?> pts).
                            </p>
                            <input type="hidden" id="pontos_resgatados" name="pontos_resgatados" value="0">
                        </div>
                        <?php endif; ?>

                        <!-- Forma de pagamento -->
                        <div class="border border-gray-200 rounded-xl p-3 bg-gray-50">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Forma de pagamento</p>
                            <div class="space-y-1">
                                <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white transition-colors">
                                    <input type="radio" name="forma_pagamento" id="pag-dinheiro" value="dinheiro" class="accent-red-600" checked>
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="text-lg leading-none">💵</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700 leading-tight">Dinheiro</p>
                                            <p class="text-xs text-gray-400">Pague na entrega</p>
                                        </div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white transition-colors">
                                    <input type="radio" name="forma_pagamento" id="pag-credito" value="credito" class="accent-red-600">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="text-lg leading-none">💳</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700 leading-tight">Crédito na entrega<?php if($taxaCreditoPct > 0): ?> <span class="text-orange-500 font-bold">(+<?= number_format($taxaCreditoPct,2,',','.') ?>%)</span><?php endif; ?></p>
                                            <p class="text-xs text-gray-400">Máquina de cartão</p>
                                        </div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white transition-colors">
                                    <input type="radio" name="forma_pagamento" id="pag-debito" value="debito" class="accent-red-600">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="text-lg leading-none">💳</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700 leading-tight">Débito na entrega<?php if($taxaDebitoPct > 0): ?> <span class="text-orange-500 font-bold">(+<?= number_format($taxaDebitoPct,2,',','.') ?>%)</span><?php endif; ?></p>
                                            <p class="text-xs text-gray-400">Máquina de cartão</p>
                                        </div>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white transition-colors">
                                    <input type="radio" name="forma_pagamento" id="pag-pix" value="pix" class="accent-red-600">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="text-lg leading-none">📱</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700 leading-tight">PIX na entrega</p>
                                            <p class="text-xs text-gray-400">Via app do banco</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <!-- Troco (só para dinheiro) -->
                            <div id="bloco-troco" class="mt-3 pt-3 border-t border-gray-200">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Troco para quanto? <span class="text-gray-400 font-normal">(opcional)</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold">R$</span>
                                    <input type="number" id="troco_para" min="0" step="0.01"
                                           class="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                                           placeholder="0,00">
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="btn-pedido"
                                class="w-full bg-red-600 hover:bg-orange-600 text-white font-bold py-3 rounded-xl transition-colors text-base flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Revisar Pedido
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- ===== MODAL DE CONFIRMAÇÃO ===== -->
<div id="modal-confirmacao" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <!-- Backdrop -->
    <div id="modal-backdrop" class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="fecharModal()"></div>
    <!-- Caixa -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden">
        <!-- Cabeçalho -->
        <div class="bg-red-600 px-6 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-white font-extrabold text-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Confirmar Pedido
            </h3>
            <button onclick="fecharModal()" class="text-white/80 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <!-- Conteúdo scrollável -->
        <div class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
            <!-- Itens do pedido -->
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Itens</p>
                <div id="modal-itens" class="space-y-2"></div>
            </div>
            <!-- Divisor -->
            <div class="border-t border-dashed border-gray-200"></div>
            <!-- Subtotal / Cupom / Total -->
            <div class="space-y-2">
                <div class="flex justify-between items-center text-sm text-gray-500">
                    <span>Subtotal</span>
                    <span id="modal-subtotal">R$ 0,00</span>
                </div>
                <div id="modal-cupom-row" style="display:none" class="justify-between items-center text-sm text-green-600 font-semibold">
                    <span id="modal-cupom-label">Cupom</span>
                    <span id="modal-cupom-valor">−R$ 0,00</span>
                </div>
                <div id="modal-taxa-cartao-row" style="display:none" class="justify-between items-center text-sm text-orange-600 font-semibold">
                    <span id="modal-taxa-cartao-label">Taxa cartão</span>
                    <span id="modal-taxa-cartao-valor">+R$ 0,00</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-gray-100">
                    <span class="font-bold text-gray-700">Total</span>
                    <span class="font-extrabold text-red-600 text-xl" id="modal-total">R$ 0,00</span>
                </div>
            </div>
            <!-- Divisor -->
            <div class="border-t border-dashed border-gray-200"></div>
            <!-- Dados do cliente -->
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Seus dados</p>
                <div class="space-y-1 text-sm text-gray-700">
                    <p><span class="font-semibold">Nome:</span> <span id="modal-nome"></span></p>
                    <p><span class="font-semibold">Telefone:</span> <span id="modal-telefone"></span></p>
                    <p><span class="font-semibold">Endereço:</span> <span id="modal-endereco" class="text-gray-600"></span></p>
                    <p><span class="font-semibold">Pagamento:</span> <span id="modal-pagamento" class="text-gray-600"></span></p>
                    <p id="modal-troco-row" class="hidden"><span class="font-semibold">Troco p/:</span> <span id="modal-troco" class="text-gray-600"></span></p>
                    <p id="modal-obs-row" class="hidden"><span class="font-semibold">Obs:</span> <span id="modal-obs" class="text-gray-600"></span></p>
                </div>
            </div>
        </div>
        <!-- Rodapé com ações -->
        <div class="px-6 py-4 bg-gray-50 border-t flex gap-3 flex-shrink-0">
            <button onclick="fecharModal()"
                    class="flex-1 border-2 border-gray-300 text-gray-600 font-semibold py-2.5 rounded-xl hover:border-gray-400 hover:text-gray-700 transition-colors text-sm">
                Voltar e editar
            </button>
            <button id="btn-confirmar"
                    class="flex-1 bg-red-600 hover:bg-orange-600 text-white font-bold py-2.5 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                Confirmar e Pedir
            </button>
        </div>
    </div>
</div>

<div id="erro-toast"
     class="fixed bottom-6 right-6 bg-red-700 text-white px-5 py-3 rounded-xl shadow-xl opacity-0 pointer-events-none transition-all duration-300 z-50">
    <span id="erro-msg">Erro ao enviar pedido.</span>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// ── Dados do servidor ──────────────────────────────────────────
const INGS  = <?= json_encode($INGS_DB,  JSON_UNESCAPED_UNICODE) ?>;
const ADDS  = <?= json_encode($ADDS_DB,  JSON_UNESCAPED_UNICODE) ?>;
const TAXA_CREDITO_PCT   = <?= $taxaCreditoPct ?>;
const TAXA_DEBITO_PCT    = <?= $taxaDebitoPct ?>;
const PEDIDO_MINIMO      = <?= $pedidoMinimo ?>;
const FIDELIDADE_ATIVO   = <?= $fidelidadeAtivo ? 'true' : 'false' ?>;
const RESGATE_MINIMO     = <?= $resgateMinimo ?>;
const VALOR_PONTO        = <?= $valorPonto ?>;

// ── Estado ────────────────────────────────────────────────────
let carrinho         = JSON.parse(localStorage.getItem('pizzaria_carrinho') || '[]');
let cupomAplicado    = null; // { codigo, tipo, valor, desconto }
let taxaEntregaAtual = 0;

// Garante que todos os itens tenham os campos de customização
carrinho = carrinho.map(i => ({
    ...i,
    precoBase: i.precoBase ?? i.preco,
    ingredientesRemovidos: i.ingredientesRemovidos ?? [],
    adicionais: i.adicionais ?? [],
}));

// ── Helpers ───────────────────────────────────────────────────
function fmt(v) {
    return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function salvar() {
    localStorage.setItem('pizzaria_carrinho', JSON.stringify(carrinho));
}

function recalcularPreco(idx) {
    const item = carrinho[idx];
    const base = item.precoBase;
    const extras = (item.adicionais || []).reduce((s, a) => s + a.preco * (a.qtd || 1), 0);
    item.preco = base + extras;
}

// ── Ações de ingrediente ──────────────────────────────────────
function toggleIng(idx, nome) {
    const item = carrinho[idx];
    const rem = item.ingredientesRemovidos || [];
    const pos = rem.indexOf(nome);
    if (pos >= 0) rem.splice(pos, 1);  // re-adiciona
    else rem.push(nome);               // remove
    item.ingredientesRemovidos = rem;
    salvar();
    render();
}

// ── Ações de adicional ────────────────────────────────────────
function addExtra(idx, addId, addNome, addPreco) {
    const item = carrinho[idx];
    item.adicionais = item.adicionais || [];
    const ja = item.adicionais.find(a => a.id === addId);
    if (ja) { ja.qtd = (ja.qtd || 1) + 1; }
    else { item.adicionais.push({ id: addId, nome: addNome, preco: addPreco, qtd: 1 }); }
    recalcularPreco(idx);
    salvar();
    render();
}

function removerExtra(idx, addId) {
    const item = carrinho[idx];
    const ja = item.adicionais?.find(a => a.id === addId);
    if (!ja) return;
    ja.qtd = (ja.qtd || 1) - 1;
    if (ja.qtd <= 0) item.adicionais = item.adicionais.filter(a => a.id !== addId);
    recalcularPreco(idx);
    salvar();
    render();
}

// ── Quantidade do item ────────────────────────────────────────
function mudarQtd(idx, delta) {
    carrinho[idx].quantidade += delta;
    if (carrinho[idx].quantidade <= 0) carrinho.splice(idx, 1);
    cupomAplicado = null;
    renderCupomFeedback(null);
    salvar();
    render();
}

function remover(idx) {
    carrinho.splice(idx, 1);
    cupomAplicado = null;
    renderCupomFeedback(null);
    salvar();
    render();
}

function separarUnidades(idx) {
    const original = carrinho[idx];
    const qtd = original.quantidade;
    if (qtd < 2) return;
    const novas = [];
    for (let i = 0; i < qtd; i++) {
        const copia = JSON.parse(JSON.stringify(original));
        copia.quantidade = 1;
        novas.push(copia);
    }
    carrinho.splice(idx, 1, ...novas);
    cupomAplicado = null;
    renderCupomFeedback(null);
    salvar();
    render();
    mostrarErro(`${qtd} itens separados — personalize cada um como quiser.`, '#2563eb');
}

// ── Render principal ──────────────────────────────────────────
function render() {
    const vazio    = document.getElementById('carrinho-vazio');
    const conteudo = document.getElementById('carrinho-conteudo');

    if (carrinho.length === 0) {
        vazio.classList.remove('hidden');
        conteudo.classList.add('hidden');
        return;
    }
    vazio.classList.add('hidden');
    conteudo.classList.remove('hidden');

    document.getElementById('lista-itens').innerHTML = carrinho.map((item, idx) => renderItem(item, idx)).join('');

    // Resumo lateral
    document.getElementById('resumo-itens').innerHTML = carrinho.map(i => {
        const adds = (i.adicionais || []).map(a => (a.qtd > 1 ? a.qtd + 'x ' : '') + a.nome).join(', ');
        const sems = (i.ingredientesRemovidos || []).map(s => 'sem ' + s).join(', ');
        const obs  = [adds && '+ ' + adds, sems].filter(Boolean).join(' · ');
        return `<div class="text-sm">
            <div class="flex justify-between gap-2">
                <span class="text-gray-700">${i.quantidade}× ${esc(i.nome)}</span>
                <span class="font-semibold flex-shrink-0">R$ ${fmt(i.preco * i.quantidade)}</span>
            </div>
            ${obs ? `<p class="text-xs text-gray-400 mt-0.5">${esc(obs)}</p>` : ''}
        </div>`;
    }).join('');

    const subtotal  = carrinho.reduce((s, i) => s + i.preco * i.quantidade, 0);
    const desconto  = cupomAplicado ? cupomAplicado.desconto : 0;
    const descontoFid = (_pontosResgatados || 0) * VALOR_PONTO;
    const formaPag  = document.querySelector('input[name="forma_pagamento"]:checked')?.value ?? 'dinheiro';
    const taxaPct   = formaPag === 'credito' ? TAXA_CREDITO_PCT : formaPag === 'debito' ? TAXA_DEBITO_PCT : 0;
    const taxaCartao = taxaPct > 0 ? Math.round((subtotal - desconto - descontoFid) * taxaPct) / 100 : 0;
    const total     = Math.max(0, subtotal - desconto - descontoFid + taxaCartao);

    // Pedido mínimo
    const isEntrega = document.getElementById('tipo-entrega')?.checked;
    const avisoMin  = document.getElementById('aviso-minimo');
    if (PEDIDO_MINIMO > 0 && isEntrega && subtotal < PEDIDO_MINIMO) {
        const falta = PEDIDO_MINIMO - subtotal;
        document.getElementById('aviso-minimo-texto').textContent =
            `Mínimo para entrega: R$ ${fmt(PEDIDO_MINIMO)}. Faltam R$ ${fmt(falta)}.`;
        avisoMin?.classList.remove('hidden');
        document.getElementById('btn-pedido')?.setAttribute('disabled', 'true');
        document.getElementById('btn-pedido')?.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        avisoMin?.classList.add('hidden');
        document.getElementById('btn-pedido')?.removeAttribute('disabled');
        document.getElementById('btn-pedido')?.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    document.getElementById('subtotal-display').textContent = 'R$ ' + fmt(subtotal);
    document.getElementById('total-display').textContent    = 'R$ ' + fmt(total);

    const descontoRow   = document.getElementById('desconto-cupom-row');
    const descontoLabel = document.getElementById('desconto-cupom-label');
    const descontoValor = document.getElementById('desconto-cupom-valor');
    if (cupomAplicado) {
        descontoLabel.textContent = `Cupom ${cupomAplicado.codigo}`;
        descontoValor.textContent = `−R$ ${fmt(desconto)}`;
        descontoRow.style.display = 'flex';
    } else {
        descontoRow.style.display = 'none';
    }

    const taxaRow = document.getElementById('taxa-cartao-row');
    if (taxaCartao > 0) {
        document.getElementById('taxa-cartao-label').textContent = `Taxa ${formaPag === 'credito' ? 'crédito' : 'débito'} (${taxaPct}%)`;
        document.getElementById('taxa-cartao-valor').textContent = `+R$ ${fmt(taxaCartao)}`;
        taxaRow.style.display = 'flex';
    } else {
        taxaRow.style.display = 'none';
    }

    const fidRow = document.getElementById('desconto-fidelidade-row');
    if (fidRow) {
        if (descontoFid > 0) {
            document.getElementById('desconto-fidelidade-valor').textContent = `−R$ ${fmt(descontoFid)}`;
            fidRow.style.display = 'flex';
        } else {
            fidRow.style.display = 'none';
        }
    }
}

function renderItem(item, idx) {
    if (item.isCombo) return renderComboItem(item, idx);
    const ings = INGS[item.id] || [];
    const removidos = new Set(item.ingredientesRemovidos || []);
    const meuAdds = item.adicionais || [];

    // ── Seção de ingredientes (estilo iFood: − para remover, + para re-add) ──
    const ingsHtml = ings.length ? `
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Ingredientes</p>
            <div class="space-y-0.5">
                ${ings.map(ing => {
                    const removido = removidos.has(ing);
                    return `<div class="flex items-center justify-between py-1.5 px-1 rounded-lg hover:bg-gray-50 transition-colors">
                        <span class="text-sm flex-1 ${removido ? 'line-through text-gray-300' : 'text-gray-700'}">${esc(ing)}</span>
                        <button onclick="toggleIng(${idx}, '${esc(ing)}')"
                                class="w-7 h-7 rounded-full border-2 flex items-center justify-center text-base font-bold transition-all flex-shrink-0 ml-3
                                       ${removido
                                         ? 'border-green-400 text-green-500 hover:bg-green-50'
                                         : 'border-gray-300 text-gray-400 hover:border-red-400 hover:text-red-500'}">
                            ${removido ? '+' : '−'}
                        </button>
                    </div>`;
                }).join('')}
            </div>
        </div>` : '';

    // ── Seção de adicionais (+ qtd −) ──────────────────────────────────────
    const addsHtml = ADDS.length ? `
        <div class="mt-4 pt-4 border-t border-gray-100">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Adicionais</p>
            <div class="space-y-0.5">
                ${ADDS.map(add => {
                    const ja = meuAdds.find(a => a.id === add.id);
                    const qtd = ja ? (ja.qtd || 1) : 0;
                    const controles = qtd === 0
                        ? `<button onclick="addExtra(${idx}, ${add.id}, '${esc(add.nome)}', ${add.preco})"
                                   class="w-7 h-7 rounded-full border-2 border-green-400 text-green-500 hover:bg-green-50 flex items-center justify-center text-base font-bold transition-all flex-shrink-0">
                               +
                           </button>`
                        : `<div class="flex items-center gap-2 flex-shrink-0">
                               <button onclick="removerExtra(${idx}, ${add.id})"
                                       class="w-7 h-7 rounded-full border-2 border-red-300 text-red-400 hover:bg-red-50 flex items-center justify-center text-base font-bold transition-all">
                                   −
                               </button>
                               <span class="text-sm font-extrabold text-gray-800 w-4 text-center">${qtd}</span>
                               <button onclick="addExtra(${idx}, ${add.id}, '${esc(add.nome)}', ${add.preco})"
                                       class="w-7 h-7 rounded-full border-2 border-green-400 text-green-500 hover:bg-green-50 flex items-center justify-center text-base font-bold transition-all">
                                   +
                               </button>
                           </div>`;
                    return `<div class="flex items-center justify-between py-1.5 px-1 rounded-lg hover:bg-gray-50 transition-colors gap-3">
                        <div class="flex-1 min-w-0">
                            <span class="text-sm text-gray-700">${esc(add.nome)}</span>
                            <span class="text-xs text-green-600 font-bold ml-1.5">+R$ ${fmt(add.preco)}</span>
                        </div>
                        ${controles}
                    </div>`;
                }).join('')}
            </div>
        </div>` : '';

    return `
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <!-- Cabeçalho do item -->
        <div class="p-4 flex gap-4 items-start">
            <img src="${esc(item.imagem)}" alt="${esc(item.nome)}"
                 class="w-20 h-20 object-cover rounded-xl flex-shrink-0"
                 onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=200&q=60'">
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-gray-800 text-base">${esc(item.nome)}</h3>
                <p class="text-sm text-gray-400 mt-0.5">Base: R$ ${fmt(item.precoBase)}</p>
                ${(item.adicionais?.length || item.ingredientesRemovidos?.length)
                    ? `<p class="text-xs text-gray-500 mt-1">
                           ${item.ingredientesRemovidos?.length ? `<span class="text-red-400">✂️ sem ${item.ingredientesRemovidos.join(', ')}</span>` : ''}
                           ${item.adicionais?.length ? `<span class="text-green-600 ml-1">➕ ${item.adicionais.map(a=>(a.qtd>1?a.qtd+'x ':'')+a.nome).join(', ')}</span>` : ''}
                       </p>` : ''}
            </div>
            <div class="flex-shrink-0 text-right">
                <p class="font-extrabold text-red-600 text-lg">R$ ${fmt(item.preco * item.quantidade)}</p>
                <div class="flex items-center gap-2 mt-1 justify-end">
                    <button onclick="mudarQtd(${idx}, -1)"
                            class="w-7 h-7 rounded-full border-2 border-gray-300 flex items-center justify-center text-gray-500 hover:border-red-400 hover:text-red-500 font-bold transition-all">−</button>
                    <span class="font-bold text-gray-800 w-4 text-center">${item.quantidade}</span>
                    <button onclick="mudarQtd(${idx}, 1)"
                            class="w-7 h-7 rounded-full border-2 border-gray-300 flex items-center justify-center text-gray-500 hover:border-green-400 hover:text-green-600 font-bold transition-all">+</button>
                    <button onclick="remover(${idx})" class="ml-1 text-gray-300 hover:text-red-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Customizações expandidas -->
        <div class="px-4 pb-4">
            ${ingsHtml}
            ${addsHtml}
            ${item.quantidade >= 2 ? `
            <div class="mt-3 pt-3 border-t border-dashed border-gray-200 flex items-center justify-between gap-3">
                <p class="text-xs text-gray-400 leading-snug">Quer personalizar cada unidade diferente?</p>
                <button onclick="separarUnidades(${idx})"
                        class="text-xs font-semibold text-blue-600 hover:text-blue-700 whitespace-nowrap underline underline-offset-2 transition-colors flex-shrink-0">
                    Separar em itens individuais →
                </button>
            </div>` : ''}
        </div>
    </div>`;
}

function renderComboItem(item, idx) {
    return `
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="p-4 flex gap-4 items-start">
            <img src="${esc(item.imagem)}" alt="${esc(item.nome)}"
                 class="w-20 h-20 object-cover rounded-xl flex-shrink-0"
                 onerror="this.src='https://images.unsplash.com/photo-1513104890138-7c749659a591?w=200&q=60'">
            <div class="flex-1 min-w-0">
                <span class="text-[10px] font-extrabold bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full uppercase tracking-wide">Combo</span>
                <h3 class="font-bold text-gray-800 text-base mt-1">${esc(item.nome)}</h3>
            </div>
            <div class="flex-shrink-0 text-right">
                <p class="font-extrabold text-orange-600 text-lg">R$ ${fmt(item.preco * item.quantidade)}</p>
                <div class="flex items-center gap-2 mt-1 justify-end">
                    <button onclick="mudarQtd(${idx}, -1)" class="w-7 h-7 rounded-full border-2 border-gray-300 flex items-center justify-center text-gray-500 hover:border-red-400 hover:text-red-500 font-bold transition-all">−</button>
                    <span class="font-bold text-gray-800 w-4 text-center">${item.quantidade}</span>
                    <button onclick="mudarQtd(${idx}, 1)" class="w-7 h-7 rounded-full border-2 border-gray-300 flex items-center justify-center text-gray-500 hover:border-green-400 hover:text-green-600 font-bold transition-all">+</button>
                    <button onclick="remover(${idx})" class="ml-1 text-gray-300 hover:text-red-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>`;
}

// ── Cupom ─────────────────────────────────────────────────────
async function aplicarCupom() {
    const input = document.getElementById('cupom-input');
    const btn   = document.getElementById('btn-cupom');
    const codigo = input.value.trim().toUpperCase();
    if (!codigo) return;

    const subtotal = carrinho.reduce((s, i) => s + i.preco * i.quantidade, 0);

    btn.disabled = true;
    btn.textContent = '…';

    try {
        const res  = await fetch('/pizzaria/api/cupom.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo, total: subtotal })
        });
        const data = await res.json();

        if (data.valido) {
            cupomAplicado = { codigo: data.codigo, tipo: data.tipo, valor: data.valor, desconto: data.desconto };
            renderCupomFeedback({ ok: true, msg: data.mensagem });
            input.disabled = true;
            btn.textContent = '✓';
            btn.classList.replace('bg-gray-800','bg-green-600');
            btn.classList.replace('hover:bg-gray-700','hover:bg-green-700');
            btn.onclick = removerCupom;
        } else {
            cupomAplicado = null;
            renderCupomFeedback({ ok: false, msg: data.mensagem });
            btn.disabled = false;
            btn.textContent = 'Aplicar';
        }
    } catch {
        renderCupomFeedback({ ok: false, msg: 'Falha ao validar cupom.' });
        btn.disabled = false;
        btn.textContent = 'Aplicar';
    }
    render();
}

function removerCupom() {
    cupomAplicado = null;
    const input = document.getElementById('cupom-input');
    const btn   = document.getElementById('btn-cupom');
    input.value = '';
    input.disabled = false;
    btn.disabled = false;
    btn.textContent = 'Aplicar';
    btn.classList.replace('bg-green-600','bg-gray-800');
    btn.classList.replace('hover:bg-green-700','hover:bg-gray-700');
    btn.onclick = aplicarCupom;
    renderCupomFeedback(null);
    render();
}

function renderCupomFeedback(state) {
    const el = document.getElementById('cupom-feedback');
    if (!state) { el.classList.add('hidden'); return; }
    el.textContent = state.msg;
    el.className = `mt-2 text-sm font-semibold rounded-lg px-3 py-2 ${state.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'}`;
    el.classList.remove('hidden');
}

// ── Forma de pagamento / troco / taxa ────────────────────────
document.querySelectorAll('input[name="forma_pagamento"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.getElementById('bloco-troco').classList.toggle('hidden', radio.value !== 'dinheiro');
        render();
    });
});

// ── Entrega / Retirada ────────────────────────────────────────
document.querySelectorAll('input[name="tipo_entrega"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const isEntrega = document.getElementById('tipo-entrega').checked;
        const bloco = document.getElementById('bloco-endereco');
        bloco.classList.toggle('hidden', !isEntrega);
        bloco.querySelectorAll('[data-req]').forEach(el => { el.required = isEntrega; });
        if (!isEntrega) taxaEntregaAtual = 0;
        render();
    });
});

// ── Histórico de endereço ─────────────────────────────────────
let _enderecoSugerido = null;

document.getElementById('cliente_telefone').addEventListener('blur', async function() {
    const tel = this.value.replace(/\D/g,'');
    if (tel.length < 8) return;
    try {
        const res  = await fetch('/pizzaria/api/endereco-historico.php?telefone=' + encodeURIComponent(tel));
        const data = await res.json();
        const div  = document.getElementById('sugestao-endereco');
        if (data && data.endereco_completo) {
            _enderecoSugerido = data;
            document.getElementById('sugestao-texto').textContent = data.endereco_completo;
            div.classList.remove('hidden');
        } else {
            div.classList.add('hidden');
            _enderecoSugerido = null;
        }
    } catch(e) {}
});

function usarEnderecoSugerido() {
    if (!_enderecoSugerido) return;
    const end = _enderecoSugerido.endereco_completo;

    // Parse: "Rua X, 123 — Complemento — Bairro — Goiânia — CEP XXXXX"
    const partes = end.split(' — ');

    if (partes[0]) {
        const ruaNum = partes[0].split(', ');
        if (document.getElementById('end_rua'))    document.getElementById('end_rua').value    = ruaNum[0] || '';
        if (document.getElementById('end_numero')) document.getElementById('end_numero').value = ruaNum[1] || '';
    }

    // Find bairro (part before "Goiânia")
    let bairroIdx = partes.findIndex(p => p.includes('Goiânia'));
    if (bairroIdx > 1) {
        const partAntesGoiania = partes[bairroIdx - 1];
        if (!partAntesGoiania.startsWith('CEP')) {
            const sel = document.getElementById('end_bairro');
            if (sel) {
                Array.from(sel.options).forEach(opt => {
                    if (opt.value && partAntesGoiania.startsWith(opt.value)) {
                        sel.value = opt.value;
                        atualizarTaxaEntrega();
                    }
                });
            }
        }
    }

    // CEP
    const cepParte = partes.find(p => p.startsWith('CEP '));
    if (cepParte && document.getElementById('end_cep')) {
        document.getElementById('end_cep').value = cepParte.replace('CEP ', '');
    }

    // If we have stored bairro separately
    if (_enderecoSugerido.bairro) {
        const sel = document.getElementById('end_bairro');
        if (sel) {
            Array.from(sel.options).forEach(opt => {
                if (opt.value === _enderecoSugerido.bairro) {
                    sel.value = opt.value;
                    atualizarTaxaEntrega();
                }
            });
        }
    }

    document.getElementById('sugestao-endereco').classList.add('hidden');
}

// ── Máscara de CEP ────────────────────────────────────────────
document.getElementById('end_cep').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,8);
    if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5);
    this.value = v;
});

// ── Montar string de endereço ─────────────────────────────────
function montarEndereco() {
    if (document.getElementById('tipo-retirada')?.checked) return 'Retirada na loja';

    const rua         = document.getElementById('end_rua').value.trim();
    const numero      = document.getElementById('end_numero').value.trim();
    const complemento = document.getElementById('end_complemento').value.trim();
    const selBairro   = document.getElementById('end_bairro');
    const bairro      = selBairro ? selBairro.value.trim() : '';
    const cep         = document.getElementById('end_cep').value.trim();

    let partes = [rua + ', ' + numero];
    if (complemento) partes.push(complemento);
    partes.push(bairro + ' — Goiânia');
    if (cep) partes.push('CEP ' + cep);
    return partes.join(' — ');
}

// ── Abrir modal de confirmação ────────────────────────────────
document.getElementById('form-pedido').addEventListener('submit', (e) => {
    e.preventDefault();

    const nome      = document.getElementById('cliente_nome').value.trim();
    const telefone  = document.getElementById('cliente_telefone').value.trim();
    const endereco  = montarEndereco();
    const obs       = document.getElementById('observacao').value.trim();
    const subtotal  = carrinho.reduce((s, i) => s + i.preco * i.quantidade, 0);
    const desconto  = cupomAplicado ? cupomAplicado.desconto : 0;
    const formaPag  = document.querySelector('input[name="forma_pagamento"]:checked')?.value ?? 'dinheiro';
    const trocoPara = formaPag === 'dinheiro' ? parseFloat(document.getElementById('troco_para').value) || null : null;
    const PAG_LABEL = { dinheiro: '💵 Dinheiro', credito: '💳 Crédito na entrega', debito: '💳 Débito na entrega', pix: '📱 PIX na entrega' };
    const taxaPctModal = formaPag === 'credito' ? TAXA_CREDITO_PCT : formaPag === 'debito' ? TAXA_DEBITO_PCT : 0;
    const taxaCartaoModal = taxaPctModal > 0 ? Math.round((subtotal - desconto) * taxaPctModal) / 100 : 0;
    const total     = Math.max(0, subtotal - desconto + taxaCartaoModal);

    // Preenche modal — itens
    document.getElementById('modal-itens').innerHTML = carrinho.map(i => {
        const adds = (i.adicionais || []).map(a => (a.qtd > 1 ? a.qtd + 'x ' : '') + a.nome).join(', ');
        const sems = (i.ingredientesRemovidos || []).map(s => 'sem ' + s).join(', ');
        const obs2 = [adds && '+ ' + adds, sems].filter(Boolean).join(' · ');
        return `<div class="flex justify-between gap-2 text-sm">
            <div class="flex-1">
                <span class="font-semibold text-gray-800">${i.quantidade}× ${esc(i.nome)}</span>
                ${obs2 ? `<p class="text-xs text-gray-400">${esc(obs2)}</p>` : ''}
            </div>
            <span class="font-bold text-gray-700 flex-shrink-0">R$ ${fmt(i.preco * i.quantidade)}</span>
        </div>`;
    }).join('');

    // Preenche modal — totais e dados
    document.getElementById('modal-subtotal').textContent = 'R$ ' + fmt(subtotal);
    document.getElementById('modal-total').textContent    = 'R$ ' + fmt(total);

    const modalCupomRow = document.getElementById('modal-cupom-row');
    if (cupomAplicado) {
        document.getElementById('modal-cupom-label').textContent = `Cupom ${cupomAplicado.codigo}`;
        document.getElementById('modal-cupom-valor').textContent = `−R$ ${fmt(desconto)}`;
        modalCupomRow.style.display = 'flex';
    } else {
        modalCupomRow.style.display = 'none';
    }
    const modalTaxaCartaoRow = document.getElementById('modal-taxa-cartao-row');
    if (modalTaxaCartaoRow) {
        if (taxaCartaoModal > 0) {
            document.getElementById('modal-taxa-cartao-label').textContent = `Taxa ${formaPag === 'credito' ? 'crédito' : 'débito'} (${taxaPctModal}%)`;
            document.getElementById('modal-taxa-cartao-valor').textContent = `+R$ ${fmt(taxaCartaoModal)}`;
            modalTaxaCartaoRow.style.display = 'flex';
        } else {
            modalTaxaCartaoRow.style.display = 'none';
        }
    }
    document.getElementById('modal-nome').textContent     = nome;
    document.getElementById('modal-telefone').textContent = telefone;
    document.getElementById('modal-endereco').textContent = endereco;
    document.getElementById('modal-pagamento').textContent = PAG_LABEL[formaPag] ?? formaPag;

    const trocoRow = document.getElementById('modal-troco-row');
    if (trocoPara) {
        document.getElementById('modal-troco').textContent = 'R$ ' + fmt(trocoPara);
        trocoRow.classList.remove('hidden');
    } else {
        trocoRow.classList.add('hidden');
    }

    const obsRow = document.getElementById('modal-obs-row');
    if (obs) {
        document.getElementById('modal-obs').textContent = obs;
        obsRow.classList.remove('hidden');
    } else {
        obsRow.classList.add('hidden');
    }

    // Exibe modal
    const modal = document.getElementById('modal-confirmacao');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
});

function fecharModal() {
    const modal = document.getElementById('modal-confirmacao');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

// ── Envio após confirmação ────────────────────────────────────
document.getElementById('btn-confirmar').addEventListener('click', async () => {
    const btn = document.getElementById('btn-confirmar');
    btn.disabled = true;
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Enviando…`;

    const _tipoEntrega  = document.querySelector('input[name="tipo_entrega"]:checked')?.value ?? 'entrega';
    const _formaPag     = document.querySelector('input[name="forma_pagamento"]:checked')?.value ?? 'dinheiro';
    const _trocoPara    = _formaPag === 'dinheiro' ? parseFloat(document.getElementById('troco_para').value) || null : null;
    const _taxaPctModal = _formaPag === 'credito' ? TAXA_CREDITO_PCT : _formaPag === 'debito' ? TAXA_DEBITO_PCT : 0;
    const payload = {
        cliente_nome:     document.getElementById('cliente_nome').value.trim(),
        cliente_telefone: document.getElementById('cliente_telefone').value.trim(),
        endereco:         montarEndereco(),
        observacao:       document.getElementById('observacao').value.trim(),
        cupom_codigo:     cupomAplicado?.codigo ?? null,
        tipo_entrega:     _tipoEntrega,
        forma_pagamento:  _formaPag,
        troco_para:       _trocoPara,
        taxa_cartao_pct:  _taxaPctModal,
        taxa_entrega:     0,
        pontos_resgatados: FIDELIDADE_ATIVO ? (_pontosResgatados || 0) : 0,
        bairro:           _tipoEntrega === 'entrega' ? (document.getElementById('end_bairro')?.value ?? null) : null,
        itens: carrinho.map(i => ({
            produto_id:             i.produtoId ?? (i.isCombo ? null : i.id),
            combo_id:               i.isCombo ? i.comboId : null,
            quantidade:             i.quantidade,
            ingredientes_removidos: i.ingredientesRemovidos || [],
            adicionais:             (i.adicionais || []).flatMap(a => Array(a.qtd || 1).fill({ id: a.id })),
            tamanho_nome:           i.tamanhoNome ?? null,
        }))
    };

    try {
        const res  = await fetch('/pizzaria/api/pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.sucesso) {
            localStorage.removeItem('pizzaria_carrinho');
            window.location.href = '/pizzaria/acompanhar.php?id=' + data.pedido_id;
        } else {
            fecharModal();
            mostrarErro(data.mensagem || 'Erro ao enviar pedido.');
            btn.disabled = false;
            btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Confirmar e Pedir`;
        }
    } catch {
        fecharModal();
        mostrarErro('Falha de conexão. Tente novamente.');
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Confirmar e Pedir`;
    }
});

function mostrarErro(msg, cor = '') {
    const t = document.getElementById('erro-toast');
    document.getElementById('erro-msg').textContent = msg;
    t.style.backgroundColor = cor || '';
    t.classList.remove('opacity-0');
    t.classList.add('opacity-100');
    setTimeout(() => { t.classList.add('opacity-0'); t.classList.remove('opacity-100'); t.style.backgroundColor = ''; }, 4000);
}

// ── Bairros / Taxa de entrega ─────────────────────────────────
let bairrosData = [];
async function carregarBairros() {
    try {
        const res = await fetch('/pizzaria/api/bairros.php');
        bairrosData = await res.json();
        const sel = document.getElementById('end_bairro');
        if (!sel) return;
        bairrosData.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.nome;
            opt.dataset.taxa = b.taxa_entrega;
            opt.textContent = b.nome + (parseFloat(b.taxa_entrega) > 0 ? ' — R$ ' + fmt(b.taxa_entrega) : ' — grátis');
            sel.appendChild(opt);
        });
    } catch(e) {}
}
carregarBairros();

function atualizarTaxaEntrega() {
    const sel = document.getElementById('end_bairro');
    const opt = sel?.options[sel.selectedIndex];
    taxaEntregaAtual = opt?.dataset.taxa ? parseFloat(opt.dataset.taxa) : 0;
    const info = document.getElementById('taxa-entrega-info');
    const taxaVal = document.getElementById('taxa-entrega-valor');
    if (sel?.value) {
        info?.classList.remove('hidden');
        if (taxaVal) taxaVal.textContent = taxaEntregaAtual > 0 ? 'R$ ' + fmt(taxaEntregaAtual) : 'grátis';
    } else {
        info?.classList.add('hidden');
    }
    render();
}

render();

// ── Fidelidade ─────────────────────────────────────────────────
let _fidelidadePontos = 0;
let _pontosResgatados = 0;

if (FIDELIDADE_ATIVO) {
    document.getElementById('cliente_telefone').addEventListener('blur', async function() {
        const tel = this.value.replace(/\D/g, '');
        if (tel.length < 8) return;
        try {
            const res  = await fetch('/pizzaria/api/fidelidade.php?acao=consultar&telefone=' + encodeURIComponent(tel));
            const data = await res.json();
            const bloco = document.getElementById('bloco-fidelidade');
            if (!bloco) return;

            _fidelidadePontos = data.erro ? 0 : (parseInt(data.pontos) || 0);
            bloco.classList.remove('hidden');

            const infoDiv     = document.getElementById('fidelidade-info');
            const resgDiv     = document.getElementById('fidelidade-resgatar');
            const zeroMsg     = document.getElementById('fidelidade-zero-msg');
            const pontosEl    = document.getElementById('fidelidade-pontos');
            const valTotalEl  = document.getElementById('fidelidade-valor-total');
            const descLabel   = document.getElementById('fidelidade-desconto-label');

            if (_fidelidadePontos > 0) {
                infoDiv?.classList.remove('hidden');
                if (pontosEl) pontosEl.textContent = _fidelidadePontos.toLocaleString('pt-BR');
                if (valTotalEl) valTotalEl.textContent = 'R$ ' + fmt(_fidelidadePontos * VALOR_PONTO);
            } else {
                infoDiv?.classList.add('hidden');
            }

            if (_fidelidadePontos >= RESGATE_MINIMO) {
                resgDiv?.classList.remove('hidden');
                zeroMsg?.classList.add('hidden');
                const desc = fmt(_fidelidadePontos * VALOR_PONTO);
                if (descLabel) descLabel.textContent = `Desconto de R$ ${desc} (${_fidelidadePontos.toLocaleString('pt-BR')} pts)`;
            } else {
                resgDiv?.classList.add('hidden');
                zeroMsg?.classList.remove('hidden');
            }

            // Reset checkbox
            const chk = document.getElementById('chk-resgatar');
            if (chk) { chk.checked = false; }
            _pontosResgatados = 0;
            const hiddenPts = document.getElementById('pontos_resgatados');
            if (hiddenPts) hiddenPts.value = 0;
        } catch(e) {}
    });
}

function toggleResgatar() {
    const chk = document.getElementById('chk-resgatar');
    if (!chk) return;
    if (chk.checked) {
        _pontosResgatados = _fidelidadePontos;
    } else {
        _pontosResgatados = 0;
    }
    const hiddenPts = document.getElementById('pontos_resgatados');
    if (hiddenPts) hiddenPts.value = _pontosResgatados;
    render();
}
</script>
