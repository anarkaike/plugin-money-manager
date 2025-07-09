[Voltar ao Índice Principal](../index.md)

### Navegação Rápida

- [Accounts](./accounts.md)
- [Addons](./addons.md)
- [Categories](./categories.md)
- [Currencies](./currencies.md)
- [Parties](./parties.md)
- [Quotes](./quotes.md)
- [Reports](./reports.md)
- [Transactions](./transactions.md)
- [Woocommerce](./woocommerce.md)

# Documentação da API: App

Este documento detalha os endpoints da API relacionados à aplicação geral, como o carregamento de dados iniciais e o salvamento de configurações do usuário.

**Controller:** `MoneyManager\Controllers\App_Controller`

### Endpoints Disponíveis

- [GET /app/load](#get-appload)
- [POST /app/save-meta](#post-appsave-meta)

---

## GET /app/load

Este é um endpoint agregador, projetado para ser chamado no carregamento inicial da aplicação. Ele busca todos os dados essenciais de uma só vez para popular a interface, minimizando o número de requisições HTTP.

- **Método HTTP:** `GET`
- **Autenticação:** Requerida.

### Parâmetros

Este endpoint não aceita parâmetros de entrada.

### Fluxo de Execução

1.  O método `load_app` é acionado.
2.  Ele executa, em sequência, as buscas por:
    - Todas as Contas (`Account::rows()`)
    - Todas as Categorias (`Category::rows()`)
    - Todas as Moedas (`Currency::rows()`)
    - Todas as Favorecidos (`Party::rows()`)
    - As cotações do dia atual (`Quote_Manager::values()`)
    - As configurações do WooCommerce (`WooCommerce_Manager::settings()`)
3.  Todos esses dados são compilados em um único objeto de resposta.

### Estrutura da Resposta

A resposta é um objeto JSON contido em uma chave `result`. Este objeto possui as seguintes chaves:

- `accounts`: Um array com todos os objetos de conta.
- `categories`: Um array com todos os objetos de categoria.
- `currencies`: Um array com todos os objetos de moeda.
- `parties`: Um array com todos os objetos de favorecido.
- `quotes`: Um objeto ou array com as cotações do dia.
- `woocommerce`: Um objeto com as configurações relevantes do WooCommerce.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/app/load' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

*A resposta completa é muito longa. O exemplo a seguir é uma representação abreviada da estrutura.*
```json
{
    "result": {
        "accounts": [
            {"id": 1, "title": "Conta Corrente", ...}
        ],
        "categories": [
            {"id": 1, "title": "Alimentação", ...}
        ],
        "currencies": [
            {"id": 1, "code": "BRL", "is_base": true, ...}
        ],
        "parties": [
            {"id": 1, "title": "Supermercado", ...}
        ],
        "quotes": {
            "USD": 5.45,
            "EUR": 5.82
        },
        "woocommerce": {
            "enabled": true,
            "order_statuses": ["wc-processing", "wc-completed"]
        }
    }
}
```

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para carregar todos os dados essenciais da aplicação de uma só vez. O agente deve usar este endpoint no carregamento inicial da aplicação ou quando precisar de um conjunto completo de dados (contas, categorias, moedas, favorecidos, cotações, configurações do WooCommerce) para exibir ou processar.

### Configuração do Body/Query String/Header

Este endpoint não requer um corpo de requisição (body), parâmetros de query string ou headers adicionais.

---

## POST /app/save-meta

Salva um bloco de dados (metadados) associado ao usuário atualmente logado. É ideal para armazenar configurações de interface e preferências do usuário.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "meta": {
    "last_page_visited": "/reports/income-vs-expense",
    "dark_mode": true,
    "transactions_per_page": 50
  }
}
```

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `meta` | Object/Array | Sim | Um objeto ou array JSON contendo os dados a serem salvos. A estrutura interna é flexível e definida pelo front-end. |

### Fluxo de Execução

1.  O método `save_meta` extrai o objeto `meta` da requisição.
2.  Ele utiliza a função `update_user_meta()` do WordPress para salvar os dados.
3.  Os dados são salvos na tabela `wp_usermeta`, associados ao ID do usuário logado e à chave `money_manager`.
4.  Retorna `{"result": "ok"}`.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/app/save-meta' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "meta": {
        "show_balance_chart": false,
        "default_report_range": "last_month"
    }
}'
```

### Resposta de Sucesso (200 OK)

```json
{
    "result": "ok"
}
```

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para salvar metadados associados ao usuário logado, como configurações de interface e preferências. O agente deve usar este endpoint quando o usuário realizar uma ação que altere suas preferências ou configurações na aplicação.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "meta": {{ $fromAI('meta', `Um objeto ou array JSON contendo os dados a serem salvos. A estrutura interna é flexível e definida pelo front-end.`, 'json') }}
}
```