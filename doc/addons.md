# Documentação da API: Add-ons

Este documento detalha os endpoints da API para o gerenciamento de **Add-ons**. Estes endpoints funcionam como uma interface para as funcionalidades nativas do WordPress de instalação e gerenciamento de plugins.

**Controller:** `MoneyManager\Controllers\Addons_Controller`

**Modelo Relacionado:** Nenhum. As operações são realizadas através de funções do core do WordPress e requisições a um servidor externo.

### Endpoints Disponíveis

- [GET /addons/list](#get-addonslist)
- [POST /addons/install](#post-addonsinstall)
- [POST /addons/activate](#post-addonsactivate)
- [POST /addons/deactivate](#post-addonsdeactivate)

---

## GET /addons/list

Este endpoint busca a lista de add-ons disponíveis a partir de um servidor remoto, não do banco de dados local.

- **Método HTTP:** `GET`
- **Autenticação:** Requerida.

### Fluxo de Execução

1.  Realiza uma requisição HTTP GET para `https://getmoneymanager.com/addons/info.json`.
2.  Espera receber um JSON com a lista e os metadados dos add-ons.
3.  Retorna o conteúdo deste JSON para o cliente.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/addons/list' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

*O formato exato depende do arquivo `info.json` remoto. Um exemplo hipotético seria:*
```json
{
    "result": [
        {
            "name": "Advanced Reports",
            "slug": "money-manager-reports-pro",
            "description": "Gere relatórios avançados e personalizados.",
            "version": "1.2.0",
            "download_link": "https://getmoneymanager.com/download/12345/reports-pro.zip"
        },
        {
            "name": "Split Transactions",
            "slug": "money-manager-split-txn",
            "description": "Divida uma única transação em múltiplas categorias.",
            "version": "1.1.0",
            "download_link": "https://getmoneymanager.com/download/12346/split-txn.zip"
        }
    ]
}
```

---

## POST /addons/install

Instala um novo add-on (plugin) no site a partir de um link de download.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### !! AVISO DE SEGURANÇA !!

Esta é uma operação **altamente sensível**. Ela baixa um arquivo ZIP de uma URL externa, o descompacta e instala seu conteúdo como um plugin ativo em seu site. O usuário que executa esta ação deve ter a capacidade (`capability`) `install_plugins`, normalmente reservada para Administradores. **Use este endpoint apenas com links de fontes 100% confiáveis.**

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `download_link` | String | Sim | A URL completa para o arquivo `.zip` do plugin a ser instalado. |

### Fluxo de Execução

1.  Carrega os arquivos necessários do `wp-admin/includes/`.
2.  Utiliza a classe `Plugin_Upgrader` do WordPress para realizar a instalação a partir do `download_link`.
3.  Retorna `{"result": "ok"}` em caso de sucesso.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/addons/install' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "download_link": "https://getmoneymanager.com/download/12345/reports-pro.zip"
}'
```

---

## POST /addons/activate

Ativa um add-on que já está instalado, mas inativo.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.
- **Permissão Necessária:** `activate_plugins`.

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `slug` | String | Sim | O slug do plugin a ser ativado (ex: `money-manager-reports-pro`). |

### Fluxo de Execução

1.  Utiliza a função `activate_plugin()` do WordPress.
2.  Assume que o arquivo principal do plugin está localizado em `{slug}/main.php`.
3.  Retorna `{"result": "ok"}` se a ativação for bem-sucedida.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/addons/activate' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "slug": "money-manager-reports-pro"
}'
```

---

## POST /addons/deactivate

Desativa um add-on ativo.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.
- **Permissão Necessária:** `activate_plugins` (a mesma para ativar).

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `slug` | String | Sim | O slug do plugin a ser desativado (ex: `money-manager-reports-pro`). |

### Fluxo de Execução

1.  Utiliza a função `deactivate_plugins()` do WordPress.
2.  Assume que o arquivo principal do plugin está localizado em `{slug}/main.php`.
3.  Retorna `{"result": "ok"}`.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/addons/deactivate' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "slug": "money-manager-reports-pro"
}'
```
