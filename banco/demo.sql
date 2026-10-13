-- ====================================================================
-- Dados da DEMONSTRACAO publica
-- --------------------------------------------------------------------
-- Roda depois do schema.sql e das migracoes, so quando DEMO = true no
-- config.local.php (includes/demo.php). Recriado a cada 24 horas.
-- Tudo aqui e ficticio: nomes, telefones e enderecos.
-- ====================================================================

-- Admin de demonstracao (a senha aparece na tela de login da demo).
-- O admin padrao do schema.sql (senha 123456) nao existe na demo.
DELETE FROM admins;
INSERT INTO admins (email, senha_hash) VALUES
('demo@bellanapoli.com', '$2y$10$GxUQ/A7Rb7PWjZ2doyPub.B2I39ZFCYPomAIpnprEa2BxkbkZAPfm');

-- Sempre aberta: quem visita a demo de madrugada tambem consegue pedir
UPDATE configuracoes SET valor = '1' WHERE chave = 'pizzaria_aberta';
UPDATE configuracoes SET valor = '[{"ativo":1,"abre":"00:00","fecha":"00:00"},{"ativo":1,"abre":"00:00","fecha":"00:00"},{"ativo":1,"abre":"00:00","fecha":"00:00"},{"ativo":1,"abre":"00:00","fecha":"00:00"},{"ativo":1,"abre":"00:00","fecha":"00:00"},{"ativo":1,"abre":"00:00","fecha":"00:00"},{"ativo":1,"abre":"00:00","fecha":"00:00"}]'
 WHERE chave = 'horarios_semana';
INSERT INTO configuracoes (chave, valor) VALUES ('fidelidade_ativo', '1')
 ON DUPLICATE KEY UPDATE valor = VALUES(valor);

INSERT INTO bairros (nome, taxa_entrega, ativo) VALUES
('Setor Bueno', 6.00, 1),
('Setor Marista', 7.00, 1),
('Setor Oeste', 6.00, 1),
('Jardim Goiás', 8.00, 1),
('Setor Central', 5.00, 1);

INSERT INTO cupons (codigo, tipo, valor, pedido_minimo, ativo) VALUES
('BEMVINDO10', 'percentual', 10.00, 40.00, 1);

-- Pedidos de exemplo, um em cada etapa do kanban. Horarios relativos a
-- agora, para o painel nunca parecer parado no tempo.
INSERT INTO pedidos (cliente_nome, cliente_telefone, endereco, observacao, total, status, created_at, tipo_entrega, forma_pagamento, taxa_entrega, bairro, token) VALUES
('Ana Souza',      '(62) 90000-0101', 'Rua 9, 120 — Setor Oeste — Goiânia',          'Sem cebola, por favor', 54.90, 'pendente',     NOW() - INTERVAL 4 MINUTE,  'entrega',  'pix',      6.00, 'Setor Oeste',   MD5(RAND())),
('Bruno Lima',     '(62) 90000-0102', 'Retirada na loja',                              NULL,                    61.80, 'pendente',     NOW() - INTERVAL 9 MINUTE,  'retirada', 'credito',  0.00, NULL,            MD5(RAND())),
('Carla Mendes',   '(62) 90000-0103', 'Av. T-4, 455 — Setor Bueno — Goiânia',         NULL,                    98.70, 'em_preparo',   NOW() - INTERVAL 18 MINUTE, 'entrega',  'dinheiro', 6.00, 'Setor Bueno',   MD5(RAND())),
('Diego Rocha',    '(62) 90000-0104', 'Rua 1137, 80 — Setor Marista — Goiânia',       'Interfone 302',         59.80, 'saiu_entrega', NOW() - INTERVAL 35 MINUTE, 'entrega',  'pix',      7.00, 'Setor Marista', MD5(RAND())),
('Elisa Prado',    '(62) 90000-0105', 'Rua 15, 30 — Setor Central — Goiânia',         NULL,                    47.90, 'entregue',     NOW() - INTERVAL 2 HOUR,    'entrega',  'debito',   5.00, 'Setor Central', MD5(RAND())),
('Fábio Teixeira', '(62) 90000-0106', 'Av. Jamel Cecílio, 900 — Jardim Goiás — Goiânia', NULL,                 75.70, 'entregue',     NOW() - INTERVAL 1 DAY,     'entrega',  'credito',  8.00, 'Jardim Goiás',  MD5(RAND()));

INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal, ingredientes_removidos)
SELECT p.id, i.produto_id, i.qtd, pr.preco, pr.preco * i.qtd, i.sem
FROM (
    SELECT 'Ana Souza' AS cliente, 5 AS produto_id, 1 AS qtd, '["Cebola"]' AS sem UNION ALL
    SELECT 'Bruno Lima',     3, 1, NULL UNION ALL
    SELECT 'Bruno Lima',     6, 1, NULL UNION ALL
    SELECT 'Carla Mendes',   4, 1, NULL UNION ALL
    SELECT 'Carla Mendes',   2, 1, NULL UNION ALL
    SELECT 'Diego Rocha',    1, 1, NULL UNION ALL
    SELECT 'Diego Rocha',    7, 1, NULL UNION ALL
    SELECT 'Elisa Prado',    1, 1, NULL UNION ALL
    SELECT 'Fábio Teixeira', 5, 1, NULL UNION ALL
    SELECT 'Fábio Teixeira', 8, 1, NULL
) i
JOIN pedidos  p  ON p.cliente_nome = i.cliente
JOIN produtos pr ON pr.id = i.produto_id;

INSERT INTO avaliacoes (pedido_id, nota, comentario)
SELECT id, 5, 'Chegou quentinha e antes do previsto.' FROM pedidos WHERE cliente_nome = 'Elisa Prado';
INSERT INTO avaliacoes (pedido_id, nota, comentario)
SELECT id, 4, 'Muito boa! Só faltou um pouco mais de borda.' FROM pedidos WHERE cliente_nome = 'Fábio Teixeira';

INSERT INTO fidelidade (telefone, nome, pontos) VALUES
('62900000105', 'Elisa Prado', 47),
('62900000106', 'Fábio Teixeira', 120);
