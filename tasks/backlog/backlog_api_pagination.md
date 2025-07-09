
# Proposta de Melhoria: Paginação nos Endpoints de Listagem

- **ID da Tarefa:** MM-BKLOG-001
- **Tipo:** Melhoria de Performance / Escalabilidade
- **Componentes Afetados:** `Accounts_Controller`, `Categories_Controller`, `Parties_Controller`, `Currencies_Controller`, `Quotes_Controller`
- **Rotas Afetadas:** `/accounts/list`, `/categories/list`, `/parties/list`, `/currencies/list`, `/quotes/list`

---

## 1. Descrição do Problema

Atualmente, todos os endpoints de listagem (`GET /.../list`) do plugin buscam e retornam **todos** os registros da tabela correspondente em uma única requisição. Por exemplo, `/accounts/list` retorna todas as contas que o usuário possui.

Isso funciona bem em um ambiente de teste ou com poucos dados, mas não escala. Em um site com milhares de transações, centenas de categorias ou dezenas de contas, uma única chamada de API pode:

- **Consumir Muita Memória:** Carregar milhares de objetos do banco de dados na memória do PHP pode exceder os limites do servidor, causando erros fatais.
- **Aumentar a Latência:** A requisição se torna extremamente lenta, prejudicando a experiência do usuário na interface, que precisa esperar todos os dados serem carregados.
- **Gerar Tráfego de Rede Excessivo:** Transferir um JSON de vários megabytes a cada carregamento de página é ineficiente.

## 2. Proposta de Implementação

A solução padrão para este problema, e que é utilizada pelo próprio core da API REST do WordPress, é a **paginação baseada em parâmetros**.

### Passo 1: Modificar a Assinatura dos Endpoints

Os endpoints de listagem devem ser modificados para aceitar dois novos parâmetros de query (`query params`):

- `per_page`: O número máximo de itens a serem retornados por página. (Ex: 25). Um padrão razoável deve ser definido (ex: 20).
- `page`: O número da página que o cliente deseja buscar. (Ex: 1, 2, 3...). O padrão é 1.

### Passo 2: Alterar a Lógica do Controller

Dentro dos métodos de listagem (ex: `list_accounts`), a lógica deve ser alterada para usar esses parâmetros e modificar a consulta SQL.

**Implementação Sugerida (Exemplo para `list_accounts`):**

```php
public function list_accounts( WP_REST_Request $request )
{
    // Definir parâmetros de paginação com valores padrão
    $per_page = (int) $request->get_param( 'per_page' ) ?: 20;
    $page = (int) $request->get_param( 'page' ) ?: 1;
    $offset = $per_page * ( $page - 1 );

    // Obter o total de registros para os cabeçalhos de resposta
    $total_accounts = Account::count(); // Um novo método count() seria necessário no modelo Base

    $accounts = Account::rows( function ( $query ) use ( $per_page, $offset ) {
        // Adicionar a ordenação existente e a paginação
        return $query . " order by field(type,\"checking\",\"card\",\"cash\",\"debt\",\"crypto\"), title LIMIT {$per_page} OFFSET {$offset}";
    } );

    $response = rest_ensure_response( array( 'result' => $accounts ) );

    // Adicionar cabeçalhos de paginação padrão do WordPress
    $response->header( 'X-WP-Total', $total_accounts );
    $response->header( 'X-WP-TotalPages', ceil( $total_accounts / $per_page ) );

    return $response;
}
```

### Passo 3: Adicionar o Método `count()` ao Modelo Base

Para que o passo 2 funcione, um método simples para contar o total de registros precisaria ser adicionado à classe `models/class-base.php`.

```php
// Em models/class-base.php
public static function count( $callback = null )
{
    global $wpdb;
    $table_name = static::table_name();
    $query = "SELECT COUNT(*) FROM $table_name";
    if ( $callback ) {
        $query = call_user_func( $callback, $query );
    }
    return (int) $wpdb->get_var( $query );
}
```

## 3. Impacto da Melhoria

- **Performance e Escalabilidade:** O impacto mais significativo é a melhoria drástica na performance. O tempo de resposta da API se tornará rápido e consistente, independentemente do volume de dados.
- **Redução do Uso de Recursos:** O consumo de memória e CPU no servidor será significativamente menor.
- **Melhor Experiência do Usuário:** A interface do aplicativo carregará os dados de forma incremental e rápida, proporcionando uma experiência mais fluida.
- **Conformidade com Padrões:** A API passará a seguir as melhores práticas e os padrões estabelecidos pela API REST do WordPress, tornando-a mais fácil de ser consumida por clientes de terceiros.
- **Impacto na Interface (UI):** A interface do usuário precisará ser atualizada para lidar com a paginação, incluindo controles para navegar entre as páginas (ex: botões "Próxima Página", "Página Anterior") e potencialmente um seletor para a quantidade de itens por página.
