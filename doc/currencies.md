[Voltar ao Índice Principal](../index.md)

### Navegação Rápida

- [Accounts](./accounts.md)
- [Addons](./addons.md)
- [App](./app.md)
- [Categories](./categories.md)
- [Parties](./parties.md)
- [Quotes](./quotes.md)
- [Reports](./reports.md)
- [Transactions](./transactions.md)
- [Woocommerce](./woocommerce.md)

# Documentação da API: Currencies

Este documento detalha todos os endpoints da API relacionados ao gerenciamento de **Moedas (Currencies)**. O sistema opera com o conceito de uma única **Moeda Base**, que serve como pivô para as cotações de todas as outras moedas.

**Controller:** `MoneyManager\Controllers\Currencies_Controller`

**Modelo Relacionado:** `MoneyManager\Models\Currency`

### Endpoints Disponíveis

- [GET /currencies/list](#get-currencieslist)
- [POST /currencies/save](#post-currenciessave)
- [POST /currencies/remove](#post-currenciesremove)

---

## GET /currencies/list

Este endpoint recupera uma lista de todas as moedas cadastradas no sistema.

- **Método HTTP:** `GET`
- **Autenticação:** Requer autenticação padrão do WordPress.

### Parâmetros

Este endpoint não aceita parâmetros de entrada.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/currencies/list' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": [
        {
            "id": 1,
            "code": "BRL",
            "color": null,
            "is_base": true,
            "default_quote": 1
        },
        {
            "id": 2,
            "code": "USD",
            "color": null,
            "is_base": false,
            "default_quote": 5.40
        },
        {
            "id": 3,
            "code": "EUR",
            "color": null,
            "is_base": false,
            "default_quote": 5.80
        }
    ]
}
```
*No exemplo, o Real (BRL) é a moeda base (`is_base: true`).*

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para listar todas as moedas cadastradas no sistema. O agente deve usar este endpoint quando o usuário solicitar uma lista de moedas ou quando precisar de informações sobre as moedas existentes para outras operações.

### Configuração do Body/Query String/Header

Este endpoint não requer um corpo de requisição (body), parâmetros de query string ou headers adicionais.

---

## POST /currencies/save

Cria ou atualiza uma moeda. Esta rota contém lógicas de negócio críticas relacionadas à moeda base.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "item": {
    "id": 2, // Opcional, para atualização
    "code": "USD",
    "is_base": false,
    "default_quote": 5.45
  }
}
```

### Parâmetros (dentro de `item`)

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `id` | Integer | Não | ID da moeda para atualização. Se omitido, cria uma nova. |
| `code` | String | Sim | O código de 3 letras da moeda (ISO 4217). Ex: "USD". |
| `is_base` | Boolean | Sim | Define se esta é a moeda base do sistema. Só pode haver uma. |
| `default_quote` | Float | Sim | A cotação padrão da moeda em relação à moeda base. Para a própria moeda base, este valor deve ser `1`. |
| `color` | String | Não | Um código de cor hexadecimal para a interface. |

### Regras de Negócio e Fluxo de Execução

- **Criação:**
    - O `code` da moeda é verificado para evitar duplicatas. Retorna erro `DUPLICATE_RECORD` se já existir.
- **Atualização:**
    - **Não é permitido alterar o `code`** de uma moeda existente.
    - **Não é permitido alterar `is_base` de `true` para `false`**. A única forma de "rebaixar" uma moeda base é promovendo outra para ser a nova base.
- **Lógica da Moeda Base (Ação Destrutiva):**
    - Se uma moeda é salva com `is_base: true` (seja na criação ou atualização):
        1.  O sistema define uma flag `changing_base_currency`.
        2.  **Todo o histórico de cotações é apagado** (`Quote_Manager::clear_history()`). Esta ação é irreversível.
        3.  Uma consulta SQL direta define `is_base = 0` para **todas as outras moedas** no banco de dados.
        4.  A `default_quote` da nova moeda base é forçada para `1`.
        5.  A nova moeda base é salva.

### Exemplo de Requisição (cURL para mudar a moeda base)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/currencies/save' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "item": {
        "id": 2, 
        "is_base": true
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

Este endpoint é utilizado para criar ou atualizar uma moeda. O agente deve usar este endpoint quando o usuário desejar adicionar uma nova moeda ou modificar os detalhes de uma moeda existente. Atenção especial deve ser dada ao campo `is_base`, pois ele define a moeda base do sistema e sua alteração pode ter impactos significativos, como o exclusão do histórico de cotações.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "item": {
    "id": {{ $fromAI('id', `ID da moeda para atualização. Atribua null se for uma inserção.`, 'number') }},
    "code": "{{ $fromAI('code', `O código de 3 letras da moeda (ISO 4217). Ex: \"USD\".`, 'string') }}",
    "is_base": {{ $fromAI('is_base', `Define se esta é a moeda base do sistema. Só pode haver uma.`, 'boolean') }},
    "default_quote": {{ $fromAI('default_quote', `A cotação padrão da moeda em relação à moeda base. Para a própria moeda base, este valor deve ser 1.`, 'number') }},
    "color": "{{ $fromAI('color', `Um código de cor hexadecimal para a interface.`, 'string') }}"
  }
}
```

---

## POST /currencies/remove

Exclui permanentemente uma moeda.

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
| `id` | Integer | Sim | O ID da moeda a ser excluída. |

### !! AVISOS CRÍTICOS !!

A implementação atual desta rota é **extremamente perigosa** e deve ser usada com o máximo de cuidado:

1.  **Exclusão da Moeda Base:** O código **não impede a exclusão da moeda base**. Fazer isso deixará o sistema em um estado inconsistente e provavelmente quebrará todas as funcionalidades de conversão e relatórios.
2.  **Exclusão de Moeda em Uso:** O código **não verifica se a moeda está sendo utilizada** por alguma conta (`Account`). Excluir uma moeda associada a uma ou mais contas deixará esses registros órfãos, causando erros e inconsistências de dados.

**Recomendação:** **NÃO use este endpoint** a menos que você tenha certeza absoluta de que a moeda não é a base e não está em uso por nenhuma conta. A forma mais segura de remover uma moeda é reatribuir todas as contas que a utilizam para outra moeda antes da exclusão.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/currencies/remove' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "id": 3
}'
```

### Instruções para o Agente de IA (n8n)

Este endpoint é utilizado para excluir permanentemente uma moeda. O agente deve usar este endpoint com EXTREMA CAUTELA, pois a exclusão da moeda base ou de uma moeda em uso por contas pode levar a inconsistências graves no sistema. Recomenda-se reatribuir contas e garantir que a moeda não seja a base antes de usar este endpoint.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "id": {{ $fromAI('id', `O ID da moeda a ser excluída.`, 'number') }}
}
```
