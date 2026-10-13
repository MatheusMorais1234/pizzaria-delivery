-- ====================================================================
-- Pizzaria Bella Napoli — Schema completo
-- Versão: 2.1  |  Compatível com MySQL 5.7+ / MariaDB 10.3+
--
-- Como usar no XAMPP (phpMyAdmin):
--   1. Abra http://localhost/phpmyadmin
--   2. Clique na aba "SQL" no menu superior (sem selecionar nenhum banco)
--   3. Cole todo o conteúdo deste arquivo e clique em "Executar"
--      → O banco "pizzaria" será criado automaticamente pelo script.
--
-- Após importar, acesse o sistema em:
--   http://localhost/pizzaria/
--
-- Acesso admin padrão:
--   URL:   http://localhost/pizzaria/admin/
--   Email: admin@pizzaria.com
--   Senha: 123456
-- ====================================================================

CREATE DATABASE IF NOT EXISTS pizzaria
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pizzaria;

-- ── Remove tabelas existentes (ordem inversa de FK) ──────────────────
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `pedido_itens`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `produto_ingredientes`;
DROP TABLE IF EXISTS `adicionais`;
DROP TABLE IF EXISTS `combos`;
DROP TABLE IF EXISTS `cupons`;
DROP TABLE IF EXISTS `produtos`;
DROP TABLE IF EXISTS `categorias`;
DROP TABLE IF EXISTS `configuracoes`;
DROP TABLE IF EXISTS `admins`;
SET FOREIGN_KEY_CHECKS = 1;

-- ────────────────────────────────────────────────────────────────────
-- 1. CATEGORIAS
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE `categorias` (
    `id`    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nome`  VARCHAR(100)    NOT NULL,
    `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 2. PRODUTOS
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `produtos` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `categoria_id` INT UNSIGNED    NOT NULL,
    `nome`         VARCHAR(150)    NOT NULL,
    `descricao`    TEXT            DEFAULT NULL,
    `preco`        DECIMAL(8,2)    NOT NULL,
    `imagem_url`   VARCHAR(500)    DEFAULT NULL,
    `ativo`        TINYINT(1)      NOT NULL DEFAULT 1,
    `desconto_pct` DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `categoria_id` (`categoria_id`),
    CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 3. INGREDIENTES DOS PRODUTOS (para o cliente poder remover)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `produto_ingredientes` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `produto_id` INT UNSIGNED  NOT NULL,
    `nome`       VARCHAR(100)  NOT NULL,
    PRIMARY KEY (`id`),
    KEY `produto_id` (`produto_id`),
    CONSTRAINT `produto_ingredientes_ibfk_1`
        FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 4. ADICIONAIS (extras com preço)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `adicionais` (
    `id`    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `nome`  VARCHAR(100)  NOT NULL,
    `preco` DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `ativo` TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 5. COMBOS (pizza + bebida com preço especial)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `combos` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `nome`           VARCHAR(150)   NOT NULL,
    `descricao`      TEXT           DEFAULT NULL,
    `preco_original` DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
    `preco_combo`    DECIMAL(8,2)   NOT NULL,
    `imagem_url`     VARCHAR(500)   DEFAULT NULL,
    `badge`          VARCHAR(50)    DEFAULT NULL,
    `ativo`          TINYINT(1)     NOT NULL DEFAULT 1,
    `ordem`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 6. PEDIDOS
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pedidos` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cliente_nome`         VARCHAR(150)  NOT NULL,
    `cliente_telefone`     VARCHAR(20)   NOT NULL,
    `endereco`             VARCHAR(300)  NOT NULL,
    `observacao`           TEXT          DEFAULT NULL,
    `total`                DECIMAL(10,2) NOT NULL,
    `status`               ENUM('pendente','em_preparo','saiu_entrega','entregue') NOT NULL DEFAULT 'pendente',
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cupom_codigo`         VARCHAR(50)   DEFAULT NULL,
    `desconto_cupom`       DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `tipo_entrega`         ENUM('entrega','retirada') NOT NULL DEFAULT 'entrega',
    `forma_pagamento`      ENUM('dinheiro','credito','debito','pix') NOT NULL DEFAULT 'dinheiro',
    `troco_para`           DECIMAL(8,2)  DEFAULT NULL,
    `taxa_cartao_pct`      DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `taxa_entrega`         DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `bairro`               VARCHAR(100)  DEFAULT NULL,
    `motivo_cancelamento`  TEXT          DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 7. ITENS DOS PEDIDOS
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pedido_itens` (
    `id`                     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `pedido_id`              INT UNSIGNED    NOT NULL,
    `produto_id`             INT UNSIGNED    NOT NULL,
    `quantidade`             TINYINT UNSIGNED NOT NULL,
    `preco_unitario`         DECIMAL(8,2)    NOT NULL,
    `subtotal`               DECIMAL(10,2)   NOT NULL,
    `ingredientes_removidos` TEXT            DEFAULT NULL,
    `adicionais_json`        TEXT            DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `pedido_id` (`pedido_id`),
    KEY `produto_id` (`produto_id`),
    CONSTRAINT `pedido_itens_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
    CONSTRAINT `pedido_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 8. CUPONS DE DESCONTO
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cupons` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `codigo`        VARCHAR(50)   NOT NULL,
    `tipo`          ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
    `valor`         DECIMAL(8,2)  NOT NULL,
    `pedido_minimo` DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `uso_limite`    INT UNSIGNED  DEFAULT NULL,
    `usos`          INT UNSIGNED  NOT NULL DEFAULT 0,
    `ativo`         TINYINT(1)    NOT NULL DEFAULT 1,
    `validade`      DATE          DEFAULT NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 9. CONFIGURAÇÕES (chave–valor)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `configuracoes` (
    `chave` VARCHAR(100) NOT NULL,
    `valor` TEXT         NOT NULL,
    PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 10. ADMINISTRADORES
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(150)  NOT NULL,
    `senha_hash` VARCHAR(255)  NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ====================================================================
-- DADOS INICIAIS
-- ====================================================================

-- ── Categorias ───────────────────────────────────────────────────────
INSERT INTO `categorias` (`nome`, `ordem`) VALUES
    ('Pizzas',     1),
    ('Bebidas',    2),
    ('Sobremesas', 3);

-- ── Pizzas ───────────────────────────────────────────────────────────
INSERT INTO `produtos` (`categoria_id`, `nome`, `descricao`, `preco`, `imagem_url`, `ativo`, `desconto_pct`) VALUES
(1, 'Margherita Clássica',
 'Molho de tomate artesanal, mussarela de búfala, folhas de manjericão fresco e fio de azeite extra virgem.',
 42.90, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&q=80', 1, 0.00),

(1, 'Calabresa Suprema',
 'Rodelas generosas de calabresa defumada, cebola roxa, azeitonas pretas e mussarela derretida.',
 46.90, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&q=80', 1, 10.00),

(1, 'Frango com Catupiry',
 'Frango desfiado temperado, catupiry original cremoso, milho verde e mussarela gratinada.',
 48.90, 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=600&q=80', 1, 0.00),

(1, 'Quatro Queijos',
 'Blend irresistível de mussarela, parmesão, gorgonzola e provolone sobre molho de tomate suave.',
 52.90, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&q=80', 1, 0.00),

(1, 'Pepperoni Picante',
 'Generosas fatias de pepperoni importado, pimentas vermelhas, mussarela e molho especial da casa.',
 54.90, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600&q=80', 1, 0.00);

-- ── Bebidas ──────────────────────────────────────────────────────────
INSERT INTO `produtos` (`categoria_id`, `nome`, `descricao`, `preco`, `imagem_url`, `ativo`, `desconto_pct`) VALUES
(2, 'Coca-Cola 2L',
 'Refrigerante gelado, perfeito para acompanhar sua pizza favorita.',
 12.90, 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=600&q=80', 1, 0.00),

(2, 'Suco de Laranja Natural 500ml',
 'Suco espremido na hora, sem conservantes, cheio de vitamina C.',
 10.90, 'https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=600&q=80', 1, 0.00);

-- ── Sobremesas ───────────────────────────────────────────────────────
INSERT INTO `produtos` (`categoria_id`, `nome`, `descricao`, `preco`, `imagem_url`, `ativo`, `desconto_pct`) VALUES
(3, 'Petit Gâteau de Chocolate',
 'Bolinho quente de chocolate com interior cremoso, servido com sorvete de creme e calda de frutas vermelhas.',
 22.90, 'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=600&q=80', 1, 0.00);

-- ── Ingredientes por produto ─────────────────────────────────────────
INSERT INTO `produto_ingredientes` (`produto_id`, `nome`) VALUES
-- Margherita Clássica (id=1)
(1, 'Molho de tomate'), (1, 'Mussarela de búfala'), (1, 'Manjericão'), (1, 'Azeite extra virgem'),
-- Calabresa Suprema (id=2)
(2, 'Molho de tomate'), (2, 'Calabresa'), (2, 'Cebola roxa'), (2, 'Azeitonas pretas'), (2, 'Mussarela'),
-- Frango com Catupiry (id=3)
(3, 'Molho de tomate'), (3, 'Frango desfiado'), (3, 'Catupiry'), (3, 'Milho verde'), (3, 'Mussarela'),
-- Quatro Queijos (id=4)
(4, 'Molho de tomate'), (4, 'Mussarela'), (4, 'Parmesão'), (4, 'Gorgonzola'), (4, 'Provolone'),
-- Pepperoni Picante (id=5)
(5, 'Molho de tomate'), (5, 'Pepperoni'), (5, 'Pimentas vermelhas'), (5, 'Mussarela'), (5, 'Molho especial da casa');

-- ── Adicionais ───────────────────────────────────────────────────────
INSERT INTO `adicionais` (`nome`, `preco`, `ativo`) VALUES
('Borda recheada com catupiry', 8.00, 1),
('Mussarela extra',             5.00, 1),
('Bacon crocante',              6.00, 1),
('Cheddar',                     4.00, 1),
('Azeitonas extras',            3.00, 1);

-- ── Configurações padrão ─────────────────────────────────────────────
-- Horários: Dom fechado | Seg-Sex 18:00-23:00 | Sáb 12:00-23:00
INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('pizzaria_aberta',   '1'),
('tempo_entrega_min', '40'),
('hora_abertura',     '18:00'),
('hora_fechamento',   '23:00'),
('horarios_semana',   '[{"ativo":0,"abre":"18:00","fecha":"23:00"},{"ativo":1,"abre":"18:00","fecha":"23:00"},{"ativo":1,"abre":"18:00","fecha":"23:00"},{"ativo":1,"abre":"18:00","fecha":"23:00"},{"ativo":1,"abre":"18:00","fecha":"23:00"},{"ativo":1,"abre":"18:00","fecha":"23:00"},{"ativo":1,"abre":"12:00","fecha":"23:00"}]'),
-- Contato (preencher no painel Admin → Configurações)
('telefone',          ''),
('whatsapp',          ''),
('endereco',          ''),
('instagram',         ''),
('facebook',          ''),
('maps_embed',        ''),
('imagem_pizzaiolo',  ''),
-- Landing page
('aniversariante_beneficio', 'Apresente um documento com foto no balcão e ganhe uma sobremesa grátis no seu aniversário! Válido apenas no dia do aniversário, para pedidos no local.'),
('sobre_nos_texto',   ''),
('sobre_nos_anos',    '15+'),
('sobre_nos_sabores', '30+');

-- ── Admin padrão ─────────────────────────────────────────────────────
-- Login: admin@pizzaria.com  |  Senha: 123456
-- Hash gerado com password_hash('123456', PASSWORD_DEFAULT)
INSERT INTO `admins` (`email`, `senha_hash`) VALUES
('admin@pizzaria.com', '$2y$10$6r8Nsa2OK6QBi6x3iXf4lOLWhZNYhSLC6hBkbEr73pzoFiRSwZ6wC');
