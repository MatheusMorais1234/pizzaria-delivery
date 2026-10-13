<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
$pdo = getPDO();
$bairros = $pdo->query('SELECT id, nome, taxa_entrega FROM bairros WHERE ativo=1 ORDER BY nome')->fetchAll();
echo json_encode($bairros);
