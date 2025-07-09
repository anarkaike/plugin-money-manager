
# Proposta de Melhoria: Implementação de Lixeira (Soft Deletes)

- **ID da Tarefa:** MM-BKLOG-002
- **Tipo:** Melhoria de Funcionalidade / Segurança de Dados
- **Componentes Afetados:** Todos os Models e Controllers que possuem a ação de `remove`.

---

## 1. Descrição do Problema

Atualmente, todas as operações de exclusão no plugin (`/accounts/remove`, `/transactions/remove`, etc.) são destrutivas. Elas executam uma consulta `DELETE` diretamente no banco de dados. Uma vez que um registro é removido, não há como recuperá-lo, exceto através de um backup do banco de dados.

Isso representa um risco significativo para o usuário:

- **Exclusão Acidental:** Um clique errado pode levar à perda permanente de dados financeiros importantes.
- **Falta de Confiança:** A natureza implacável da exclusão pode deixar os usuários receosos de gerenciar seus dados.
- **Inconsistência com Padrões:** A maioria das aplicações modernas, incluindo o próprio WordPress (para posts e páginas), utiliza um sistema de lixeira que permite a recuperação de itens excluídos.

## 2. Proposta de Implementação

A proposta é implementar o padrão de design conhecido como **Soft Deletes**. Em vez de apagar o registro, nós o marcaremos como "excluído".

### Passo 1: Modificar as Tabelas do Banco de Dados

Todas as tabelas principais (`accounts`, `transactions`, `categories`, `parties`, `currencies`) precisariam de uma nova coluna:

- `deleted_at` (tipo `DATETIME`, `NULL` por padrão).

Quando esta coluna é `NULL`, o registro está ativo. Quando ela contém uma data e hora, o registro está na lixeira.

### Passo 2: Modificar a Classe Base do Modelo

A classe `models/class-base.php` seria o local central para implementar a lógica.

1.  **Sobrescrever `destroy()`:** O método `destroy()` não executaria mais `DELETE`. Em vez disso, ele executaria um `UPDATE` para definir o campo `deleted_at` para a data e hora atuais nos registros correspondentes.

2.  **Adicionar Escopo Global:** Todas as consultas de busca (`find()`, `rows()`, `count()`) seriam modificadas para adicionar automaticamente a condição `WHERE deleted_at IS NULL`. Isso garante que, por padrão, a aplicação interaja apenas com os registros ativos.

**Implementação Sugerida (em `models/class-base.php`):**

```php
// Novo método destroy para "soft delete"
public static function destroy( $ids )
{
    global $wpdb;
    // ... (lógica para converter $ids em array)

    $wpdb->update(
        static::table_name(),
        array( 'deleted_at' => current_time( 'mysql' ) ), // Marca como deletado
        array( '%d' => $ids_placeholders ) // Onde o ID corresponde
    );
}

// Exemplo de modificação em rows() para ignorar os deletados
public static function rows( $callback = null )
{
    global $wpdb;
    $table_name = static::table_name();
    $query = "SELECT * FROM $table_name WHERE deleted_at IS NULL"; // Adiciona o escopo
    if ( $callback ) {
        $query = call_user_func( $callback, $query );
    }
    return $wpdb->get_results( $query );
}
```

### Passo 3: Criar Novos Endpoints para a Lixeira

Novas rotas seriam necessárias para gerenciar a lixeira:

- `GET /<recurso>/trashed`: Listaria apenas os itens na lixeira (`WHERE deleted_at IS NOT NULL`).
- `POST /<recurso>/restore`: Receberia um ID e definiria seu `deleted_at` de volta para `NULL`, efetivamente restaurando o item.
- `POST /<recurso>/force-delete`: Receberia um ID e executaria um `DELETE` real no banco de dados para remover o item permanentemente da lixeira. Esta ação seria final e irreversível.

## 3. Impacto da Melhoria

- **Segurança dos Dados:** Reduz drasticamente o risco de perda de dados por exclusão acidental, aumentando a confiança do usuário.
- **Melhor UX:** Proporciona uma experiência de usuário mais familiar e segura, alinhada com as expectativas de aplicações modernas.
- **Complexidade Aumentada:** A implementação não é trivial. Exige alterações em todas as consultas do banco de dados, a criação de novos endpoints e modificações significativas na interface do usuário para gerenciar a lixeira.
- **Consistência de Dados:** A lógica de "soft delete" também precisa ser considerada em conjunto com o bug de dados órfãos (MM-BUG-001). Ao mover um item para a lixeira, suas dependências também devem ser tratadas de forma consistente.
