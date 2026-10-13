<?php
session_start();

// Já logado → vai pro dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: /pizzaria/admin/dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email && $senha) {
        $stmt = getPDO()->prepare('SELECT id, senha_hash FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha_hash'])) {
            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['admin_email'] = $email;
            header('Location: /pizzaria/admin/dashboard.php');
            exit;
        }
    }
    $erro = 'E-mail ou senha incorretos.';
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
