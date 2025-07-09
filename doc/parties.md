[Voltar ao Índice Principal](../index.md)

### Navegação Rápida

- [Accounts](./accounts.md)
- [Addons](./addons.md)
- [App](./app.md)
- [Categories](./categories.md)
- [Currencies](./currencies.md)
- [Quotes](./quotes.md)
- [Reports](./reports.md)
- [Transactions](./transactions.md)
- [Woocommerce](./woocommerce.md)

# Documentação da API: Parties

Este documento detalha todos os endpoints da API relacionados ao gerenciamento de **Favorecidos (Parties)**. Um "Party" representa um terceiro envolvido em uma transação, como uma loja, uma pessoa ou uma empresa.

**Controller:** `MoneyManager\Controllers\Parties_Controller`

**Modelo Relacionado:** `MoneyManager\Models\Party`

### Endpoints Disponíveis

- [GET /parties/list](#get-partieslist)
- [POST /parties/save](#post-partiessave)
- [POST /parties/remove](#post-partiesremove)

---

## GET /parties/list

Este endpoint recupera uma lista de todos os favorecidos cadastrados, ordenados alfabeticamente pelo título.

- **Método HTTP:** `GET`
- **Autenticação:** Requer autenticação padrão do WordPress.

### Parâmetros

Este endpoint não aceita parâmetros de entrada.

### Fluxo de Execução

1.  O método `list_parties` chama `Party::rows()`.
2.  A consulta ao banco de dados é modificada para ordenar os resultados por `title`.
3.  A lista de objetos de favorecidos é retornada dentro de uma chave `result`.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/parties/list' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": [
        {
            "id": 1,
            "title": "Supermercado Pague Menos",
            "default_category_id": 2,
            "color": "#FF5733"
        },
        {
            "id": 2,
            "title": "Posto Shell",
            "default_category_id": 15,
            "color": "#FFC300"
        },
        {
            "id": 3,
            "title": "Netflix",
            "default_category_id": 8,
            "color": "#E50914"
        }
    ]
}
```
*No exemplo, "Posto Shell" tem a categoria com ID 15 (provavelmente "Combustível") como padrão.*

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para listar todos os favorecidos cadastrados. O agente deve usar este endpoint quando o usuário solicitar uma lista de favorecidos ou quando precisar de informações sobre os favorecidos existentes para outras operações.

### Configuração do Body/Query String/Header

Este endpoint não requer um corpo de requisição (body), parâmetros de query string ou headers adicionais.

---

## POST /parties/save

Cria ou atualiza um favorecido.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "item": {
    "id": 2, // Opcional, para atualização
    "title": "Posto de Gasolina Shell",
    "default_category_id": 15,
    "color": "#FFC300"
  }
}
```

### Parâmetros (dentro de `item`)

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `id` | Integer | Não | ID do favorecido para atualização. Se omitido, cria um novo. |
| `title` | String | Sim | O nome do favorecido. |
| `default_category_id` | Integer | Não | O ID da categoria a ser sugerida por padrão ao selecionar este favorecido em uma nova transação. |
| `color` | String | Não | Um código de cor hexadecimal (ex: `#FFC300`) para a interface. |

### Fluxo de Execução

1.  O método `save_party` extrai o objeto `item`.
2.  Verifica a presença de `item[id]` para decidir entre criar um novo `Party` ou encontrar (`find`) e atualizar um existente.
3.  Os dados são atribuídos de forma segura usando o método `fill()`.
4.  O favorecido é salvo no banco de dados (`INSERT` ou `UPDATE`).
5.  Retorna `{"result": "ok"}`.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/parties/save' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "item": {
        "title": "Amazon Compras",
        "default_category_id": 21,
        "color": "#FF9900"
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

Este endpoint é utilizado para criar ou atualizar um favorecido. O agente deve usar este endpoint quando o usuário desejar adicionar uma nova favorecido ou modificar os detalhes de um favorecido existente.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "item": {
    "id": {{ $fromAI('id', `ID do favorecido para atualização. Atribua null se for uma inserção.`, 'number') }},
    "title": "{{ $fromAI('title', `O nome do favorecido.`, 'string') }}",
    "default_category_id": {{ $fromAI('default_category_id', `O ID da categoria a ser sugerida por padrão ao selecionar este favorecido em uma nova transação.`, 'number') }},
    "color": "{{ $fromAI('color', `Um código de cor hexadecimal (ex: #FFC300) para a interface.`, 'string') }}"
  }
}
```

---

## POST /parties/remove

Exclui permanentemente um favorecido.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "id": 3
}
```

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `id` | Integer | Sim | O ID do favorecido a ser excluído. |

### Fluxo de Execução

1.  O método `remove_party` extrai o `id`.
2.  Chama `Party::destroy($id)`, que executa uma consulta `DELETE` no banco de dados.
3.  Retorna `{"result": "ok"}`.

### Consideração Importante: Transações Órfãs

A implementação atual **não trata dados dependentes** ao excluir um favorecido. Transações associadas a este favorecido **não serão atualizadas**. O campo `party_id` delas continuará apontando para um ID que não existe mais, tornando-as órfãs e podendo causar problemas em filtros e relatórios.

**Recomendação:** Antes de excluir um favorecido, reatribua manualmente suas transações através da interface do plugin ou de outras chamadas de API.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/parties/remove' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "id": 3
}'
```

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para excluir permanentemente um favorecido. O agente deve usar este endpoint com cautela, pois transações associadas a este favorecido não serão atualizadas e podem se tornar órfãs. Recomenda-se reatribuir transações antes de usar este endpoint.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "id": {{ $fromAI('id', `O ID do favorecido a ser excluído.`, 'number') }}
}
```