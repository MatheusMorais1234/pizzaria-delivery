<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getPDO();
$ok  = '';
$erro = '';

// Ajuste manual de pontos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefone  = preg_replace('/\D/', '', trim($_POST['telefone'] ?? ''));
    $pontos    = (int) ($_POST['pontos']    ?? 0);
    $tipo      = in_array($_POST['tipo'] ?? '', ['credito', 'debito']) ? $_POST['tipo'] : '';
    $descricao = trim($_POST['descricao'] ?? '');

    if (strlen($telefone) < 8) {
        $erro = 'Telefone inválido.';
    } elseif ($pontos <= 0) {
        $erro = 'Quantidade de pontos deve ser maior que zero.';
    } elseif (!$tipo) {
        $erro = 'Selecione o tipo (crédito ou débito).';
    } else {
        // Busca ou cria registro
        $stmt = $pdo->prepare('SELECT id, pontos FROM fidelidade WHERE REGEXP_REPLACE(telefone,\'[^0-9]\',\'\') = ?');
        $stmt->execute([$telefone]);
        $reg = $stmt->fetch();

        if (!$reg) {
            $pdo->prepare('INSERT INTO fidelidade (telefone, pontos) VALUES (?,?)')
                ->execute([$telefone, 0]);
            $reg = ['id' => $pdo->lastInsertId(), 'pontos' => 0];
        }

        $novoSaldo = (int)$reg['pontos'];
        if ($tipo === 'credito') {
            $novoSaldo += $pontos;
        } else {
            if ($pontos > $novoSaldo) {
                $erro = 'Saldo insuficiente para débito (' . $novoSaldo . ' pontos disponíveis).';
            } else {
                $novoSaldo -= $pontos;
            }
        }

        if (!$erro) {
            $pdo->prepare('UPDATE fidelidade SET pontos=? WHERE id=?')->execute([$novoSaldo, $reg['id']]);
            $pdo->prepare('INSERT INTO fidelidade_log (telefone, pontos, tipo, descricao) VALUES (?,?,?,?)')
                ->execute([$telefone, $pontos, $tipo, $descricao ?: 'Ajuste manual']);
            $ok = 'Pontos ' . ($tipo === 'credito' ? 'creditados' : 'debitados') . ' com sucesso! Novo saldo: ' . $novoSaldo . ' pts.';
        }
    }
}

// Busca / paginação
$busca    = trim($_GET['busca'] ?? '');
$porPag   = 20;
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$offset   = ($pagina - 1) * $porPag;

$where = '';
$params = [];
if ($busca) {
    $where = "WHERE telefone LIKE ? OR nome LIKE ?";
    $params = ["%$busca%", "%$busca%"];
}

$total    = (int) $pdo->prepare("SELECT COUNT(*) FROM fidelidade $where")->execute($params) ? 0 : 0;
$cntStmt  = $pdo->prepare("SELECT COUNT(*) FROM fidelidade $where");
$cntStmt->execute($params);
$total    = (int) $cntStmt->fetchColumn();

$totalPags = max(1, (int) ceil($total / $porPag));
if ($pagina > $totalPags) { $pagina = $totalPags; $offset = ($pagina - 1) * $porPag; }

$stmtList = $pdo->prepare("SELECT * FROM fidelidade $where ORDER BY pontos DESC, updated_at DESC LIMIT $porPag OFFSET $offset");
$stmtList->execute($params);
$clientes = $stmtList->fetchAll();

$pageTitle = 'Programa de Fidelidade — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Programa de Fidelidade</h1>
        <p class="text-gray-400 text-sm mt-0.5">Gerencie pontos dos clientes e visualize o histórico</p>
    </div>
</div>

<?php if ($ok): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    <?= htmlspecialchars($ok) ?>
</div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Ajuste manual -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
            <h2 class="font-extrabold text-gray-900 mb-1">Ajuste manual de pontos</h2>
            <p class="text-xs text-gray-400 mb-5">Adicione ou remova pontos de um cliente pelo telefone</p>

            <form method="POST" action="/pizzaria/admin/fidelidade.php" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Telefone do cliente</label>
                    <input type="tel" name="telefone" required
                           placeholder="(11) 99999-9999"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipo</label>
                    <div class="flex rounded-xl border border-gray-200 overflow-hidden text-sm font-semibold">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="tipo" value="credito" class="sr-only peer" checked>
                            <div class="peer-checked:bg-green-600 peer-checked:text-white bg-white text-gray-500 py-2.5 text-center transition-colors">
                                + Crédito
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="tipo" value="debito" class="sr-only peer">
                            <div class="peer-checked:bg-red-600 peer-checked:text-white bg-white text-gray-500 py-2.5 text-center transition-colors border-l border-gray-200">
                                − Débito
                            </div>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Quantidade de pontos</label>
                    <input type="number" name="pontos" min="1" required
                           placeholder="Ex: 50"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Descrição <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <input type="text" name="descricao" maxlength="100"
                           placeholder="Ex: Bônus de aniversário"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <button type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-colors">
                    Aplicar ajuste
                </button>
            </form>
        </div>
    </div>

    <!-- Lista de clientes -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Busca -->
        <form method="GET" action="/pizzaria/admin/fidelidade.php" class="flex gap-2">
            <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                   placeholder="Buscar por telefone ou nome…"
                   class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-400">
            <button type="submit"
                    class="px-5 py-2.5 bg-gray-800 hover:bg-gray-700 text-white font-semibold rounded-xl text-sm transition-colors">
                Buscar
            </button>
            <?php if ($busca): ?>
            <a href="/pizzaria/admin/fidelidade.php"
               class="px-4 py-2.5 border border-gray-200 text-gray-600 font-semibold rounded-xl text-sm hover:bg-gray-50 transition-colors">
                Limpar
            </a>
            <?php endif; ?>
        </form>

        <!-- Tabela -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="font-extrabold text-gray-900">Clientes cadastrados</h2>
                    <p class="text-xs text-gray-400 mt-0.5"><?= $total ?> cliente<?= $total !== 1 ? 's' : '' ?> · pág. <?= $pagina ?>/<?= $totalPags ?></p>
                </div>
            </div>

            <?php if (empty($clientes)): ?>
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-3">⭐</div>
                <p class="font-medium">Nenhum cliente encontrado.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Telefone</th>
                            <th class="text-left px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Nome</th>
                            <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Pontos</th>
                            <th class="text-right px-6 py-3 font-semibold text-gray-500 text-xs uppercase tracking-widest">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($clientes as $c): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-mono text-gray-700"><?= htmlspecialchars($c['telefone']) ?></td>
                            <td class="px-6 py-4 text-gray-700"><?= $c['nome'] ? htmlspecialchars($c['nome']) : '<span class="text-gray-300 italic">—</span>' ?></td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold
                                             <?= (int)$c['pontos'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-400' ?>">
                                    ⭐ <?= number_format((int)$c['pontos']) ?> pts
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-400 text-xs">
                                <?= date('d/m/Y H:i', strtotime($c['updated_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($totalPags > 1): ?>
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-400">
                    Mostrando <?= $offset + 1 ?>–<?= min($offset + $porPag, $total) ?> de <?= $total ?>
                </p>
                <div class="flex gap-2">
                    <?php if ($pagina > 1): ?>
                    <a href="?pagina=<?= $pagina - 1 ?>&busca=<?= urlencode($busca) ?>"
                       class="px-4 py-2 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                        ← Anterior
                    </a>
                    <?php endif; ?>
                    <?php if ($pagina < $totalPags): ?>
                    <a href="?pagina=<?= $pagina + 1 ?>&busca=<?= urlencode($busca) ?>"
                       class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-semibold transition-colors">
                        Próxima →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
