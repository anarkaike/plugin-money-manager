[Voltar ao Índice Principal](../index.md)

### Navegação Rápida

- [Accounts](./accounts.md)
- [Addons](./addons.md)
- [App](./app.md)
- [Categories](./categories.md)
- [Currencies](./currencies.md)
- [Parties](./parties.md)
- [Quotes](./quotes.md)
- [Transactions](./transactions.md)
- [Woocommerce](./woocommerce.md)

# Documentação da API: Reports

Este documento detalha os endpoints da API para a geração de **Relatórios (Reports)**. Estes endpoints são somente para leitura e executam consultas de agregação de dados complexas para fornecer insights financeiros.

**Controller:** `MoneyManager\Controllers\Reports_Controller`

**Aviso de Performance:** As consultas executadas por este controller são inerentemente pesadas, pois envolvem a junção de múltiplas tabelas e a conversão de moeda para cada transação individualmente. Em sites com um grande volume de transações, estas requisições podem demorar para serem processadas.

### Endpoints Disponíveis

- [GET /reports/cash-flow](#get-reportscash-flow)
- [GET /reports/income-expenses](#get-reportsincome-expenses)

---

## GET /reports/cash-flow

Este endpoint gera um relatório de fluxo de caixa, totalizando receitas e despesas para um determinado período, com várias opções de filtro e agrupamento.

- **Método HTTP:** `GET`
- **Autenticação:** Requerida.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `currency` | String | Sim | O código da moeda (ex: "BRL") na qual o relatório será apresentado. Todos os valores serão convertidos para esta moeda. |
| `range` | String | Sim | O período de tempo para o relatório. Valores: `recent_12_months`, `this_year`, `last_year`, ou um padrão `X_years_ago` (ex: `0_years_ago` para o ano atual, `1_years_ago` para o ano passado). |
| `group_by` | String | Sim | Como agrupar os dados. Valores: `month` (agrupa por ano-mês), `party` (agrupa por favorecido). |
| `account_id` | Integer | Não | Filtra o relatório para incluir apenas transações de uma conta específica. |
| `include_transfers` | Boolean | Não | Se `true`, as transferências entre contas são consideradas no cálculo de receitas e despesas. Requer que `account_id` seja fornecido. |

### Estrutura da Resposta

A resposta contém um objeto `result` com duas chaves: `income` e `expenses`. Cada uma é um array de objetos agregados.

- **Se `group_by: 'month'`**: Cada objeto no array terá `id` (no formato `YYYY-M`), `category_id` e `amount`.
- **Se `group_by: 'party'`**: Cada objeto no array terá `party_id`, `category_id` e `amount`.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/reports/cash-flow?currency=BRL&range=this_year&group_by=month' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

*Agrupado por mês (`group_by: 'month'`)*
```json
{
    "result": {
        "income": [
            {
                "id": "2024-7",
                "category_id": 10,
                "amount": 5000.00
            }
        ],
        "expenses": [
            {
                "id": "2024-7",
                "category_id": 1,
                "amount": 1250.50
            },
            { 
                "id": "2024-7",
                "category_id": 3,
                "amount": 300.00
            },
            {
                "id": "2024-6",
                "category_id": 1,
                "amount": 1100.00
            }
        ]
    }
}
```

### Instruções para o Agente de IA (n8n)

Este endpoint gera um relatório de fluxo de caixa, totalizando receitas e despesas para um determinado período, com várias opções de filtro e agrupamento. O agente deve usar este endpoint quando o usuário solicitar um relatório de fluxo de caixa, especificando a moeda, o período, o agrupamento e, opcionalmente, o ID da conta e se deve incluir transferências.

### Configuração do Body/Query String/Header

Este endpoint aceita os seguintes parâmetros de query string:

```json
{
  "currency": "{{ $fromAI('currency', `O código da moeda (ex: \"BRL\") na qual o relatório será apresentado. Todos os valores serão convertidos para esta moeda.`, 'string') }}",
  "range": "{{ $fromAI('range', `O período de tempo para o relatório. Valores: \"recent_12_months\", \"this_year\", \"last_year\", ou um padrão \"X_years_ago\" (ex: \"0_years_ago\" para o ano atual, \"1_years_ago\" para o ano passado).`, 'string') }}",
  "group_by": "{{ $fromAI('group_by', `Como agrupar os dados. Valores: \"month\" (agrupa por ano-mês), \"party\" (agrupa por favorecido).`, 'string') }}",
  "account_id": {{ $fromAI('account_id', `Filtra o relatório para incluir apenas transações de uma conta específica. Atribua null se não houver filtro.`, 'number') }},
  "include_transfers": {{ $fromAI('include_transfers', `Se true, as transferências entre contas são consideradas no cálculo de receitas e despesas. Requer que account_id seja fornecido.`, 'boolean') }}
}
```

---

## GET /reports/income-expenses

Este endpoint gera múltiplos relatórios de fluxo de caixa para diferentes períodos, permitindo a comparação entre eles (ex: comparar despesas deste ano com as do ano passado).

- **Método HTTP:** `GET`
- **Autenticação:** Requerida.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `currency` | String | Sim | O código da moeda para os relatórios. |
| `ranges` | Array | Sim | Um array de strings de períodos a serem comparados. Os valores são os mesmos do parâmetro `range` do endpoint `cash-flow`. |
| `group_by` | String | Sim | Como agrupar os dados em cada relatório. Valores: `month`, `party`. |

### Fluxo de Execução

1.  O método `income_expenses` recebe um array de `ranges`.
2.  Ele itera sobre este array.
3.  Para cada `range` no array, ele chama a lógica principal `get_report_data` (a mesma usada por `/reports/cash-flow`).
4.  Cada resultado é adicionado a um array de resposta, com uma chave `range` adicional para identificar a qual período aquele bloco de dados pertence.

### Exemplo de Requisição (cURL)

*Comparando o ano atual (`0_years_ago`) com o ano anterior (`1_years_ago`)*
```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/reports/income-expenses?currency=BRL&group_by=party&ranges[]=0_years_ago&ranges[]=1_years_ago' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": [
        {
            "income": [
                {"party_id": 10, "category_id": 1, "amount": 60000.00}
            ],
            "expenses": [
                {"party_id": 22, "category_id": 5, "amount": 15000.00}
            ],
            "range": "0_years_ago"
        },
        {
            "income": [
                {"party_id": 10, "category_id": 1, "amount": 58000.00}
            ],
            "expenses": [
                {"party_id": 22, "category_id": 5, "amount": 14500.00}
            ],
            "range": "1_years_ago"
        }
    ]
}
```

### Instruções para o Agente de IA (n8n)

Este endpoint gera múltiplos relatórios de fluxo de caixa para diferentes períodos, permitindo a comparação entre eles. O agente deve usar este endpoint quando o usuário solicitar a comparação de receitas e despesas entre diferentes períodos, especificando a moeda, os períodos a serem comparados e o agrupamento.

### Configuração do Body/Query String/Header

Este endpoint aceita os seguintes parâmetros de query string:

```json
{
  "currency": "{{ $fromAI('currency', `O código da moeda para os relatórios.`, 'string') }}",
  "ranges": {{ $fromAI('ranges', `Um array de strings de períodos a serem comparados. Os valores são os mesmos do parâmetro \"range\" do endpoint cash-flow.`, 'array') }},
  "group_by": "{{ $fromAI('group_by', `Como agrupar os dados em cada relatório. Valores: \"month\", \"party\".`, 'string') }}"
}
```