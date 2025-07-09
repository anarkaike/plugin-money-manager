# Análise Detalhada da Rota: `POST /wp-json/money-manager/v1/accounts/save`

Este documento fornece uma análise técnica aprofundada da rota de API `accounts/save` do plugin Money Manager. O objetivo é detalhar seu funcionamento, parâmetros, fluxo de execução, e fornecer exemplos práticos para desenvolvedores.

---

## 1. Visão Geral

A rota `POST /wp-json/money-manager/v1/accounts/save` é o endpoint central para a criação e atualização de contas de usuário no sistema. Ela foi projetada para ser um ponto de entrada único, diferenciando entre a criação de um novo registro e a atualização de um existente com base na presença de um `id` no corpo da requisição.

- **Endpoint:** `/wp-json/money-manager/v1/accounts/save`
- **Método HTTP:** `POST`
- **Autenticação:** Requer autenticação padrão do WordPress. O usuário deve estar logado e possuir as capacidades necessárias para gerenciar o plugin. A ausência de um `permission_callback` explícito na definição da rota em `Accounts_Controller` significa que ela herda as permissões gerais definidas para o `money-manager/v1`, que tipicamente se baseiam na capacidade `manage_options` ou uma capacidade personalizada similar definida durante a inicialização do plugin.

---

## 2. Estrutura da Requisição

A rota espera que o corpo da requisição (`body`) seja um JSON contendo um objeto principal chamado `item`. Este objeto, por sua vez, contém todos os dados da conta a ser salva.

**Formato do Corpo (Body):**

```json
{
  "item": {
    "parametro1": "valor1",
    "parametro2": "valor2",
    ...
  }
}
```

---

## 3. Parâmetros Detalhados

Os parâmetros aceitos dentro do objeto `item` são definidos pela propriedade `$fillable` no modelo `models/class-account.php`. A seguir, uma descrição detalhada de cada um.

| Parâmetro         | Tipo    | Obrigatório? | Descrição                                                                                                                                                                                                                         |
| ----------------- | ------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`              | Integer | Não          | **Para atualização.** O identificador único da conta. Se este parâmetro for fornecido, a API tentará encontrar e atualizar a conta correspondente. Se for omitido, a API criará uma nova conta.                                     |
| `title`           | String  | Sim          | O nome da conta, que será exibido na interface. Ex: "Carteira", "Conta Corrente Banco do Brasil", "Cartão de Crédito Nubank".                                                                                                       |
| `type`            | String  | Sim          | O tipo da conta. Este campo é crucial para a organização e agrupamento. Os valores permitidos são: `checking`, `card`, `cash`, `debt`, `crypto`.                                                                                     |
| `currency`        | String  | Sim          | O código de 3 letras da moeda da conta, padrão ISO 4217. Ex: "BRL", "USD", "EUR".                                                                                                                                                  |
| `initial_balance` | Float   | Sim          | O saldo inicial da conta no momento de sua criação. **Importante:** O saldo final (`balance`) não é definido diretamente por esta rota; ele é calculado dinamicamente pelo sistema. Este é o valor base para esse cálculo.         |
| `notes`           | String  | Não          | Um campo de texto livre para anotações sobre a conta. Ex: "Conta conjunta com...", "Usar apenas para emergências".                                                                                                                  |
| `color`           | String  | Não          | Um código de cor hexadecimal (ex: `#FF5733`) para ser usado na interface, facilitando a identificação visual da conta.                                                                                                               |

---

## 4. Fluxo de Execução Interno (Análise do Código)

Compreender o fluxo de execução é fundamental para usar a API de forma eficaz e para depurar problemas.

1.  **Recebimento da Requisição:** O servidor WordPress recebe uma requisição `POST` para a rota `/wp-json/money-manager/v1/accounts/save`.

2.  **Controlador Acionado:** A requisição é direcionada para o método `save_account` da classe `MoneyManager\Controllers\Accounts_Controller`.

3.  **Extração de Dados:** O método extrai o objeto `item` do corpo da requisição usando `$request->get_param('item')`.

4.  **Decisão: Criar ou Atualizar?**
    - O código verifica a existência da chave `id` dentro do objeto `item` (`isset($input['id'])`).
    - **Se `id` existe (Atualização):**
        - O sistema busca a conta no banco de dados com `Account::find($input['id'])`.
        - Se a conta não for encontrada, retorna um erro `RECORD_NOT_FOUND`.
        - Se encontrada, os novos dados do `item` são mesclados ao objeto da conta existente através do método `fill()`. Este método atualiza apenas os campos listados na propriedade `$fillable` do modelo `Account`.
    - **Se `id` não existe (Criação):**
        - Uma nova instância do modelo `Account` é criada: `new Account($input)`. O construtor da classe `Base` (herdada por `Account`) também utiliza a lógica de `$fillable` para atribuir os dados em massa de forma segura.

5.  **Persistência no Banco de Dados:**
    - O método `save()` é chamado no objeto `$account`. Este método, herdado da classe `Base`, executa uma operação de `UPDATE` ou `INSERT` na tabela `wp_money_manager_accounts` do banco de dados, dependendo se o objeto já existia ou é novo.

6.  **Recálculo do Saldo (Passo Crítico):**
    - Após a persistência dos dados básicos, o controlador invoca o método estático `Account_Manager::refresh_balance($account->id)`.
    - Este método é responsável por garantir a integridade do saldo da conta. Ele executa a seguinte lógica:
        a. Pega o `initial_balance` da conta recém-salva.
        b. Consulta a tabela de transações (`wp_money_manager_transactions`) para somar e subtrair todas as movimentações associadas a este `account_id`.
        c. As transações consideradas são: `income` (soma), `expense` (subtrai), `transfer` (soma se for a conta de destino, subtrai se for a conta de origem).
        d. O resultado final é atualizado diretamente no campo `balance` da tabela `wp_money_manager_accounts`.

7.  **Resposta ao Cliente:**
    - O controlador retorna uma resposta JSON simples indicando sucesso: `{"result": "ok"}`.

---

## 5. Exemplos de Uso

### Exemplo 1: Criando uma Nova Conta (cURL)

Esta requisição cria uma nova conta do tipo "Conta Corrente" (checking).

```bash
curl --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/accounts/save' \
--header 'Authorization: Bearer SEU_TOKEN_JWT_OU_NONCE' \
--header 'Content-Type: application/json' \
--data-raw '{
    "item": {
        "title": "Conta Corrente Principal",
        "type": "checking",
        "currency": "BRL",
        "initial_balance": 1500.75,
        "notes": "Conta principal para recebimento de salário.",
        "color": "#2980b9"
    }
}'
```

### Exemplo 2: Atualizando uma Conta Existente (cURL)

Esta requisição atualiza o nome e a cor de uma conta com `id = 15`.

```bash
curl --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/accounts/save' \
--header 'Authorization: Bearer SEU_TOKEN_JWT_OU_NONCE' \
--header 'Content-Type: application/json' \
--data-raw '{
    "item": {
        "id": 15,
        "title": "Conta Corrente Principal (Atualizado)",
        "color": "#3498db"
    }
}'
```
*Nota: Apenas os campos a serem alterados precisam ser enviados, além do `id`.*

### Exemplo 3: Criando uma Nova Conta (JavaScript com `fetch`)

Este snippet pode ser usado no frontend da aplicação ou em um script Node.js.

```javascript
async function createAccount() {
    const accountData = {
        item: {
            title: "Cartão de Crédito Visa",
            type: "card",
            currency: "BRL",
            initial_balance: 0,
            notes": "Fechamento todo dia 10.",
            color: "#c0392b"
        }
    };

    try {
        const response = await fetch('https://seu-site.com/wp-json/money-manager/v1/accounts/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // Para autenticação via nonce (comum em temas WP)
                'X-WP-Nonce': wpApiSettings.nonce 
            },
            body: JSON.stringify(accountData)
        });

        const result = await response.json();

        if (response.ok) {
            console.log('Conta criada com sucesso:', result);
            // Ex: {"result":"ok"}
        } else {
            console.error('Erro ao criar conta:', result);
        }
    } catch (error) {
        console.error('Erro de rede ou de execução:', error);
    }
}

createAccount();
```

---

## 6. Respostas da API

### Resposta de Sucesso

Uma operação bem-sucedida (criação ou atualização) retornará um status `200 OK` e um corpo JSON simples.

```json
{
    "result": "ok"
}
```

### Respostas de Erro

Os erros seguem o formato padrão da API REST do WordPress.

**Erro 1: Tentativa de atualizar uma conta inexistente**

- **Cenário:** Enviar uma requisição de atualização com um `id` que não existe no banco de dados.
- **Status:** `200 OK` (O controlador trata o erro internamente e retorna 200, o que pode ser um ponto de melhoria no plugin)
- **Corpo:**
```json
{
    "error": {
        "code": "RECORD_NOT_FOUND"
    }
}
```

**Erro 2: Não autenticado**

- **Cenário:** Fazer a requisição sem estar logado no WordPress ou sem fornecer um token/nonce válido.
- **Status:** `401 Unauthorized`
- **Corpo:**
```json
{
    "code": "rest_not_logged_in",
    "message": "Você não está conectado no momento.",
    "data": {
        "status": 401
    }
}
```

**Erro 3: Sem permissão**

- **Cenário:** O usuário está logado, mas sua função (role) não tem a capacidade (`capability`) necessária para acessar esta rota.
- **Status:** `403 Forbidden`
- **Corpo:**
```json
{
    "code": "rest_forbidden",
    "message": "Desculpe, você não tem permissão para fazer isso.",
    "data": {
        "status": 403
    }
}
```

**Erro 4: Parâmetro mal formatado**

- **Cenário:** Enviar um JSON inválido no corpo da requisição.
- **Status:** `400 Bad Request`
- **Corpo:**
```json
{
    "code": "rest_invalid_json",
    "message": "Corpo da requisição inválido.",
    "data": {
        "status": 400,
        "json_error_code": 4,
        "json_error_message": "Syntax error"
    }
}
```

---
*Este documento foi gerado com base na análise estática do código-fonte do plugin Money Manager em 08/07/2025.*