# Bella Napoli — sistema de delivery para pizzaria

Sistema completo de pedidos para pizzaria, em **PHP 8 + MySQL** sem framework:
o cliente monta o pedido pelo celular e acompanha cada etapa, e a pizzaria
controla tudo num painel com kanban.

**▶ Demo no ar: [pizzaria.matheusmorais.com](https://pizzaria.matheusmorais.com)**
— painel em [/admin](https://pizzaria.matheusmorais.com/admin/login.php)
(`demo@bellanapoli.com` / `demo1234`). Os dados são recriados a cada 24 horas,
então pode mexer à vontade.

> Bella Napoli é uma marca fictícia, criada para este projeto.

---

## O que tem

**Para o cliente**
- Cardápio com categorias, busca, tamanhos, adicionais e remoção de ingredientes
- Carrinho com cupom de desconto, taxa de entrega por bairro, troco e
  taxa de cartão
- Programa de fidelidade: pontos por pedido, resgatáveis como desconto
- Acompanhamento do pedido em tempo real, com tempo estimado
- **Cardápio de mesa por QR Code**: o pedido já chega com o número da mesa
- Avaliação do pedido depois da entrega
- "Meus pedidos": histórico dos pedidos feitos no aparelho

**Para a pizzaria**
- Pedidos em **kanban** (novo → em preparo → saiu para entrega → entregue),
  com atualização automática e cancelamento com motivo
- Pedido manual, para balcão e telefone
- Produtos, categorias, combos, adicionais, ingredientes e tamanhos
- Cupons, fidelidade, bairros e taxas, horário de funcionamento por dia
- Dashboard do dia, histórico com filtros, relatório por bairro e avaliações
- Gerador de QR Codes das mesas

---

## Segurança

O sistema passou por uma revisão antes de ir para o ar. Cada correção está
num commit próprio no histórico.

| Ponto | Como está |
|---|---|
| SQL injection | Todo SQL com prepared statements (PDO, sem emulação) |
| Preço | Calculado no servidor; o preço enviado pelo navegador é ignorado |
| XSS | Saída escapada no PHP e no JavaScript; ingredientes removidos são validados contra o cadastro do produto |
| Dados de clientes | O pedido só é visto com o código secreto do link (`?id=&t=`); nada é consultado por telefone |
| CSRF | Token por sessão em todo POST do painel, injetado automaticamente em formulários e `fetch` |
| Login | `password_hash`, sessão renovada ao entrar, bloqueio de 15 min após 5 erros |
| Upload | Tipo verificado pelo conteúdo do arquivo, nome aleatório, pasta sem execução de scripts |
| Configuração | Credenciais fora do Git, em `includes/config.local.php` |

---

## Rodando localmente

Requisitos: PHP 8.1+ e MySQL/MariaDB (o XAMPP serve).

1. Coloque a pasta em `htdocs/pizzaria`.
2. Crie o banco importando `banco/schema.sql` (cria o banco `pizzaria`).
3. Copie `includes/config.local.example.php` para `includes/config.local.php`
   e ajuste as credenciais.
4. Abra `http://localhost/pizzaria/`.

Painel: `http://localhost/pizzaria/admin/` — login inicial
`admin@pizzaria.com` / `123456`. **Troque essa senha** antes de usar o sistema
fora da sua máquina.

As alterações de banco posteriores ao `schema.sql` ficam em
`includes/db.php` (`_migrarBanco`) e rodam sozinhas, uma vez por versão.

### Modo demonstração

Com `define('DEMO', true)` no `config.local.php`, o banco é recriado a cada
24 horas com `banco/schema.sql` + `banco/demo.sql`, o login de teste aparece na
tela do painel e o upload de imagens fica desligado. **Isso apaga todos os
dados** — nunca ligue numa pizzaria de verdade.

---

## Estrutura

```
index.php, cardapio.php, carrinho.php   páginas do cliente
acompanhar.php, historico.php           acompanhamento e "meus pedidos"
cardapio-mesa.php                       pedido pela mesa (QR Code)
api/                                    endpoints JSON (pedido, status, cupom...)
admin/                                  painel da pizzaria
includes/                               conexão, configuração, CSRF, demo
banco/                                  schema.sql e dados da demo
```

Front-end com Tailwind via CDN e JavaScript sem framework.

---

Feito por [Matheus Morais](https://matheusmorais.com).
