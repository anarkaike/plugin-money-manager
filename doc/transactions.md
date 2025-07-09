
# Documentação da API: Transactions

Este documento detalha todos os endpoints da API relacionados ao gerenciamento de **Transações (Transactions)**.

**Controller:** `MoneyManager\Controllers\Transactions_Controller`

**Modelo Relacionado:** `MoneyManager\Models\Transaction`, `MoneyManager\Models\File`

### Endpoints Disponíveis

- [POST /transactions/list](#post-transactionslist)
- [POST /transactions/save](#post-transactionssave)
- [POST /transactions/remove](#post-transactionsremove)
- [POST /transactions/import](#post-transactionsimport)

---

## POST /transactions/list

Este endpoint recupera uma lista de transações com base em um conjunto robusto de filtros. É uma das rotas mais complexas do plugin.

- **Método HTTP:** `POST` (utiliza POST para acomodar o grande número de filtros no corpo da requisição).
- **Autenticação:** Requer autenticação padrão do WordPress.

### Parâmetros de Filtragem

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `account_id` | Integer | Não | Filtra transações para uma conta específica. Se omitido, busca em todas as contas. |
| `range` | String | Sim | Um atalho para filtros de data comuns. Se `advanced_filter` for usado, este é ignorado. Valores: `today`, `this_month`, `recent_30_days`, `recent_90_days`, `last_month`, `recent_3_months`, `recent_12_months`, `this_year`, `last_year`, `advanced_filter`. |
| `criteria` | Array | Não | Usado com `range: 'advanced_filter'`. Indica quais filtros avançados estão ativos. Valores: `date_range`, `party`, `category`. |
| `date_from` | String | Não | Data de início (YYYY-MM-DD) para o filtro `date_range`. Requer `criteria: ['date_range']`. |
| `date_to` | String | Não | Data de fim (YYYY-MM-DD) para o filtro `date_range`. Requer `criteria: ['date_range']`. |
| `party_ids` | Array | Não | Uma lista de IDs de favorecidos. Requer `criteria: ['party']`. |
| `category_ids` | Array | Não | Uma lista de IDs de categorias. Requer `criteria: ['category']`. |

### Fluxo de Execução

1.  O método `list_transactions` constrói uma consulta SQL complexa com base nos parâmetros de filtro.
2.  Ele junta a tabela de arquivos (`wp_money_manager_files`) para incluir anexos.
3.  Executa a consulta para obter os dados brutos.
4.  Processa os resultados em PHP para:
    a. Calcular um campo `balance` de execução para cada transação (se `account_id` for fornecido).
    b. Agrupar os dados de arquivos em um array aninhado `files` dentro de cada objeto de transação.
5.  Retorna a lista processada de transações.

### Exemplo de Requisição (Filtro Simples)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/transactions/list' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "account_id": 1,
    "range": "this_month"
}'
```

### Exemplo de Requisição (Filtro Avançado)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/transactions/list' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "range": "advanced_filter",
    "criteria": ["date_range", "category"],
    "date_from": "2024-01-01",
    "date_to": "2024-06-30",
    "category_ids": [5, 12]
}'
```

### Exemplo de Resposta de Sucesso

```json
{
    "result": [
        {
            "id": 101,
            "account_id": 1,
            "to_account_id": null,
            "party_id": 22,
            "category_id": 5,
            "date": "2024-07-15",
            "type": "expense",
            "amount": "150.00",
            "to_amount": null,
            "notes": "Supermercado",
            "files": [
                {
                    "id": 3,
                    "attachment_id": 150,
                    "filename": "nota_fiscal.pdf",
                    "description": "",
                    "url": "https://.../nota_fiscal.pdf"
                }
            ],
            "split": [],
            "balance": 2350.00
        }
    ]
}
```

---

## POST /transactions/save

Cria ou atualiza uma transação, incluindo a gestão de arquivos anexos.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "item": {
    "id": 101, // Opcional, para atualização
    "account_id": 1,
    "type": "expense",
    "amount": 150.00,
    // ... outros campos
    "files": [
        {"id": 3}, // Manter arquivo existente
        {"attachment_id": 210, "filename": "novo.jpg"} // Novo arquivo
    ]
  }
}
```

### Parâmetros (dentro de `item`)

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `id` | Integer | Não | ID da transação para atualização. Se omitido, cria uma nova. |
| `account_id` | Integer | Sim | ID da conta principal da transação. |
| `type` | String | Sim | Tipo da transação. Valores: `income`, `expense`, `transfer`. |
| `date` | String | Sim | Data da transação (YYYY-MM-DD). |
| `amount` | Float | Sim | O valor da transação. Sempre positivo. |
| `category_id` | Integer | Não | ID da categoria. |
| `party_id` | Integer | Não | ID do favorecido/loja. |
| `notes` | String | Não | Anotações. |
| `to_account_id` | Integer | Sim (se `type`=`transfer`) | ID da conta de destino. |
| `to_amount` | Float | Sim (se `type`=`transfer`) | Valor que chega na conta de destino (para conversão de moeda). |
| `files` | Array | Não | Lista de objetos de arquivo. Para manter um arquivo, envie seu `id`. Para adicionar, envie os dados do novo arquivo. Arquivos não incluídos na lista serão desvinculados. |

### Fluxo de Execução

1.  Diferencia entre criar e atualizar com base na presença de `item[id]`.
2.  Salva os dados da transação na tabela `wp_money_manager_transactions`.
3.  **Gerencia Arquivos:**
    a. Itera sobre o array `item[files]`.
    b. Se um objeto de arquivo tem `id`, ele é mantido. Se não, um novo registro de arquivo é criado.
    c. Arquivos que estavam associados à transação mas não estão no array enviado são excluídos (`File::destroy_except`).
4.  **Atualiza Saldos:** Chama `Account_Manager::refresh_balance()` para todas as contas afetadas (conta de origem, de destino e, em caso de edição, as contas antigas também).
5.  Retorna `{"result": "ok"}`.

---

## POST /transactions/remove

Exclui uma ou mais transações permanentemente.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "ids": [101, 102, 105]
}
```

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `ids` | Array | Sim | Uma lista de IDs de transações a serem excluídas. |

### Fluxo de Execução

1.  Recebe a lista de `ids`.
2.  Busca no banco todas as contas (`account_id`, `to_account_id`) que serão afetadas pela exclusão.
3.  Exclui as transações da tabela `wp_money_manager_transactions`.
4.  Chama `Account_Manager::refresh_balance()` para cada uma das contas afetadas para recalcular seus saldos.
5.  Retorna `{"result": "ok"}`.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/transactions/remove' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "ids": [101, 102]
}'
```

---

## POST /transactions/import

Realiza a importação em massa de transações simples para uma conta específica.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "account_id": 1,
  "party_id": 22, // Opcional
  "category_id": 5, // Opcional
  "data": [
    {"date": "2024-07-10", "amount": -50.25, "notes": "Lanche"},
    {"date": "2024-07-11", "amount": 1200.00, "notes": "Adiantamento"},
    {"date": "2024-07-12", "amount": -25.00, "notes": "Café"}
  ]
}
```

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `account_id` | Integer | Sim | ID da conta para a qual as transações serão importadas. |
| `party_id` | Integer | Não | ID de um favorecido a ser atribuído a todas as transações importadas. |
| `category_id` | Integer | Não | ID de uma categoria a ser atribuída a todas as transações importadas. |
| `data` | Array | Sim | Uma lista de objetos de transação. Cada objeto deve conter `date`, `amount` e `notes`. |

### Fluxo de Execução

1.  Recebe os IDs de conta/favorecido/categoria e a lista de dados.
2.  Itera sobre cada item no array `data`.
3.  Para cada item, cria um novo objeto `Transaction`.
4.  O `type` da transação é definido como `income` se `amount` for positivo, e `expense` se for negativo. O valor `amount` é armazenado como `abs(amount)`.
5.  Salva a nova transação.
6.  Após o loop, chama `Account_Manager::refresh_balance()` uma única vez na conta de destino.
7.  Retorna `{"result": "ok"}`.
