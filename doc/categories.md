# Documentação da API: Categories

Este documento detalha todos os endpoints da API relacionados ao gerenciamento de **Categorias (Categories)**. As categorias são usadas para classificar transações e suportam uma estrutura hierárquica (categorias e subcategorias).

**Controller:** `MoneyManager\Controllers\Categories_Controller`

**Modelo Relacionado:** `MoneyManager\Models\Category`

### Endpoints Disponíveis

- [GET /categories/list](#get-categorieslist)
- [POST /categories/save](#post-categoriessave)
- [POST /categories/remove](#post-categoriesremove)

---

## GET /categories/list

Este endpoint recupera uma lista de todas as categorias cadastradas, ordenadas alfabeticamente pelo título.

- **Método HTTP:** `GET`
- **Autenticação:** Requer autenticação padrão do WordPress.

### Parâmetros

Este endpoint não aceita parâmetros de entrada.

### Fluxo de Execução

1.  O método `list_categories` chama `Category::rows()`.
2.  A consulta ao banco de dados é modificada para ordenar os resultados por `title`.
3.  A lista de objetos de categoria é retornada dentro de uma chave `result`.

### Exemplo de Requisição (cURL)

```bash
curl --location --request GET 'https://seu-site.com/wp-json/money-manager/v1/categories/list' \
--header 'Authorization: Bearer SEU_TOKEN'
```

### Exemplo de Resposta de Sucesso (200 OK)

```json
{
    "result": [
        {
            "id": 1,
            "title": "Alimentação",
            "parent_id": null,
            "color": "#FF5733"
        },
        {
            "id": 10,
            "title": "Lazer",
            "parent_id": null,
            "color": "#33FF57"
        },
        {
            "id": 2,
            "title": "Supermercado",
            "parent_id": 1,
            "color": "#FFC300"
        },
        {
            "id": 3,
            "title": "Transporte",
            "parent_id": null,
            "color": "#581845"
        }
    ]
}
```
*No exemplo, "Supermercado" é uma subcategoria de "Alimentação".*

---

## POST /categories/save

Cria ou atualiza uma categoria. Para criar uma subcategoria, basta fornecer o `id` da categoria pai no campo `parent_id`.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "item": {
    "id": 2, // Opcional, para atualização
    "title": "Mercado e Feira",
    "parent_id": 1,
    "color": "#FFC300"
  }
}
```

### Parâmetros (dentro de `item`)

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `id` | Integer | Não | ID da categoria para atualização. Se omitido, cria uma nova. |
| `title` | String | Sim | O nome da categoria. |
| `parent_id` | Integer | Não | O ID da categoria pai. Se `null` ou omitido, é uma categoria de nível superior. |
| `color` | String | Não | Um código de cor hexadecimal (ex: `#FF5733`) para a interface. |

### Fluxo de Execução

1.  O método `save_category` extrai o objeto `item`.
2.  Verifica a presença de `item[id]` para decidir entre criar uma nova `Category` ou encontrar (`find`) e atualizar uma existente.
3.  Os dados são atribuídos de forma segura usando o método `fill()` que respeita a lista `$fillable` do modelo.
4.  A categoria é salva no banco de dados (`INSERT` ou `UPDATE`).
5.  Retorna `{"result": "ok"}`.

### Exemplo de Requisição (cURL para criar subcategoria)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/categories/save' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "item": {
        "title": "Restaurante",
        "parent_id": 1,
        "color": "#C70039"
    }
}'
```

### Resposta de Sucesso (200 OK)

```json
{
    "result": "ok"
}
```

---

## POST /categories/remove

Exclui permanentemente uma categoria.

- **Método HTTP:** `POST`
- **Autenticação:** Requerida.

### Estrutura da Requisição

```json
{
  "id": 10
}
```

### Parâmetros

| Parâmetro | Tipo | Obrigatório? | Descrição |
|---|---|---|---|
| `id` | Integer | Sim | O ID da categoria a ser excluída. |

### Fluxo de Execução

1.  O método `remove_category` extrai o `id`.
2.  Chama `Category::destroy($id)`, que executa uma consulta `DELETE` no banco de dados.
3.  Retorna `{"result": "ok"}`.

### Considerações Importantes: Dados Órfãos

A implementação atual **não trata dados dependentes** ao excluir uma categoria:

- **Transações:** Transações associadas a esta categoria **não serão atualizadas**. O campo `category_id` delas continuará apontando para um ID que não existe mais, tornando-as órfãs e podendo causar problemas em filtros e relatórios.
- **Subcategorias:** Se uma categoria pai é excluída, suas subcategorias **não são excluídas nem reatribuídas**. Elas se tornarão órfãs, com um `parent_id` que não aponta para nenhum registro válido.

**Recomendação:** Antes de excluir uma categoria, reatribua manualmente suas transações e subcategorias através da interface do plugin ou de outras chamadas de API.

### Exemplo de Requisição (cURL)

```bash
cURL --location --request POST 'https://seu-site.com/wp-json/money-manager/v1/categories/remove' \
--header 'Authorization: Bearer SEU_TOKEN' \
--header 'Content-Type: application/json' \
--data-raw '{
    "id": 10
}'
```