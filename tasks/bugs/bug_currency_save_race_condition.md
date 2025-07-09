
# Análise de Bug Potencial: Falta de Atomicidade ao Alterar a Moeda Base

- **ID do Bug:** MM-BUG-003
- **Severidade:** Média-Alta
- **Componente Afetado:** `Currencies_Controller`
- **Rota Afetada:** `POST /currencies/save`

---

## 1. Descrição Detalhada do Bug

A operação de alterar a moeda base do sistema, realizada no método `save_currency`, envolve uma sequência de múltiplas e distintas operações no banco de dados que dependem umas das outras:

1.  `Quote_Manager::clear_history()`: Executa um `DELETE` na tabela de cotações.
2.  `$wpdb->query("update ... set is_base = 0")`: Executa um `UPDATE` na tabela de moedas para rebaixar a antiga moeda base.
3.  `$currency->save()`: Executa um `INSERT` ou `UPDATE` para salvar a nova moeda base com `is_base = 1`.

O problema é que essas operações não são executadas de forma **atômica**. Elas não estão envoltas em uma transação de banco de dados. Se ocorrer uma falha por qualquer motivo (ex: timeout do PHP, erro fatal, queda do servidor de banco de dados) após o passo 1 ou 2, mas antes do passo 3 ser concluído, o sistema será deixado em um estado corrompido e inconsistente.

### Impacto

- **Sistema Sem Moeda Base:** O cenário de falha mais provável é o sistema ficar sem **nenhuma** moeda com `is_base = 1`. Isso tem o mesmo impacto crítico do bug MM-BUG-002, quebrando relatórios e conversões.
- **Perda de Dados Irreversível:** O histórico de cotações (`quotes`) pode ser apagado (passo 1), mas a operação final de designar a nova moeda base pode falhar, resultando em perda de dados sem a conclusão bem-sucedida da operação principal.
- **Dificuldade de Diagnóstico:** O erro retornado ao usuário pode não indicar claramente que o estado do banco de dados ficou inconsistente, tornando a depuração difícil.

---

## 2. Cenários de Teste e Exemplos

### Cenário: Falha Durante a Troca da Moeda Base

1.  **Preparação:**
    - Tenha uma moeda base "BRL" e uma segunda moeda "USD".
    - Tenha um histórico de cotações na tabela `wp_money_manager_quotes`.
    - Para simular uma falha, seria necessário introduzir um `die()` ou um erro fatal no código do método `save_currency` logo após a chamada `$wpdb->query("update ...")` e antes de `$currency->save()`.

2.  **Ação:**
    - Envie uma requisição `POST` para `/currencies/save` para promover a moeda "USD" a nova moeda base.
    - `{"item": {"id": ID_DO_USD, "is_base": true}}`

3.  **Resultado Esperado (Com Bug e Falha Simulada):**
    - A API retorna um erro 500 ou a conexão é interrompida.
    - A tabela de cotações (`wp_money_manager_quotes`) está vazia, pois o `clear_history()` foi executado.
    - **Nenhuma** linha na tabela `wp_money_manager_currencies` tem o campo `is_base` definido como `1`, pois o `UPDATE` que rebaixa todas as moedas foi executado, mas o `save()` final que promove a nova nunca foi concluído.

4.  **Verificação do Erro:**
    - O sistema está agora em um estado quebrado, sem moeda base e sem histórico de cotações.

---

## 3. Propostas de Correção

### Proposta 1: Usar Transações de Banco de Dados (Recomendado)

**Descrição Detalhada:**
Esta é a solução padrão e correta para garantir a atomicidade de operações de banco de dados multi-passo. Todo o bloco de código que altera a moeda base deve ser envolto em uma transação. Se qualquer uma das operações dentro do bloco falhar, todas as operações anteriores são desfeitas (`ROLLBACK`), garantindo que o banco de dados retorne ao seu estado original e consistente.

**Implementação (em `save_currency`):**

```php
public function save_currency( WP_REST_Request $request )
{
    global $wpdb;
    // ... (código inicial para obter $input e $currency)

    if ( $changing_base_currency ) {
        $wpdb->query( 'START TRANSACTION' ); // Inicia a transação

        try {
            // Se base currency is changing then clear quote history
            Quote_Manager::clear_history();
            $table_name = Currency::table_name();
            $wpdb->query( "update $table_name set is_base = 0" );

            if ( ! $currency->save() ) {
                // Força uma exceção se o save falhar, para acionar o rollback
                throw new \Exception("Falha ao salvar a nova moeda base.");
            }

            $wpdb->query( 'COMMIT' ); // Confirma a transação se tudo deu certo

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' ); // Desfaz tudo se algo deu errado
            // Retorna um erro apropriado para o usuário
            return new WP_Error('rest_transaction_failed', $e->getMessage(), array('status' => 500));
        }
    } else {
        // Se não estiver mudando a base, apenas salva normalmente.
        $currency->save();
    }

    return rest_ensure_response( array( 'result' => 'ok' ) );
}
```

- **Vantagens:**
    - Garante a atomicidade (tudo ou nada), que é a propriedade fundamental para manter a consistência dos dados em operações complexas.
    - É a prática padrão da indústria para este tipo de problema.
- **Desvantagens:**
    - Adiciona um pouco de complexidade com o bloco `try/catch`, mas é uma complexidade necessária e justificada.

### Proposta 2: Reordenar Operações (Incompleto)

**Descrição Detalhada:**
Uma tentativa de mitigar o problema seria reordenar as operações, realizando a mais crítica (salvar a nova moeda base) primeiro. No entanto, isso não resolve o problema fundamental da atomicidade.

1.  Salvar a nova moeda base com `is_base = 1`.
2.  Se o passo 1 for bem-sucedido, rebaixar as outras moedas.
3.  Se o passo 2 for bem-sucedido, limpar o histórico de cotações.

- **Vantagens:**
    - Reduz a chance de o sistema ficar *sem* uma moeda base.
- **Desvantagens:**
    - **Não resolve o problema:** Pode deixar o sistema com **duas** moedas base se a falha ocorrer após o passo 1 e antes do passo 2. A atomicidade ainda é necessária.
    - Apenas muda a natureza do estado inconsistente, não o previne.
