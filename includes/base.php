<?php
// Fuso do sistema. O banco segue o mesmo fuso (ver getPDO em db.php).
date_default_timezone_set('America/Sao_Paulo');

// Endereco base do sistema e credenciais do banco, lidos uma vez so.
// Ficam em config.local.php, fora do Git (modelo: config.local.example.php).
if (!defined('BASE')) {
    $local = __DIR__ . '/config.local.php';
    if (is_file($local)) {
        require_once $local;
    }
    defined('BASE') || define('BASE', '/pizzaria');
}
