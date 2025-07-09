[Voltar ao Índice Principal](../index.md)

### Navegação Rápida

- [Accounts](./accounts.md)
- [Addons](./addons.md)
- [App](./app.md)
- [Categories](./categories.md)
- [Currencies](./currencies.md)
- [Parties](./parties.md)
- [Reports](./reports.md)
- [Transactions](./transactions.md)
- [Woocommerce](./woocommerce.md)

# Documentação da API: Quotes

Este documento detalha os endpoints da API para o gerenciamento de **Cotações (Quotes)** de moedas. Estas rotas são responsáveis por buscar, armazenar e servir dados de taxas de câmbio, utilizando a API do **Yahoo Finance** como fonte de dados externa.

**Controller:** `MoneyManager\Controllers\Quotes_Controller`

**Manager:** `MoneyManager\Managers\Quote_Manager`

**Modelo Relacionado:** `MoneyManager\Models\Quote`

### Endpoints Disponíveis

- [GET /quotes/load](#get-quotesload)
- [GET /quotes/list](#get-quoteslist)
- [POST /quotes/refresh](#post-quotesrefresh)
- [POST /quotes/fetch-history](#post-quotesfetch-history)
- [POST /quotes/clear-history](#post-quotesclear-history)

---

## GET /quotes/load

Busca a cotação mais recente para todas as moedas em uma data específica (ou na data atual, por padrão).

- **Método HTTP:** `GET`
- **Autenticação:** Requerida.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `date` | String | Não | A data para a qual buscar as cotações (formato YYYY-MM-DD). Padrão: data atual. |

### Fluxo de Execução

1.  O método `load_quotes` chama `Quote_Manager::values()` com a data fornecida.
2.  Para cada moeda cadastrada, o manager busca no banco de dados a cotação mais recente em ou antes da data especificada.
3.  Se nenhuma cotação histórica for encontrada para uma moeda, seu valor `default_quote` (definido na tela de Moedas) é usado.
4.  Retorna um objeto onde as chaves são os códigos das moedas e os valores são suas cotações.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/quotes/load?date=2024-07-01' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": {
        "BRL": 1,
        "USD": 5.45,
        "EUR": 5.82
    }
}
```

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para buscar a cotação mais recente para todas as moedas em uma data específica (ou na data atual, por padrão). O agente deve usar este endpoint quando precisar obter as taxas de câmbio para conversões ou exibição.

### Configuração do Body/Query String/Header

Este endpoint aceita um parâmetro de query string:

```json
{
  "date": "{{ $fromAI('date', `A data para a qual buscar as cotações (formato YYYY-MM-DD). Atribua null para a data atual.`, 'string') }}"
}
```

---

## GET /quotes/list

Lista todo o histórico de cotações armazenado no banco de dados para uma única moeda.

- **Método HTTP:** `GET`
- **Autenticação:** Requerida.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `currency` | String | Sim | O código da moeda (ex: "USD") para a qual o histórico será listado. |

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/quotes/list?currency=USD' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": [
        {
            "id": 100,
            "currency": "USD",
            "date": "2024-07-08",
            "value": 5.45
        },
        {
            "id": 99,
            "currency": "USD",
            "date": "2024-07-07",
            "value": 5.42
        }
    ]
}
```

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para listar todo o histórico de cotações armazenado no banco de dados para uma única moeda. O agente deve usar este endpoint quando o usuário solicitar o histórico de cotações de uma moeda específica.

### Configuração do Body/Query String/Header

Este endpoint aceita um parâmetro de query string:

```json
{
  "currency": "{{ $fromAI('currency', `O código da moeda (ex: \"USD\") para a qual o histórico será listado.`, 'string') }}"
}
```

---

## POST /quotes/refresh

Dispara uma atualização da cotação do dia atual para todas as moedas (exceto a base), buscando os dados na API do Yahoo Finance.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Parâmetros

Este endpoint não aceita parâmetros.

### Fluxo de Execução

1.  O método `refresh_quotes` chama `Quote_Manager::refresh()`.
2.  O manager itera sobre as moedas e, para cada uma, faz uma requisição à API do Yahoo Finance para obter o preço de mercado atual.
3.  O valor obtido é salvo no banco de dados com a data de hoje.

**Aviso:** Esta operação depende de um serviço externo e pode falhar ou demorar dependendo da conectividade e da disponibilidade da API do Yahoo Finance.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/quotes/refresh' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Instruções para o Agente de IA (n8n)

Este endpoint dispara uma atualização da cotação do dia atual para todas as moedas (exceto a base), buscando os dados na API do Yahoo Finance. O agente deve usar este endpoint quando o usuário solicitar a atualização das cotações.

### Configuração do Body/Query String/Header

Este endpoint não requer um corpo de requisição (body), parâmetros de query string ou headers adicionais.

---

## POST /quotes/fetch-history

Busca e importa um grande volume de dados históricos de cotações da API do Yahoo Finance para preencher o banco de dados local.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### !! AVISO DE PERFORMANCE !!

Esta é uma operação **extremamente lenta e intensiva**. Ela pode realizar dezenas de requisições a uma API externa e centenas de escritas no banco de dados. Use com moderação e apenas quando necessário para popular o histórico.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `range` | String | Sim | O período de tempo a ser buscado. Valores permitidos: `1d`, `5d`, `1mo`, `3mo`, `6mo`, `1y`, `2y`, `5y`, `10y`, `ytd`, `max`. |
| `currencies` | Array | Não | Uma lista de códigos de moeda para buscar. Se omitido, busca para todas as moedas. |

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/quotes/fetch-history' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "range": "3mo",
    "currencies": ["USD", "EUR"]
}'
```

### Instruções para o Agente de IA (n8n)

Este endpoint busca e importa um grande volume de dados históricos de cotações da API do Yahoo Finance. O agente deve usar este endpoint quando o usuário solicitar o preenchimento do histórico de cotações para um período específico e/ou moedas selecionadas.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "range": "{{ $fromAI('range', `O período de tempo a ser buscado. Valores permitidos: \"1d\", \"5d\", \"1mo\", \"3mo\", \"6mo\", \"1y\", \"2y\", \"5y\", \"10y\", \"ytd\", \"max\".`, 'string') }}",
  "currencies": {{ $fromAI('currencies', `Uma lista de códigos de moeda para buscar. Atribua null se for para todas as moedas.`, 'array') }}
}
```

---

## POST /quotes/clear-history

Exclui permanentemente os registros do histórico de cotações do banco de dados.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### !! AVISO: AÇÃO DESTRUTIVA !!

Esta operação apaga dados permanentemente. Se nenhum filtro de moeda for fornecido, **TODO o histórico de cotações será apagado**.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `currencies` | Array | Não | Uma lista de códigos de moeda cujo histórico deve ser apagado. **Se omitido, apaga o histórico de TODAS as moedas.** |

### Exemplo 1: Apagar histórico de moedas específicas

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/quotes/clear-history' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "currencies": ["USD"]
}'
```

### Exemplo 2: Apagar TODO o histórico

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/quotes/clear-history' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{}'
```

### Instruções para o Agente de IA (n8n)

Este endpoint exclui permanentemente os registros do histórico de cotações do banco de dados. O agente deve usar este endpoint com EXTREMA CAUTELA, pois pode apagar todo o histórico de cotações se nenhum filtro de moeda for fornecido.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "currencies": {{ $fromAI('currencies', `Uma lista de códigos de moeda cujo histórico deve ser apagado. Atribua null para apagar o histórico de TODAS as moedas.`, 'array') }}
}
```