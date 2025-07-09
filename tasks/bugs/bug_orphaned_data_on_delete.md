
# Análise de Bug Potencial: Exclusão de Registros Causa Dados Órfãos

- **ID do Bug:** MM-BUG-001
- **Severidade:** Alta
- **Componentes Afetados:** `Accounts_Controller`, `Categories_Controller`, `Parties_Controller`
- **Rotas Afetadas:** `POST /accounts/remove`, `POST /categories/remove`, `POST /parties/remove`

---

## 1. Descrição Detalhada do Bug

Os controllers responsáveis por remover contas, categorias e favorecidos executam uma exclusão direta do registro no banco de dados sem verificar se aquele registro está sendo utilizado por outras entidades, como as transações. 

Quando um usuário exclui uma categoria que já foi associada a uma ou mais transações, o registro da categoria é apagado, mas as transações continuam no banco com um `category_id` que não existe mais. O mesmo ocorre para favorecidos (`party_id`) e contas (`account_id`).

Isso quebra a integridade referencial dos dados e leva a um estado inconsistente, onde as transações ficam "órfãs", apontando para registros que não existem.

### Impacto

- **Inconsistência em Relatórios:** Relatórios que agrupam por categoria ou favorecido podem falhar, apresentar dados incorretos ou simplesmente omitir as transações órfãs.
- **Erros na Interface:** A interface do usuário pode quebrar ou exibir informações vazias ao tentar renderizar uma transação que aponta para um favorecido ou categoria excluído.
- **Dificuldade de Correção:** Corrigir manualmente os dados órfãos no banco de dados é uma tarefa complexa e arriscada.
- **Exclusão de Subcategorias:** No caso específico de categorias, a exclusão de uma categoria-pai torna suas subcategorias órfãs, quebrando a estrutura hierárquica.

---

## 2. Cenários de Teste e Exemplos

### Cenário 1: Excluindo uma Categoria em Uso

1.  **Preparação:**
    - Crie uma categoria "Transporte" (que receberá o ID: 10, por exemplo).
    - Crie uma transação de despesa de R$ 50,00 e associe-a à categoria "Transporte".

2.  **Ação:**
    - Envie uma requisição `POST` para `/categories/remove` com o corpo `{"id": 10}`.

3.  **Resultado Esperado (Com Bug):**
    - A API retorna `{"result": "ok"}` (status 200).
    - A categoria "Transporte" é removida da tabela `wp_money_manager_categories`.
    - A transação de R$ 50,00 permanece na tabela `wp_money_manager_transactions` com o campo `category_id` ainda definido como `10`.

4.  **Verificação do Erro:**
    - Tente listar as transações na interface. A transação de R$ 50,00 pode aparecer sem nome de categoria.
    - Gere um relatório de despesas por categoria. O valor de R$ 50,00 pode ser agrupado em "Não categorizado" ou simplesmente ignorado, dependendo da implementação do relatório.

---

## 3. Propostas de Correção

A seguir, são apresentadas quatro possíveis abordagens para corrigir este bug, cada uma com seus prós e contras.

### Proposta 1: Impedir a Exclusão (Prevenção)

**Descrição Detalhada:**
Esta é a abordagem mais segura. Antes de executar o `destroy()`, o método `remove_...` faria uma consulta prévia para verificar se existem registros dependentes. 

No `remove_category`, por exemplo, ele verificaria se existe alguma transação com `category_id = ID_DA_CATEGORIA` ou alguma outra categoria com `parent_id = ID_DA_CATEGORIA`. Se qualquer dependência for encontrada, a operação é abortada e um erro é retornado ao cliente.

**Implementação (Exemplo para `remove_category`):**

```php
public function remove_category( WP_REST_Request $request )
{
    global $wpdb;
    $id = $request->get_param( 'id' );

    // Verificar transações dependentes
    $transactions_table = $wpdb->prefix . 'money_manager_transactions';
    $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $transactions_table WHERE category_id = %d", $id ) );

    // Verificar subcategorias dependentes
    $categories_table = $wpdb->prefix . 'money_manager_categories';
    $sub_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $categories_table WHERE parent_id = %d", $id ) );

    if ( $count > 0 || $sub_count > 0 ) {
        return new WP_Error(
            'rest_dependency_exists',
            'Não é possível excluir. Existem transações ou subcategorias associadas.',
            array( 'status' => 409 ) // 409 Conflict é um status apropriado
        );
    }

    Category::destroy( $id );

    return rest_ensure_response( array( 'result' => 'ok' ) );
}
```

- **Vantagens:** Garante 100% a integridade dos dados. É simples de implementar e o comportamento é previsível.
- **Desvantagens:** Pode ser frustrante para o usuário, que precisa encontrar e reatribuir manualmente todos os itens dependentes antes de poder excluir.

### Proposta 2: Exclusão em Cascata

**Descrição Detalhada:**
Nesta abordagem, a exclusão do registro principal acionaria a exclusão de todos os registros dependentes. Por exemplo, ao excluir uma conta, todas as transações dessa conta seriam permanentemente apagadas.

- **Vantagens:** Mantém a integridade do banco de dados de forma automática.
- **Desvantagens:** **EXTREMAMENTE PERIGOSO.** Pode levar à perda acidental e massiva de dados. Um usuário poderia apagar anos de histórico financeiro com um único clique. **Não é recomendado para este tipo de aplicação.**

### Proposta 3: Definir Dependências como Nulas (Set Null)

**Descrição Detalhada:**
Ao excluir um registro, os campos de chave estrangeira nos registros dependentes seriam atualizados para `NULL`. Por exemplo, ao excluir a categoria "Transporte", o campo `category_id` de todas as transações associadas seria alterado para `NULL`.

**Implementação (Exemplo para `remove_category`):**

```php
public function remove_category( WP_REST_Request $request )
{
    global $wpdb;
    $id = $request->get_param( 'id' );

    // Desassociar transações
    $transactions_table = $wpdb->prefix . 'money_manager_transactions';
    $wpdb->update( $transactions_table, array( 'category_id' => null ), array( 'category_id' => $id ) );

    // ... (Lógica para tratar subcategorias, talvez reatribuindo-as ao pai da categoria excluída)

    Category::destroy( $id );

    return rest_ensure_response( array( 'result' => 'ok' ) );
}
```

- **Vantagens:** Evita a perda de dados das transações e mantém a integridade referencial.
- **Desvantagens:** Requer que as colunas (`category_id`, `party_id`) no banco de dados permitam valores nulos. A lógica para tratar subcategorias pode ser complexa (reatribuir ao "avô"? torná-las categorias-pai?).

### Proposta 4: Reatribuição para um Padrão (Recomendado)

**Descrição Detalhada:**
Esta é a solução mais robusta e amigável. A API poderia aceitar um segundo parâmetro, como `reattribution_id`. Ao excluir uma categoria, o usuário forneceria o ID de outra categoria para a qual todas as transações dependentes seriam movidas.

**Implementação (Exemplo para `remove_category`):**

```php
// A rota POST /categories/remove aceitaria { "id": 10, "fallback_id": 1 }
public function remove_category( WP_REST_Request $request )
{
    global $wpdb;
    $id_to_delete = $request->get_param( 'id' );
    $fallback_id = $request->get_param( 'fallback_id' );

    if ( empty( $fallback_id ) ) {
        // Poderia ser combinado com a Proposta 1: se não houver fallback, verificar dependências.
        return new WP_Error('rest_fallback_required', 'É necessário fornecer um ID de fallback.', array('status' => 400));
    }

    // Reatribuir transações
    $transactions_table = $wpdb->prefix . 'money_manager_transactions';
    $wpdb->update( $transactions_table, array( 'category_id' => $fallback_id ), array( 'category_id' => $id_to_delete ) );

    // Reatribuir subcategorias
    $categories_table = $wpdb->prefix . 'money_manager_categories';
    $wpdb->update( $categories_table, array( 'parent_id' => $fallback_id ), array( 'parent_id' => $id_to_delete ) );

    Category::destroy( $id_to_delete );

    return rest_ensure_response( array( 'result' => 'ok' ) );
}
```

- **Vantagens:** Oferece controle total ao usuário, previne perda de dados e mantém a integridade de forma limpa.
- **Desvantagens:** É a mais complexa de implementar, pois exige modificações na API e na interface do usuário para solicitar o `fallback_id`.
