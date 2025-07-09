# Referência da API REST

Todas as rotas estão registradas no namespace `money-manager/v1`. Para autenticação é necessário um nonce válido do WordPress e o usuário precisa da permissão `manage_options` (veja `Base_Controller`).

As tabelas a seguir listam cada rota, o método HTTP e os parâmetros esperados. Parâmetros marcados como **obrigatórios** devem ser enviados na requisição. Parâmetros opcionais possuem comportamento padrão quando omitidos.

## Contas

### GET `/accounts/list`
Retorna todas as contas ordenadas por tipo e título.

**Parâmetros:** nenhum

### POST `/accounts/save`
Cria ou atualiza uma conta.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `item` | objeto | Dados da conta contendo campos: `id` (para atualização), `title`, `type` (`checking`, `card`, `cash`, `debt` ou `crypto`), `currency`, `initial_balance`, `notes`, `color`. | sim |

### POST `/accounts/remove`
Exclui uma conta.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `id` | int | Identificador da conta a ser removida. | sim |

## Add-ons

### GET `/addons/list`
Obtém informações dos complementos disponíveis a partir de `getmoneymanager.com`.

**Parâmetros:** nenhum

### POST `/addons/install`
Instala um pacote de complemento.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `download_link` | string | URL direta para o arquivo ZIP do plugin. | sim |

### POST `/addons/activate`
Ativa um complemento instalado.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `slug` | string | Slug da pasta do plugin (ex.: `money-manager-xyz`). | sim |

### POST `/addons/deactivate`
Desativa um complemento.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `slug` | string | Slug da pasta do plugin a desativar. | sim |

## App

### GET `/app/load`
Retorna dados iniciais da aplicação, como contas, categorias, moedas, partes, cotações do dia e configurações do WooCommerce.

**Parâmetros:** nenhum

### POST `/app/save-meta`
Armazena configurações específicas do usuário em seu meta.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `meta` | objeto | Objeto de configurações salvo para o usuário atual. | sim |

## Categorias

### GET `/categories/list`
Lista todas as categorias ordenadas por título.

**Parâmetros:** nenhum

### POST `/categories/save`
Cria ou atualiza uma categoria.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `item` | objeto | Dados da categoria com campos `id`, `title`, `parent_id`, `color`. | sim |

### POST `/categories/remove`
Exclui uma categoria.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `id` | int | Identificador da categoria a ser removida. | sim |

## Moedas

### GET `/currencies/list`
Retorna todas as moedas.

**Parâmetros:** nenhum

### POST `/currencies/save`
Cria ou atualiza uma moeda. Quando `is_base` é definido, a moeda indicada torna-se a base e as demais são atualizadas.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `item` | objeto | Dados da moeda (`id` para atualização, `code`, `color`, `is_base`, `default_quote`). | sim |

### POST `/currencies/remove`
Exclui uma moeda.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `id` | int | Identificador da moeda a ser removida. | sim |

## Partes

### GET `/parties/list`
Lista todas as partes ordenadas por título.

**Parâmetros:** nenhum

### POST `/parties/save`
Cria ou atualiza uma parte/parceiro.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `item` | objeto | Dados da parte (`id`, `title`, `default_category_id`, `color`). | sim |

### POST `/parties/remove`
Exclui uma parte.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `id` | int | Identificador da parte a ser removida. | sim |

## Cotações

### GET `/quotes/load`
Carrega taxas de câmbio para a data informada (padrão: hoje).

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `date` | string (Y‑m‑d) | Data para obter as cotações. Caso omitida, utiliza a data atual. | não |

### GET `/quotes/list`
Retorna o histórico de cotações armazenado para uma moeda.

| Parameter | Type | Description | Required |
| --- | --- | --- | --- |
| `currency` | string | Código da moeda cujo histórico será carregado. | sim |

### POST `/quotes/refresh`
Atualiza as cotações do dia usando provedores on-line.

**Parâmetros:** nenhum

### POST `/quotes/fetch-history`
Baixa cotações históricas para as moedas indicadas e período informado.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `range` | string | Período como `1d`, `1mo`, `6mo`, `1y`. Padrão: `1d`. | não |
| `currencies` | array | Lista de códigos de moeda a atualizar. Se vazio, usa todas. | não |

### POST `/quotes/clear-history`
Remove histórico armazenado para as moedas informadas.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `currencies` | array | Códigos de moeda cujo histórico deve ser limpo. Vetor vazio não remove nada. | não |

## Relatórios

### GET `/reports/cash-flow`
Gera um relatório de fluxo de caixa.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `currency` | string | Código da moeda para conversão. Padrão `BRL`. | não |
| `range` | string | Identificador de período (`recent_12_months`, `this_year`, etc.). | não |
| `group_by` | string | `month` ou `party` para agrupar resultados. | não |
| `account_id` | int | Restringe o relatório a uma conta específica. | não |
| `include_transfers` | bool | Se verdadeiro, considera transferências quando uma conta é especificada. | não |

### GET `/reports/income-expenses`
Compara dados de receitas e despesas para vários períodos.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `currency` | string | Moeda usada para conversão, padrão `BRL`. | não |
| `ranges` | array | Vetor de identificadores de período (mesmos acima). | não |
| `group_by` | string | Forma de agrupamento (`month` ou `party`). | não |

## Transações

### POST `/transactions/list`
Retorna transações filtradas por conta e período. Quando `range` é `advanced_filter`, critérios adicionais podem ser informados.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `account_id` | int | Conta cujas transações serão buscadas. | sim |
| `range` | string | Intervalo pré-definido (`today`, `this_month`, `recent_30_days`, `advanced_filter`, ...). | sim |
| `criteria` | array | Quando `range` é `advanced_filter`, este array indica quais filtros se aplicam (`date_range`, `party`, `category`). | não |
| `date_from` / `date_to` | string (Y‑m‑d) | Datas utilizadas quando `criteria` contém `date_range`. | não |
| `party_ids` | array | IDs de partes quando `criteria` inclui `party`. | não |
| `category_ids` | array | IDs de categorias quando `criteria` inclui `category`. | não |

### POST `/transactions/save`
Cria ou atualiza uma transação juntamente com seus arquivos anexados.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `item` | objeto | Campos da transação (`id`, `account_id`, `to_account_id`, `party_id`, `category_id`, `date`, `type`, `amount`, `to_amount`, `notes`, `files`). | sim |

### POST `/transactions/remove`
Remove várias transações.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `ids` | array | Lista de IDs das transações a remover. | sim |

### POST `/transactions/import`
Importa um array de linhas simples de transações.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `data` | array | Linhas com `date`, `amount` e `notes`. | sim |
| `account_id` | int | ID da conta destino. | sim |
| `party_id` | int | Parte aplicada às linhas importadas. | não |
| `category_id` | int | Categoria aplicada às linhas importadas. | não |

## WooCommerce

### POST `/woocommerce/save`
Armazena opções de integração do WooCommerce.

| Parâmetro | Tipo | Descrição | Obrigatório |
| --- | --- | --- | --- |
| `item` | objeto | Conjunto de configurações como `enabled`, `payment_methods`, `paid_order_statuses`, `account_id`, etc. | sim |


**Observação:** toda documentação deste repositório deve permanecer em Português (Brasil).
