<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin — Bella Napoli') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sidebar: '#111827',
                        'sidebar-hover': '#1f2937',
                        'sidebar-active': '#991b1b',
                        accent: '#dc2626',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        * { font-family: 'Inter', sans-serif; }
        .nav-item { transition: all 0.15s ease; }
        .nav-item:hover { background: #1f2937; }
        .nav-item.active { background: #991b1b; }
        #sidebar { transition: transform 0.25s ease; }
        #sidebar.closed { transform: translateX(-100%); }
        @media (min-width: 768px) {
            #sidebar.closed { transform: translateX(0); }
        }
        /* Scrollbar fina */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 2px; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php
// Detecta página atual para marcar nav ativo
$paginaAtual = basename($_SERVER['PHP_SELF'], '.php');
function navAtivo($paginas, $atual) {
    return in_array($atual, (array)$paginas) ? 'active' : '';
}

// Conta pendentes para badge
require_once __DIR__ . '/../../includes/db.php';
$pendentesNav = getPDO()->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
?>

<div class="flex min-h-screen">

    <!-- ===================== SIDEBAR ===================== -->
    <aside id="sidebar"
           class="w-64 bg-gray-900 flex flex-col fixed inset-y-0 left-0 z-40 overflow-y-auto">

        <!-- Logo -->
        <div class="px-6 py-6 border-b border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-600 rounded-xl flex items-center justify-center text-xl flex-shrink-0">🍕</div>
                <div>
                    <p class="text-white font-extrabold text-base leading-tight">Bella Napoli</p>
                    <p class="text-gray-500 text-xs">Painel Admin</p>
                </div>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

            <p class="text-gray-600 text-xs font-semibold uppercase tracking-widest px-3 mb-2">Menu</p>

            <!-- Dashboard -->
            <a href="/pizzaria/admin/dashboard.php"
               class="nav-item <?= navAtivo('dashboard', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <!-- chart-bar icon -->
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Dashboard</span>
            </a>

            <!-- Pedidos -->
            <a href="/pizzaria/admin/pedidos.php"
               class="nav-item <?= navAtivo('pedidos', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </span>
                <span class="font-medium text-sm flex-1">Pedidos</span>
                <?php if ($pendentesNav > 0): ?>
                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center">
                    <?= $pendentesNav ?>
                </span>
                <?php endif; ?>
            </a>

            <!-- Categorias -->
            <a href="/pizzaria/admin/categorias.php"
               class="nav-item <?= navAtivo('categorias', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5l2 2h5a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h2z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Categorias</span>
            </a>

            <!-- Produtos -->
            <a href="/pizzaria/admin/produtos.php"
               class="nav-item <?= navAtivo(['produtos','produto-form'], $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Produtos</span>
            </a>

            <!-- Adicionais -->
            <a href="/pizzaria/admin/adicionais.php"
               class="nav-item <?= navAtivo('adicionais', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Adicionais</span>
            </a>

            <!-- Combos -->
            <a href="/pizzaria/admin/combos.php"
               class="nav-item <?= navAtivo(['combos','combo-form'], $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Combos</span>
            </a>

            <!-- Histórico -->
            <a href="/pizzaria/admin/historico.php"
               class="nav-item <?= navAtivo('historico', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Histórico</span>
            </a>

            <!-- Avaliações -->
            <a href="/pizzaria/admin/avaliacoes.php"
               class="nav-item <?= navAtivo('avaliacoes', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Avaliações</span>
            </a>

            <!-- Relatório Bairros -->
            <a href="/pizzaria/admin/relatorio-bairros.php"
               class="nav-item <?= navAtivo('relatorio-bairros', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Relatório Bairros</span>
            </a>

            <!-- Cupons -->
            <a href="/pizzaria/admin/cupons.php"
               class="nav-item <?= navAtivo('cupons', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700 text-base">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M17 17h.01M3 7a4 4 0 014-4h.01M17 3h.01A4 4 0 0121 7v.01M21 17v.01A4 4 0 0117 21h-.01M7 21H7a4 4 0 01-4-4v-.01M3 12h18"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Cupons</span>
            </a>

            <!-- Fidelidade -->
            <a href="/pizzaria/admin/fidelidade.php"
               class="nav-item <?= navAtivo('fidelidade', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Fidelidade</span>
            </a>

            <!-- Bairros -->
            <a href="/pizzaria/admin/bairros.php"
               class="nav-item <?= navAtivo('bairros', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Bairros / Taxa</span>
            </a>

            <!-- QR Codes -->
            <a href="/pizzaria/admin/qrcodes.php"
               class="nav-item <?= navAtivo('qrcodes', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">QR Codes</span>
            </a>

            <!-- Configurações -->
            <a href="/pizzaria/admin/configuracoes.php"
               class="nav-item <?= navAtivo('configuracoes', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-300 hover:text-white group">
                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
                <span class="font-medium text-sm">Configurações</span>
            </a>

            <!-- Atalhos -->
            <div class="pt-2">
                <p class="text-gray-600 text-xs font-semibold uppercase tracking-widest px-3 mb-2">Atalhos</p>

                <!-- Novo pedido manual -->
                <a href="/pizzaria/admin/pedido-manual.php"
                   class="nav-item <?= navAtivo('pedido-manual', $paginaAtual) ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-400 hover:text-white group">
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-green-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <span class="font-medium text-sm">Pedido WhatsApp</span>
                </a>

                <a href="/pizzaria/" target="_blank"
                   class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-gray-400 hover:text-white group">
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-800 group-hover:bg-gray-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </span>
                    <span class="font-medium text-sm">Ver cardápio</span>
                </a>
            </div>
        </nav>

        <!-- Rodapé do sidebar: info do admin -->
        <div class="px-4 py-4 border-t border-gray-800">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-full bg-red-700 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    <?= strtoupper(substr($_SESSION['admin_email'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <p class="text-white text-xs font-semibold truncate"><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></p>
                    <p class="text-gray-500 text-xs">Administrador</p>
                </div>
            </div>
            <a href="/pizzaria/admin/logout.php"
               class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sair
            </a>
        </div>
    </aside>

    <!-- Overlay mobile -->
    <div id="overlay" class="hidden fixed inset-0 bg-black/50 z-30 md:hidden" onclick="toggleSidebar()"></div>

    <!-- ===================== CONTEÚDO ===================== -->
    <div class="flex-1 flex flex-col min-w-0 md:ml-64">

        <!-- Topbar mobile -->
        <header class="md:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-20">
            <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="font-bold text-gray-800">Bella Napoli Admin</span>
            <div class="w-9"></div>
        </header>

        <!-- Área de conteúdo das páginas -->
        <main class="flex-1 p-6 lg:p-8 overflow-auto">
