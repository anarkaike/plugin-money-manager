
# Documentação da API: Accounts

Este documento detalha todos os endpoints da API relacionados ao gerenciamento de **Contas (Accounts)** no plugin Money Manager.

**Controller:** `MoneyManager\Controllers\Accounts_Controller`

**Modelo Relacionado:** `MoneyManager\Models\Account`

### Endpoints Disponíveis

- [GET /accounts/list](#get-accountslis)
- [POST /accounts/save](#post-accountssave)
- [POST /accounts/remove](#post-accountsremove)

---

## GET /accounts/list

Este endpoint recupera uma lista de todas as contas cadastradas no sistema, ordenadas de forma específica para exibição na interface.

- **Método HTTP:** `GET`
- **Autenticação:** Requer autenticação padrão do WordPress.

### Parâmetros

Este endpoint não aceita parâmetros de entrada.

### Fluxo de Execução

1.  O método `list_accounts` é acionado.
2.  Ele chama o método estático `Account::rows()`, que busca todos os registros da tabela `wp_money_manager_accounts`.
3.  Uma função de callback é passada para adicionar uma cláusula `ORDER BY` personalizada à consulta SQL. A ordenação é feita primeiramente pelo campo `type` na seguinte ordem: `checking`, `card`, `cash`, `debt`, `crypto`, e em segundo lugar pelo `title` em ordem alfabética.
4.  A lista de objetos de conta é retornada dentro de uma chave `result`.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/accounts/list' \
--header 'Authorization: Bearer SEU_TOKEN_JWT_OU_NONCE'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": [
        {
            "id": 1,
            "title": "Conta Corrente",
            "type": "checking",
            "currency": "BRL",
            "initial_balance": "1500.00",
            "balance": "2500.00",
            "notes": "Conta principal",
            "color": "#2980b9",
            "created_at": "2024-07-08 10:00:00",
            "updated_at": "2024-07-08 11:00:00"
        },
        {
            "id": 5,
            "title": "Cartão de Crédito",
            "type": "card",
            "currency": "BRL",
            "initial_balance": "0.00",
            "balance": "-500.00",
            "notes": "",
            "color": "#c0392b",
            "created_at": "2024-07-08 10:05:00",
            "updated_at": "2024-07-08 11:05:00"
        }
    ]
}
```

---

## POST /accounts/save

Este endpoint é o ponto central para a **criação e atualização** de contas. A ação (criar ou atualizar) é determinada pela presença do campo `id`.

- **Método HTTP:** `POST`
- **Autenticação:** Requer autenticação padrão do WordPress.

### Estrutura da Requisição

A rota espera um objeto `item` no corpo (body) da requisição.

```json
{
  "item": {
    "id": 1, // Opcional, para atualização
    "title": "Novo Título",
    // ... outros campos
  }
}
```

### Parâmetros Detalhados

| Parâmetro         | Tipo    | Obrigatório? | Descrição                                                                                                                                                                                                                         |
| ----------------- | ------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`              | Integer | Não          | **Para atualização.** O ID da conta a ser atualizada. Se omitido, uma nova conta será criada.                                     |
| `title`           | String  | Sim          | O nome da conta. Ex: "Carteira", "Conta Corrente Banco do Brasil".                                                                                                       |
| `type`            | String  | Sim          | O tipo da conta. Valores permitidos: `checking`, `card`, `cash`, `debt`, `crypto`.                                                                                     |
| `currency`        | String  | Sim          | O código de 3 letras da moeda (ISO 4217). Ex: "BRL", "USD".                                                                                                                                                  |
| `initial_balance` | Float   | Sim          | O saldo inicial da conta. **Importante:** O saldo final (`balance`) é calculado dinamicamente pelo sistema e não pode ser definido aqui.         |
| `notes`           | String  | Não          | Anotações sobre a conta.                                                                                                                  |
| `color`           | String  | Não          | Um código de cor hexadecimal (ex: `#FF5733`) para a interface.                                                                                                               |

### Fluxo de Execução

1.  O método `save_account` extrai o objeto `item`.
2.  Verifica se `item[id]` existe.
3.  **Se `id` existe (Atualização):** Busca a conta, atualiza os campos preenchíveis (`$fillable`) e salva. Retorna erro `RECORD_NOT_FOUND` se o ID não for encontrado.
4.  **Se `id` não existe (Criação):** Cria uma nova instância de `Account` com os dados e salva.
5.  **Passo Crítico:** Após salvar, o método `Account_Manager::refresh_balance()` é chamado para recalcular o campo `balance` da conta, somando o `initial_balance` com todas as transações associadas.
6.  Retorna `{"result": "ok"}`.

### Exemplos de Uso

(Exemplos de cURL e JavaScript para criação e atualização podem ser encontrados no arquivo `route-account-save.md` gerado anteriormente. Eles são idênticos.)

### Resposta de Sucesso (200 OK)

```json
{
    "result": "ok"
}
```

### Respostas de Erro Comuns

- `RECORD_NOT_FOUND`: Tentativa de atualizar um `id` que não existe.
- `rest_not_logged_in` (401): Usuário não autenticado.
- `rest_forbidden` (403): Usuário sem permissão.

---

## POST /accounts/remove

Este endpoint é usado para **excluir permanentemente** uma conta.

- **Método HTTP:** `POST`
- **Autenticação:** Requer autenticação padrão do WordPress.

### Estrutura da Requisição

A rota espera o `id` da conta a ser removida no corpo da requisição.

```json
{
  "id": 15
}
```

### Parâmetros

| Parâmetro | Tipo    | Obrigatório? | Descrição                      |
| --------- | ------- | ------------ | ------------------------------ |
| `id`      | Integer | Sim          | O ID da conta a ser excluída. |

### Fluxo de Execução

1.  O método `remove_account` é acionado.
2.  Ele extrai o `id` do corpo da requisição.
3.  Chama o método estático `Account::destroy($id)`, que executa uma consulta `DELETE FROM wp_money_manager_accounts WHERE id = %d` no banco de dados.
4.  Retorna `{"result": "ok"}`.

### Consideração Importante: Transações Órfãs

A implementação atual **não exclui nem reatribui as transações** associadas à conta removida. Isso significa que a exclusão de uma conta deixará registros de transações "órfãos" no banco de dados, o que pode levar a inconsistências em relatórios. Use este endpoint com cuidado.

### Exemplo de Requisição (cURL)

```bash
curl --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/accounts/remove' \
--header 'Authorization: Bearer SEU_TOKEN_JWT_OU_NONCE' \
--header 'Content-Type: application/json' \
--data-raw '{
    "id": 15
}'
```

### Resposta de Sucesso (200 OK)

```json
{
    "result": "ok"
}
```

### Respostas de Erro Comuns

- A rota não possui uma verificação explícita se o `id` existe antes de tentar deletar. Uma tentativa de deletar um `id` inexistente não retornará um erro, apenas não afetará nenhuma linha no banco de dados.
- `rest_not_logged_in` (401): Usuário não autenticado.
- `rest_forbidden` (403): Usuário sem permissão.
