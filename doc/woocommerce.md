[Voltar ao Índice Principal](../index.md)

### Navegação Rápida

- [Accounts](./accounts.md)
- [Addons](./addons.md)
- [App](./app.md)
- [Categories](./categories.md)
- [Currencies](./currencies.md)
- [Parties](./parties.md)
- [Quotes](./quotes.md)
- [Reports](./reports.md)
- [Transactions](./transactions.md)

# Documentação da API: WooCommerce

Este documento detalha o endpoint da API para configurar a integração com o **WooCommerce**. A integração permite a criação automática de transações no Money Manager com base nos pedidos da sua loja.

**Controller:** `MoneyManager\Controllers\WooCommerce_Controller`

**Manager:** `MoneyManager\Managers\WooCommerce_Manager`

### Endpoint Disponível

- [POST /woocommerce/save](#post-woocommercesave)

---

## POST /woocommerce/save

Este endpoint salva a configuração completa da integração com o WooCommerce. A lógica de automação não é executada por este endpoint, mas sim por um "gancho" (hook) do WordPress (`woocommerce_after_order_object_save`) que utiliza esta configuração para operar em segundo plano.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Fluxo de Automação (em segundo plano)

1.  Quando um pedido do WooCommerce é criado ou atualizado, o `WooCommerce_Manager` é acionado.
2.  Ele verifica se a integração está habilitada e se o status e o método de pagamento do pedido correspondem aos definidos na configuração salva.
3.  Se as condições forem atendidas, ele cria (ou atualiza) uma transação do tipo `income` no Money Manager, associada à conta configurada.
4.  O valor da transação é o total do pedido, descontados os reembolsos.
5.  O saldo da conta designada é atualizado.

### Estrutura da Requisição e Parâmetros

A rota espera um único objeto `item` no corpo da requisição, contendo toda a configuração. A estrutura deste objeto é definida pelo método `settings()` no `WooCommerce_Manager`.

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `enabled` | Boolean | Habilita ou desabilita globalmente a integração. |
| `payment_methods` | Array | Lista de `slugs` de métodos de pagamento que devem acionar a criação de transação. Um valor `null` no array significa "qualquer método". |
| `paid_order_statuses` | Array | Lista de `slugs` de status de pedido que são considerados "pagos" (ex: `processing`, `completed`). |
| `account_id` | Integer | ID da conta no Money Manager onde a receita será registrada. |
| `party_id` | Integer | (Opcional) ID do favorecido a ser associado à transação. |
| `category_id` | Integer | (Opcional) ID da categoria a ser associada à transação. |
| `auto_delete_transactions` | Boolean | Se `true`, a transação será excluída se o status do pedido mudar para um que não está na lista de `paid_order_statuses`. |
| `configs` | Array | Uma lista de configurações mais específicas que podem sobrescrever a configuração global (funcionalidade avançada, possivelmente para add-ons). |

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/woocommerce/save' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "item": {
        "enabled": true,
        "payment_methods": ["bacs", "pix"],
        "paid_order_statuses": ["processing", "completed"],
        "account_id": 1,
        "party_id": 12,
        "category_id": 7,
        "auto_delete_transactions": false,
        "configs": []
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

Este endpoint é utilizado para salvar a configuração completa da integração com o WooCommerce. O agente deve usar este endpoint quando o usuário desejar configurar ou atualizar as definições de como os pedidos do WooCommerce devem gerar transações no Money Manager.

### Configuração do Body

O corpo da requisição deve ser um JSON no seguinte formato:

```json
{
  "item": {
    "enabled": {{ $fromAI('enabled', `Habilita ou desabilita globalmente a integração.`, 'boolean') }},
    "payment_methods": {{ $fromAI('payment_methods', `Lista de slugs de métodos de pagamento que devem acionar a criação de transação. Um valor null no array significa \"qualquer método\".`, 'array') }},
    "paid_order_statuses": {{ $fromAI('paid_order_statuses', `Lista de slugs de status de pedido que são considerados \"pagos\" (ex: \"processing\", \"completed\").`, 'array') }},
    "account_id": {{ $fromAI('account_id', `ID da conta no Money Manager onde a receita será registrada.`, 'number') }},
    "party_id": {{ $fromAI('party_id', `(Opcional) ID do favorecido a ser associado à transação. Atribua null se não houver.`, 'number') }},
    "category_id": {{ $fromAI('category_id', `(Opcional) ID da categoria a ser associada à transação. Atribua null se não houver.`, 'number') }},
    "auto_delete_transactions": {{ $fromAI('auto_delete_transactions', `Se true, a transação será excluída se o status do pedido mudar para um que não está na lista de paid_order_statuses.`, 'boolean') }},
    "configs": {{ $fromAI('configs', `Uma lista de configurações mais específicas que podem sobrescrever a configuração global (funcionalidade avançada, possivelmente para add-ons).`, 'array') }}
  }
}
```