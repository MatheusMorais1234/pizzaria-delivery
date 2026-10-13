<?php
// ====================================================================
// Modo demonstracao (DEMO = true no config.local.php)
// --------------------------------------------------------------------
// Na demo publica qualquer visitante faz pedidos e mexe no painel.
// Para ela nunca ficar baguncada, o banco inteiro e recriado a cada
// 24 horas a partir de banco/schema.sql + migracoes + banco/demo.sql.
//
// Nao ha cron no plano de hospedagem, entao quem dispara a recriacao e
// a primeira requisicao depois que o prazo vence. GET_LOCK garante que
// duas requisicoes simultaneas nao recriem o banco ao mesmo tempo.
// ====================================================================

function demo_ativa(): bool {
    return defined('DEMO') && DEMO === true;
}

function demo_recriar_se_preciso(PDO $pdo): void {
    if (!demo_ativa()) return;

    try {
        $ultima = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'demo_recriada_em'")->fetchColumn();
        if ($ultima && strtotime($ultima) > time() - 24 * 3600) return;
    } catch (Exception $e) {
        // tabela ainda nao existe: banco vazio, recria
    }

    if (!(int) $pdo->query("SELECT GET_LOCK('pizzaria_demo', 0)")->fetchColumn()) return;
    try {
        demo_recriar($pdo);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('pizzaria_demo')");
    }
}

// Le um .sql tirando CREATE DATABASE e USE. O schema.sql tem "USE pizzaria"
// para quem importa pelo phpMyAdmin - executado aqui, ele trocava a conexao
// para o banco "pizzaria" e a demo recriava o banco errado.
function demo_sql(string $arquivo): string {
    $sql = file_get_contents(__DIR__ . '/../banco/' . $arquivo);
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;/ims', '', $sql);
    $sql = preg_replace('/^\s*USE\s+[`\w]+\s*;/im', '', $sql);
    if (preg_match('/^\s*(USE|CREATE\s+DATABASE|DROP\s+DATABASE)\b/im', $sql)) {
        throw new RuntimeException("demo: $arquivo ainda troca ou cria banco - abortado");
    }
    return $sql;
}

// Trava: o banco conectado tem que ser o do config.local.php, antes e
// depois de cada etapa. Se algo trocar de banco no meio, para tudo.
function demo_conferir_banco(PDO $pdo): void {
    $atual = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($atual !== DB_NAME) {
        throw new RuntimeException("demo: conectado em '$atual', esperado '" . DB_NAME . "' - abortado");
    }
}

function demo_recriar(PDO $pdo): void {
    demo_conferir_banco($pdo);
    $schema = demo_sql('schema.sql');
    $demo   = demo_sql('demo.sql');

    // Apaga todas as tabelas - inclusive as criadas pelas migracoes, que o
    // schema.sql nao conhece
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabela) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $tabela) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec($schema);
    demo_conferir_banco($pdo);
    _migrarBanco($pdo);
    $pdo->exec($demo);
    demo_conferir_banco($pdo);

    $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('schema_versao', ?), ('demo_recriada_em', NOW())
                   ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
        ->execute([SCHEMA_VERSAO]);
}
