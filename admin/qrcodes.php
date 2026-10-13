<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = getPDO();
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numMesas = max(1, min(100, (int)($_POST['num_mesas'] ?? 10)));
    $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
    $stmt->execute(['num_mesas', $numMesas]);
    $ok = true;
}

$numMesas = max(1, (int) getConfig('num_mesas', '10'));

// URL base
$host    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = $host . '/pizzaria/cardapio-mesa.php';

$pageTitle = 'QR Codes das Mesas — Bella Napoli Admin';
include __DIR__ . '/includes/layout_top.php';
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">QR Codes das Mesas</h1>
        <p class="text-gray-400 text-sm mt-0.5">Gere e imprima QR Codes para cada mesa do salão</p>
    </div>
    <button onclick="imprimirTodos()"
            class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Imprimir todos
    </button>
</div>

<?php if ($ok): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
    </svg>
    Configuração salva! <?= $numMesas ?> mesas configuradas.
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Configuração + QR geral -->
    <div class="space-y-4">
        <!-- Configurar número de mesas -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 class="font-extrabold text-gray-900 mb-1">Configuração</h2>
            <p class="text-xs text-gray-400 mb-4">Defina quantas mesas gerar QR Codes</p>

            <form method="POST" action="/pizzaria/admin/qrcodes.php" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Número de mesas</label>
                    <input type="number" name="num_mesas" min="1" max="100" value="<?= $numMesas ?>"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-400">
                    <p class="text-xs text-gray-400 mt-1">Máximo 100 mesas</p>
                </div>
                <button type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm">
                    Salvar
                </button>
            </form>
        </div>

        <!-- QR Cardápio geral (sem mesa) -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
            <h2 class="font-extrabold text-gray-900 mb-1">Cardápio Geral</h2>
            <p class="text-xs text-gray-400 mb-4">Sem número de mesa — use na entrada ou em materiais impressos</p>
            <div id="qr-geral" class="flex justify-center mb-4"></div>
            <p class="text-xs text-gray-400 break-all mb-4"><?= htmlspecialchars($baseUrl) ?></p>
            <button onclick="imprimirQR('geral', 'Cardápio Geral')"
                    class="w-full flex items-center justify-center gap-2 border border-gray-200 text-gray-600 hover:bg-gray-50 font-semibold px-4 py-2 rounded-xl text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir
            </button>
        </div>
    </div>

    <!-- Grid de mesas -->
    <div class="lg:col-span-2">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <?php for ($i = 1; $i <= $numMesas; $i++): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                <p class="font-extrabold text-gray-800 text-sm mb-3">Mesa <?= $i ?></p>
                <div id="qr-mesa-<?= $i ?>" class="flex justify-center mb-3"></div>
                <button onclick="imprimirQR('mesa-<?= $i ?>', 'Mesa <?= $i ?>')"
                        class="w-full flex items-center justify-center gap-1.5 border border-gray-200 text-gray-600 hover:bg-gray-50 font-semibold px-3 py-2 rounded-xl text-xs transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
            </div>
            <?php endfor; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const BASE_URL   = <?= json_encode($baseUrl) ?>;
const NUM_MESAS  = <?= $numMesas ?>;
const NOME_PIZZ  = <?= json_encode(getConfig('nome_pizzaria', 'Bella Napoli')) ?>;

// Gera QR Code geral (sem mesa)
new QRCode(document.getElementById('qr-geral'), {
    text: BASE_URL,
    width: 140,
    height: 140,
    colorDark: '#111827',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
});

// Gera QR Codes para cada mesa
for (let i = 1; i <= NUM_MESAS; i++) {
    const el = document.getElementById('qr-mesa-' + i);
    if (!el) continue;
    new QRCode(el, {
        text: BASE_URL + '?mesa=' + i,
        width: 110,
        height: 110,
        colorDark: '#111827',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

function getQRDataURL(containerId) {
    const el = document.getElementById(containerId);
    if (!el) return null;
    const img = el.querySelector('img');
    if (img && img.src) return img.src;
    const canvas = el.querySelector('canvas');
    if (canvas) return canvas.toDataURL('image/png');
    return null;
}

function imprimirQR(id, label) {
    const dataUrl = getQRDataURL('qr-' + id);
    if (!dataUrl) { alert('QR Code ainda está sendo gerado. Tente novamente.'); return; }

    const win = window.open('', '_blank', 'width=400,height=500');
    win.document.write(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Imprimir QR — ${label}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@700;800;900&display=swap');
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #fff; }
  .card { text-align: center; padding: 32px; border: 2px solid #f3f4f6; border-radius: 20px; max-width: 300px; }
  .logo { font-size: 48px; margin-bottom: 8px; }
  .pizzeria { font-size: 14px; font-weight: 700; color: #374151; margin-bottom: 4px; }
  .label { font-size: 28px; font-weight: 900; color: #111827; margin-bottom: 20px; }
  .qr-wrap { display: inline-block; padding: 12px; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 16px; }
  .qr-wrap img { display: block; width: 180px; height: 180px; }
  .hint { font-size: 11px; color: #6b7280; line-height: 1.5; }
  @media print { body { padding: 0; } }
</style>
</head>
<body>
<div class="card">
  <div class="logo">🍕</div>
  <div class="pizzeria">${NOME_PIZZ}</div>
  <div class="label">${label}</div>
  <div class="qr-wrap"><img src="${dataUrl}" alt="QR Code"></div>
  <p class="hint">Escaneie para ver o cardápio completo</p>
</div>
<script>window.onload = () => window.print();<\/script>
</body>
</html>`);
    win.document.close();
}

function imprimirTodos() {
    const win = window.open('', '_blank', 'width=900,height=700');
    let cardsHtml = '';

    // Geral
    const geralUrl = getQRDataURL('qr-geral');
    if (geralUrl) {
        cardsHtml += buildCardHtml('Cardápio Geral', geralUrl);
    }

    for (let i = 1; i <= NUM_MESAS; i++) {
        const url = getQRDataURL('qr-mesa-' + i);
        if (url) cardsHtml += buildCardHtml('Mesa ' + i, url);
    }

    win.document.write(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Imprimir Todos os QR Codes</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@700;800;900&display=swap');
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Inter', sans-serif; background: #fff; padding: 20px; }
  .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  .card { text-align: center; padding: 20px; border: 1px solid #e5e7eb; border-radius: 16px; page-break-inside: avoid; }
  .logo { font-size: 32px; margin-bottom: 4px; }
  .pizzeria { font-size: 11px; font-weight: 700; color: #374151; margin-bottom: 2px; }
  .label { font-size: 20px; font-weight: 900; color: #111827; margin-bottom: 12px; }
  .qr-wrap { display: inline-block; padding: 8px; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 10px; }
  .qr-wrap img { display: block; width: 140px; height: 140px; }
  .hint { font-size: 10px; color: #9ca3af; }
  @media print { .grid { grid-template-columns: repeat(3, 1fr); } body { padding: 10px; } }
</style>
</head>
<body>
<div class="grid">${cardsHtml}</div>
<script>window.onload = () => window.print();<\/script>
</body>
</html>`);
    win.document.close();
}

function buildCardHtml(label, imgSrc) {
    return `<div class="card">
  <div class="logo">🍕</div>
  <div class="pizzeria">${NOME_PIZZ}</div>
  <div class="label">${label}</div>
  <div class="qr-wrap"><img src="${imgSrc}" alt="QR Code"></div>
  <p class="hint">Escaneie para ver o cardápio</p>
</div>`;
}
</script>
