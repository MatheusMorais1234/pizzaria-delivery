<?php
// ============================================================
// Configuracao do ambiente - MODELO
// ------------------------------------------------------------
// Copie para includes/config.local.php e preencha. O arquivo
// copiado fica fora do Git (.gitignore): e nele que mora a
// senha do banco.
// ============================================================

// Pasta onde o sistema fica dentro do dominio, sem barra no fim.
//   http://localhost/pizzaria/       -> '/pizzaria'
//   https://pizzaria.exemplo.com/    -> ''
define('BASE', '/pizzaria');

define('DB_HOST', 'localhost');
define('DB_NAME', 'pizzaria');
define('DB_USER', 'root');
define('DB_PASS', '');
