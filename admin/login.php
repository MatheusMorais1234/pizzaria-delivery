<?php
session_start();

// Já logado → vai pro dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE . '/admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$erro = '';

// Limite de tentativas: 5 erros seguidos do mesmo IP bloqueiam o login
// por 15 minutos. Sem isso, um robo testaria senhas sem parar.
const LOGIN_MAX_FALHAS = 5;
const LOGIN_BLOQUEIO_MIN = 15;

$pdo = getPDO();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$st  = $pdo->prepare('SELECT falhas, ultima_falha FROM admin_login_falhas WHERE ip = ?');
$st->execute([$ip]);
$reg = $st->fetch();
$bloqueado = $reg
    && (int) $reg['falhas'] >= LOGIN_MAX_FALHAS
    && strtotime($reg['ultima_falha']) > time() - LOGIN_BLOQUEIO_MIN * 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($bloqueado) {
        $erro = 'Muitas tentativas. Tente de novo em ' . LOGIN_BLOQUEIO_MIN . ' minutos.';
    } else {
        $admin = null;
        if ($email && $senha) {
            $stmt = $pdo->prepare('SELECT id, senha_hash FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
        }

        if ($admin && password_verify($senha, $admin['senha_hash'])) {
            $pdo->prepare('DELETE FROM admin_login_falhas WHERE ip = ?')->execute([$ip]);
            // Sessao nova ao entrar: um ID de sessao plantado antes do login
            // (session fixation) deixa de valer.
            session_regenerate_id(true);
            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['admin_email'] = $email;
            header('Location: ' . BASE . '/admin/dashboard.php');
            exit;
        }

        // Falha antiga (passou o bloqueio) recomeça a contagem
        $pdo->prepare('
            INSERT INTO admin_login_falhas (ip, falhas, ultima_falha) VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                falhas = IF(ultima_falha < NOW() - INTERVAL ? MINUTE, 1, falhas + 1),
                ultima_falha = NOW()
        ')->execute([$ip, LOGIN_BLOQUEIO_MIN]);
        $erro = 'E-mail ou senha incorretos.';
    }
}

$pageTitle = 'Login — Admin Bella Napoli';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 flex items-center justify-center px-4">


<div class="w-full max-w-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-red-600 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3 shadow-lg">🍕</div>
            <h1 class="text-2xl font-extrabold text-gray-900">Bella Napoli</h1>
            <p class="text-sm text-gray-400 mt-1">Painel Administrativo</p>
        </div>

        <?php if ($erro): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 text-sm mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zM9 7a1 1 0 112 0v4a1 1 0 11-2 0V7zm1 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
            </svg>
            <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <?php if (demo_ativa()): ?>
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-lg px-4 py-3 text-sm mb-4">
            <p class="font-semibold">Demonstração</p>
            <p class="mt-1">E-mail <code class="font-mono">demo@bellanapoli.com</code> · senha <code class="font-mono">demo1234</code></p>
            <p class="mt-1 text-amber-800/80">Pode mexer à vontade: os dados são recriados a cada 24 horas.</p>
            <button type="button" class="mt-2 text-amber-900 font-semibold underline"
                    onclick="document.querySelector('[name=email]').value='demo@bellanapoli.com';document.querySelector('[name=senha]').value='demo1234';this.closest('div').nextElementSibling.requestSubmit()">
                Entrar com a conta de demonstração
            </button>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                       placeholder="admin@pizzaria.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                <input type="password" name="senha" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                       placeholder="••••••">
            </div>
            <button type="submit"
                    class="w-full bg-red-600 hover:bg-orange-600 text-white font-bold py-3 rounded-xl transition-colors mt-2">
                Entrar
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-6">
            Acesso restrito à equipe da pizzaria
        </p>
    </div>
</div>

</body></html>
