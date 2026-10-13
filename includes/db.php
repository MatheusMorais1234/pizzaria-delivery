<?php
// Conexão PDO com o banco de dados MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'pizzaria');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        _migrarSeNecessario($pdo);
    }
    return $pdo;
}

// Versao do esquema. Ao adicionar uma alteracao em _migrarBanco(),
// aumente este numero: as migracoes so rodam quando ele muda, e nao
// mais em toda requisicao.
const SCHEMA_VERSAO = '3';

function _migrarSeNecessario(PDO $pdo): void {
    try {
        $atual = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'schema_versao'")->fetchColumn();
        if ($atual === SCHEMA_VERSAO) return;
    } catch (Exception $e) {}
    _migrarBanco($pdo);
    $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('schema_versao', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
        ->execute([SCHEMA_VERSAO]);
}

function _migrarBanco(PDO $pdo): void {
    // Desconto por produto
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN desconto_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    // Cupom no pedido
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN cupom_codigo VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN desconto_cupom DECIMAL(8,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    // Tipo de entrega
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN tipo_entrega ENUM('entrega','retirada') NOT NULL DEFAULT 'entrega'"); } catch (Exception $e) {}
    // Motivo de cancelamento
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN motivo_cancelamento TEXT DEFAULT NULL"); } catch (Exception $e) {}
    // Tabela de cupons
    $pdo->exec("CREATE TABLE IF NOT EXISTS cupons (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo        VARCHAR(50)  NOT NULL UNIQUE,
        tipo          ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
        valor         DECIMAL(8,2) NOT NULL,
        pedido_minimo DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        uso_limite    INT UNSIGNED DEFAULT NULL,
        usos          INT UNSIGNED NOT NULL DEFAULT 0,
        ativo         TINYINT(1)   NOT NULL DEFAULT 1,
        validade      DATE         DEFAULT NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Tabela de avaliações
    $pdo->exec("CREATE TABLE IF NOT EXISTS avaliacoes (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pedido_id  INT UNSIGNED NOT NULL UNIQUE,
        nota       TINYINT UNSIGNED NOT NULL,
        comentario TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Bairros com taxa de entrega
    $pdo->exec("CREATE TABLE IF NOT EXISTS bairros (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome         VARCHAR(100) NOT NULL,
        taxa_entrega DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        ativo        TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Taxa e bairro no pedido
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN taxa_entrega DECIMAL(8,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN bairro VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    // Tamanhos de produto
    $pdo->exec("CREATE TABLE IF NOT EXISTS produto_tamanhos (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        produto_id INT UNSIGNED NOT NULL,
        nome       VARCHAR(50) NOT NULL,
        preco      DECIMAL(8,2) NOT NULL,
        ativo      TINYINT(1) NOT NULL DEFAULT 1,
        ordem      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        INDEX (produto_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE pedido_itens ADD COLUMN tamanho_nome VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    // Combo support
    try { $pdo->exec("ALTER TABLE pedido_itens ADD COLUMN combo_id INT UNSIGNED DEFAULT NULL"); } catch (Exception $e) {}
    // Estoque básico
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN estoque INT DEFAULT NULL COMMENT 'NULL=ilimitado, 0=esgotado'"); } catch (Exception $e) {}

    // Fidelidade — colunas no pedido
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN pontos_ganhos INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN pontos_resgatados INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN desconto_fidelidade DECIMAL(8,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}

    // Tabela de saldo de pontos por cliente
    $pdo->exec("CREATE TABLE IF NOT EXISTS fidelidade (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        telefone   VARCHAR(20) NOT NULL UNIQUE,
        nome       VARCHAR(100) DEFAULT NULL,
        pontos     INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Log de transações de pontos
    $pdo->exec("CREATE TABLE IF NOT EXISTS fidelidade_log (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        telefone   VARCHAR(20) NOT NULL,
        pedido_id  INT UNSIGNED DEFAULT NULL,
        pontos     INT NOT NULL,
        tipo       ENUM('credito','debito') NOT NULL,
        descricao  VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (telefone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Mesa no pedido
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN mesa VARCHAR(20) DEFAULT NULL"); } catch (Exception $e) {}
    // Extend tipo_entrega enum to include 'mesa'
    try { $pdo->exec("ALTER TABLE pedidos MODIFY COLUMN tipo_entrega ENUM('entrega','retirada','mesa') NOT NULL DEFAULT 'entrega'"); } catch (Exception $e) {}
    // Codigo secreto do pedido: o link de acompanhamento leva id + token,
    // para ninguem ver o pedido dos outros trocando o numero na URL
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN token CHAR(32) DEFAULT NULL, ADD UNIQUE KEY uq_pedidos_token (token)"); } catch (Exception $e) {}
    // Tentativas de login erradas por IP (limite no admin/login.php)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_login_falhas (
        ip           VARCHAR(45) NOT NULL PRIMARY KEY,
        falhas       INT UNSIGNED NOT NULL DEFAULT 0,
        ultima_falha DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
