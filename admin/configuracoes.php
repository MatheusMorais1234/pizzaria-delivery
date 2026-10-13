<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo  = getPDO();
$ok   = false;
$erro = '';

// Nomes dos dias (0=Dom … 6=Sáb)
$DIAS_NOMES = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$DIAS_ABREV = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

// Carrega horários salvos ou usa padrão
$horariosSalvos = getConfig('horarios_semana', '');
if ($horariosSalvos) {
    $horarios = json_decode($horariosSalvos, true);
} else {
    // Padrão: Seg-Sáb aberto 18:00-23:00, Dom fechado
    $horarios = [];
    for ($d = 0; $d <= 6; $d++) {
        $horarios[$d] = [
            'ativo' => ($d >= 1) ? 1 : 0,
            'abre'  => '18:00',
            'fecha' => '23:00',
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'horarios';

    if ($acao === 'horarios') {
        $novosHorarios = [];
        $valido = true;
        for ($d = 0; $d <= 6; $d++) {
            $h     = $_POST['horario'][$d] ?? [];
            $abre  = trim($h['abre']  ?? '00:00');
            $fecha = trim($h['fecha'] ?? '00:00');
            $ativo = isset($h['ativo']) ? 1 : 0;

            if (!preg_match('/^\d{2}:\d{2}$/', $abre) || !preg_match('/^\d{2}:\d{2}$/', $fecha)) {
                $erro = 'Formato de horário inválido em ' . $DIAS_NOMES[$d] . '. Use HH:MM.';
                $valido = false;
                break;
            }
            $novosHorarios[$d] = ['ativo' => $ativo, 'abre' => $abre, 'fecha' => $fecha];
        }

        if ($valido) {
            $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
            $stmt->execute(['horarios_semana', json_encode($novosHorarios)]);
            $horarios = $novosHorarios;
            $ok = true;
        }
    }

    if ($acao === 'geral') {
        $tempo = (int) ($_POST['tempo_entrega_min'] ?? 40);
        $aberta = isset($_POST['pizzaria_aberta']) ? '1' : '0';
        $pedidoMinimo = round(max(0, (float)str_replace(',', '.', $_POST['pedido_minimo'] ?? '0')), 2);

        $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
        $stmt->execute(['tempo_entrega_min', $tempo]);
        $stmt->execute(['pizzaria_aberta', $aberta]);
        $stmt->execute(['pedido_minimo', $pedidoMinimo]);

        // Fidelidade
        $fidAtivo          = isset($_POST['fidelidade_ativo']) ? '1' : '0';
        $fidReaisPorPonto  = max(1, (float)str_replace(',', '.', $_POST['fidelidade_reais_por_ponto'] ?? '10'));
        $fidResgateMinimo  = max(1, (int)($_POST['fidelidade_resgate_minimo'] ?? 50));
        $fidValorPonto     = max(0.01, (float)str_replace(',', '.', $_POST['fidelidade_valor_ponto'] ?? '0.10'));
        $stmt->execute(['fidelidade_ativo',          $fidAtivo]);
        $stmt->execute(['fidelidade_reais_por_ponto', number_format($fidReaisPorPonto, 2, '.', '')]);
        $stmt->execute(['fidelidade_resgate_minimo',  $fidResgateMinimo]);
        $stmt->execute(['fidelidade_valor_ponto',     number_format($fidValorPonto, 4, '.', '')]);
        $ok = true;
    }

    if ($acao === 'contato') {
        $campos = ['telefone','whatsapp','endereco','instagram','facebook','maps_embed','imagem_pizzaiolo'];
        $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
        foreach ($campos as $c) {
            $stmt->execute([$c, trim($_POST[$c] ?? '')]);
        }
        $ok = true;
    }

    if ($acao === 'pagamentos') {
        $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
        $credito = max(0, min(30, (float)str_replace(',', '.', $_POST['taxa_credito_pct'] ?? '0')));
        $debito  = max(0, min(30, (float)str_replace(',', '.', $_POST['taxa_debito_pct']  ?? '0')));
        $stmt->execute(['taxa_credito_pct', number_format($credito, 2, '.', '')]);
        $stmt->execute(['taxa_debito_pct',  number_format($debito,  2, '.', '')]);
        $ok = true;
    }

    if ($acao === 'landing') {
        $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
        $stmt->execute(['aniversariante_beneficio', trim($_POST['aniversariante_beneficio'] ?? '')]);
        $stmt->execute(['sobre_nos_texto',          trim($_POST['sobre_nos_texto']         ?? '')]);
        $stmt->execute(['sobre_nos_anos',           trim($_POST['sobre_nos_anos']          ?? '')]);
        $stmt->execute(['sobre_nos_sabores',        trim($_POST['sobre_nos_sabores']       ?? '')]);
        $ok = true;
    }
}

$pageTitle = 'Configurações — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-extrabold text-gray-900">Configurações</h1>
    <p class="text-gray-400 text-sm mt-0.5">Horário, contato, landing page e parâmetros de entrega</p>
</div>

<?php if ($ok): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Configurações salvas com sucesso!
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="max-w-2xl space-y-6">

    <!-- ── Horários por dia da semana ───────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-extrabold text-gray-900">Horários por dia da semana</h2>
            <p class="text-xs text-gray-400 mt-0.5">Defina o horário de cada dia individualmente. Dias desligados ficam fechados.</p>
        </div>

        <form method="POST" action="<?= BASE ?>/admin/configuracoes.php">
            <input type="hidden" name="acao" value="horarios">

            <div class="divide-y divide-gray-50">
                <?php for ($d = 0; $d <= 6; $d++):
                    $h     = $horarios[$d] ?? ['ativo' => 0, 'abre' => '18:00', 'fecha' => '23:00'];
                    $ativo = !empty($h['ativo']);
                    $isHoje = (int)date('w') === $d;
                ?>
                <div class="px-6 py-4 flex flex-wrap items-center gap-4" id="row-dia-<?= $d ?>">

                    <!-- Nome do dia -->
                    <div class="w-24 flex-shrink-0">
                        <span class="font-semibold text-gray-800 text-sm <?= $isHoje ? 'text-red-600' : '' ?>">
                            <?= $DIAS_NOMES[$d] ?>
                        </span>
                        <?php if ($isHoje): ?>
                        <span class="block text-xs text-red-500 font-bold">hoje</span>
                        <?php endif; ?>
                    </div>

                    <!-- Toggle ativo/fechado -->
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" title="<?= $ativo ? 'Clique para fechar' : 'Clique para abrir' ?>">
                        <input type="checkbox" name="horario[<?= $d ?>][ativo]" value="1"
                               class="sr-only peer dia-toggle"
                               data-dia="<?= $d ?>"
                               <?= $ativo ? 'checked' : '' ?>
                               onchange="toggleDia(<?= $d ?>, this.checked)">
                        <div class="w-10 h-5 bg-gray-200 peer-checked:bg-red-500 rounded-full transition-colors
                                    after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                        <span class="ml-2 text-xs font-semibold <?= $ativo ? 'text-gray-700' : 'text-gray-400' ?>" id="label-dia-<?= $d ?>">
                            <?= $ativo ? 'Aberto' : 'Fechado' ?>
                        </span>
                    </label>

                    <!-- Horários -->
                    <div class="flex items-center gap-2 flex-1" id="horarios-dia-<?= $d ?>" <?= !$ativo ? 'style="opacity:0.35;pointer-events:none"' : '' ?>>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-500 font-medium whitespace-nowrap">Abre às</label>
                            <input type="time" name="horario[<?= $d ?>][abre]"
                                   value="<?= htmlspecialchars($h['abre']) ?>"
                                   class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 w-28">
                        </div>
                        <span class="text-gray-300 font-bold">—</span>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-500 font-medium whitespace-nowrap">Fecha às</label>
                            <input type="time" name="horario[<?= $d ?>][fecha]"
                                   value="<?= htmlspecialchars($h['fecha']) ?>"
                                   class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 w-28">
                        </div>
                    </div>

                    <!-- Status chip -->
                    <?php if ($isHoje && $ativo): ?>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700 flex-shrink-0">
                        <?= $h['abre'] ?> – <?= $h['fecha'] ?>
                    </span>
                    <?php elseif ($isHoje): ?>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-red-100 text-red-600 flex-shrink-0">Fechado hoje</span>
                    <?php endif; ?>

                </div>
                <?php endfor; ?>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <button type="submit"
                        class="bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-2.5 rounded-xl transition-colors text-sm">
                    Salvar horários
                </button>
            </div>
        </form>
    </div>

    <!-- ── Configurações gerais ──────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-extrabold text-gray-900 mb-1">Configurações gerais</h2>
        <p class="text-xs text-gray-400 mb-5">Override manual e tempo de entrega</p>

        <form method="POST" action="<?= BASE ?>/admin/configuracoes.php" class="space-y-5">
            <input type="hidden" name="acao" value="geral">

            <!-- Override: fechar a loja manualmente -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
                <div>
                    <p class="font-semibold text-gray-800">Loja aberta agora (override)</p>
                    <p class="text-xs text-gray-400 mt-0.5">Quando desligado, fecha a loja independente do horário configurado</p>
                </div>
                <label class="relative cursor-pointer flex-shrink-0">
                    <input type="checkbox" name="pizzaria_aberta" class="sr-only peer"
                           <?= getConfig('pizzaria_aberta','1') === '1' ? 'checked' : '' ?>>
                    <div class="w-11 h-6 bg-gray-300 peer-checked:bg-red-600 rounded-full transition-colors duration-200 relative
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <!-- Tempo estimado de entrega -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tempo estimado de entrega</label>
                <div class="flex items-center gap-3">
                    <input type="range" name="tempo_entrega_min" min="15" max="90" step="5"
                           value="<?= (int) getConfig('tempo_entrega_min','40') ?>"
                           oninput="document.getElementById('tempo-val').textContent=this.value"
                           class="flex-1 accent-red-600">
                    <span class="bg-red-100 text-red-700 font-bold text-sm px-3 py-1 rounded-lg w-20 text-center">
                        <span id="tempo-val"><?= (int) getConfig('tempo_entrega_min','40') ?></span> min
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Exibido como countdown na página de acompanhamento do cliente</p>
            </div>

            <!-- Pedido mínimo -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Pedido mínimo para entrega</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold pointer-events-none">R$</span>
                    <input type="number" name="pedido_minimo" min="0" step="0.01"
                           value="<?= number_format((float)getConfig('pedido_minimo','0'), 2, '.', '') ?>"
                           class="w-full border border-gray-200 rounded-xl pl-10 pr-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <p class="text-xs text-gray-400 mt-1">0 = sem mínimo. Pedidos de entrega abaixo deste valor são bloqueados.</p>
            </div>

            <!-- ── Programa de Fidelidade ──────────────────────────── -->
            <div class="border-t border-gray-100 pt-5">
                <h3 class="font-bold text-gray-800 mb-1">Programa de Fidelidade</h3>
                <p class="text-xs text-gray-400 mb-4">Configure a acumulação e resgate de pontos pelos clientes</p>

                <!-- Ativar/desativar -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100 mb-4">
                    <div>
                        <p class="font-semibold text-gray-800">Ativar programa de fidelidade</p>
                        <p class="text-xs text-gray-400 mt-0.5">Exibe a seção de pontos no checkout do cliente</p>
                    </div>
                    <label class="relative cursor-pointer flex-shrink-0">
                        <input type="checkbox" name="fidelidade_ativo" class="sr-only peer"
                               <?= getConfig('fidelidade_ativo','0') === '1' ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-gray-300 peer-checked:bg-red-600 rounded-full transition-colors duration-200 relative
                                    after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
                    </label>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">R$ gastos por ponto</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold pointer-events-none">R$</span>
                            <input type="number" name="fidelidade_reais_por_ponto" min="1" step="0.01"
                                   value="<?= number_format((float)getConfig('fidelidade_reais_por_ponto','10'), 2, '.', '') ?>"
                                   class="w-full border border-gray-200 rounded-xl pl-10 pr-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">A cada R$X gastos o cliente ganha 1 ponto</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mínimo para resgatar</label>
                        <input type="number" name="fidelidade_resgate_minimo" min="1" step="1"
                               value="<?= (int)getConfig('fidelidade_resgate_minimo','50') ?>"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                        <p class="text-xs text-gray-400 mt-1">Pontos mínimos para usar no checkout</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Valor de cada ponto</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold pointer-events-none">R$</span>
                            <input type="number" name="fidelidade_valor_ponto" min="0.01" step="0.01"
                                   value="<?= number_format((float)getConfig('fidelidade_valor_ponto','0.10'), 2, '.', '') ?>"
                                   class="w-full border border-gray-200 rounded-xl pl-10 pr-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Ex: R$0,10 → 100 pts = R$10 de desconto</p>
                    </div>
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors">
                Salvar configurações gerais
            </button>
        </form>
    </div>

    <!-- ── Taxas de pagamento ────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-extrabold text-gray-900 mb-1">Taxas do cartão</h2>
        <p class="text-xs text-gray-400 mb-5">Percentual cobrado pela maquininha repassado ao cliente no checkout. Deixe 0 para não cobrar taxa.</p>

        <form method="POST" action="<?= BASE ?>/admin/configuracoes.php" class="space-y-4">
            <input type="hidden" name="acao" value="pagamentos">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Cartão de Crédito (%)</label>
                    <div class="relative">
                        <input type="number" name="taxa_credito_pct" min="0" max="30" step="0.01"
                               value="<?= number_format((float)getConfig('taxa_credito_pct','0'), 2, '.', '') ?>"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-red-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">%</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Ex: 3.50 para 3,5%</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Cartão de Débito (%)</label>
                    <div class="relative">
                        <input type="number" name="taxa_debito_pct" min="0" max="30" step="0.01"
                               value="<?= number_format((float)getConfig('taxa_debito_pct','0'), 2, '.', '') ?>"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-gray-50
                                      focus:outline-none focus:ring-2 focus:ring-red-400">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">%</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Ex: 1.50 para 1,5%</p>
                </div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-3 text-xs text-yellow-700">
                <strong>Como funciona:</strong> quando o cliente escolher crédito ou débito no checkout, a taxa é somada ao total automaticamente e exibida de forma transparente.
            </div>

            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors">
                Salvar taxas do cartão
            </button>
        </form>
    </div>

    <!-- ── Informações de contato ──────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-extrabold text-gray-900 mb-1">Informações de contato</h2>
        <p class="text-xs text-gray-400 mb-5">Exibidas na landing page e no footer</p>

        <form method="POST" action="<?= BASE ?>/admin/configuracoes.php" class="space-y-4">
            <input type="hidden" name="acao" value="contato">

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Telefone</label>
                    <input type="text" name="telefone"
                           value="<?= htmlspecialchars(getConfig('telefone','')) ?>"
                           placeholder="(11) 3456-7890"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">WhatsApp <span class="text-gray-400 font-normal">(número com DDI, sem +)</span></label>
                    <input type="text" name="whatsapp"
                           value="<?= htmlspecialchars(getConfig('whatsapp','')) ?>"
                           placeholder="DDI + DDD + número, só dígitos"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                    <p class="text-xs text-gray-400 mt-1">Ex: 5511987654321 (55 + DDD + número)</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Endereço completo</label>
                <input type="text" name="endereco"
                       value="<?= htmlspecialchars(getConfig('endereco','')) ?>"
                       placeholder="Rua das Pizzas, 123 — Centro, São Paulo/SP"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Instagram <span class="text-gray-400 font-normal">(só o @)</span></label>
                    <input type="text" name="instagram"
                           value="<?= htmlspecialchars(getConfig('instagram','')) ?>"
                           placeholder="bellanapoli.pizza"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Facebook <span class="text-gray-400 font-normal">(slug da página)</span></label>
                    <input type="text" name="facebook"
                           value="<?= htmlspecialchars(getConfig('facebook','')) ?>"
                           placeholder="pizzariabellanapoli"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">URL embed do Google Maps</label>
                <input type="text" name="maps_embed"
                       value="<?= htmlspecialchars(getConfig('maps_embed','')) ?>"
                       placeholder="https://www.google.com/maps/embed?pb=..."
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                <p class="text-xs text-gray-400 mt-1">No Google Maps: Compartilhar → Incorporar mapa → copie o src do iframe</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">URL da foto do pizzaiolo <span class="text-gray-400 font-normal">(seção Sobre nós)</span></label>
                <input type="text" name="imagem_pizzaiolo"
                       value="<?= htmlspecialchars(getConfig('imagem_pizzaiolo','')) ?>"
                       placeholder="https://... ou <?= BASE ?>/uploads/..."
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>

            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors">
                Salvar informações de contato
            </button>
        </form>
    </div>

    <!-- ── Landing page ──────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-extrabold text-gray-900 mb-1">Textos da landing page</h2>
        <p class="text-xs text-gray-400 mb-5">Benefício para aniversariantes e dados do "Sobre nós"</p>

        <form method="POST" action="<?= BASE ?>/admin/configuracoes.php" class="space-y-4">
            <input type="hidden" name="acao" value="landing">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Benefício para aniversariantes</label>
                <textarea name="aniversariante_beneficio" rows="3"
                          placeholder="Ex: Sobremesa grátis no dia do aniversário. Apresente um documento com foto no balcão."
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"><?= htmlspecialchars(getConfig('aniversariante_beneficio','')) ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Aparece na seção de aniversariantes da landing page</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Texto "Sobre nós"</label>
                <textarea name="sobre_nos_texto" rows="4"
                          placeholder="Conte a história da pizzaria..."
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"><?= htmlspecialchars(getConfig('sobre_nos_texto','')) ?></textarea>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Anos de história</label>
                    <input type="text" name="sobre_nos_anos"
                           value="<?= htmlspecialchars(getConfig('sobre_nos_anos','15+')) ?>"
                           placeholder="15+"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Sabores no cardápio</label>
                    <input type="text" name="sobre_nos_sabores"
                           value="<?= htmlspecialchars(getConfig('sobre_nos_sabores','30+')) ?>"
                           placeholder="30+"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition-colors">
                Salvar textos da landing page
            </button>
        </form>
    </div>

    <!-- ── Status atual ──────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm font-semibold text-gray-700 mb-3">Status atual</p>
        <div class="flex items-center gap-3 mb-2">
            <div class="w-3 h-3 rounded-full flex-shrink-0 <?= estaAberto() ? 'bg-green-500 animate-pulse' : 'bg-red-500' ?>"></div>
            <span class="font-medium text-gray-800">
                <?= estaAberto() ? '✅ Loja aberta — aceitando pedidos' : '🔴 Loja fechada — pedidos bloqueados' ?>
            </span>
        </div>
        <?php
            $diaAtual  = (int)date('w');
            $hAtual    = $horarios[$diaAtual] ?? null;
        ?>
        <?php if ($hAtual && $hAtual['ativo']): ?>
        <p class="text-xs text-gray-400">
            Hoje (<?= $DIAS_NOMES[$diaAtual] ?>): <?= $hAtual['abre'] ?> às <?= $hAtual['fecha'] ?>
        </p>
        <?php else: ?>
        <p class="text-xs text-gray-400">Hoje (<?= $DIAS_NOMES[$diaAtual] ?>) está marcado como fechado.</p>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-1">
            Hora do servidor: <strong class="text-gray-600"><?= date('H:i:s') ?></strong>
            <span class="text-gray-300 ml-1">(<?= date_default_timezone_get() ?>)</span>
        </p>
    </div>

</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script>
function toggleDia(dia, ativo) {
    const row   = document.getElementById('horarios-dia-' + dia);
    const label = document.getElementById('label-dia-' + dia);
    row.style.opacity        = ativo ? '' : '0.35';
    row.style.pointerEvents  = ativo ? '' : 'none';
    label.textContent        = ativo ? 'Aberto' : 'Fechado';
    label.className          = 'ml-2 text-xs font-semibold ' + (ativo ? 'text-gray-700' : 'text-gray-400');
}
</script>
