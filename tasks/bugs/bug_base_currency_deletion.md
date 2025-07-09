
# Análise de Bug Potencial: Exclusão da Moeda Base Não é Impedida

- **ID do Bug:** MM-BUG-002
- **Severidade:** Crítica
- **Componente Afetado:** `Currencies_Controller`
- **Rota Afetada:** `POST /currencies/remove`

---

## 1. Descrição Detalhada do Bug

O sistema de múltiplas moedas do plugin é construído sobre o conceito de uma única **Moeda Base** (`is_base = true`), que serve como pivô para todas as conversões de taxa de câmbio. Toda a lógica de relatórios e cálculo de saldos consolidados depende da existência e unicidade desta moeda base.

O endpoint `POST /currencies/remove` permite a exclusão de qualquer moeda através de seu ID. No entanto, o código não implementa nenhuma verificação para impedir que a moeda atualmente definida como base seja excluída. 

Se um usuário, por engano ou intencionalmente, excluir a moeda base, o sistema entrará em um estado indefinido e provavelmente disfuncional, pois não haverá mais um ponto de referência para os cálculos de conversão.

### Impacto

- **Quebra de Relatórios:** Qualquer relatório que envolva conversão de moeda (`/reports/cash-flow`, etc.) irá falhar ou produzir resultados completamente incorretos, pois a lógica de conversão não encontrará uma referência.
- **Inconsistência de Dados:** O sistema ficará sem uma moeda de referência, tornando impossível calcular valores totais de patrimônio ou comparar valores de contas com moedas diferentes.
- **Erros Fatais em Potencial:** Dependendo de como outras partes do código lidam com a ausência de uma moeda base, isso pode levar a erros fatais (PHP Fatal Errors) em várias partes do plugin, tornando-o inutilizável até que uma nova moeda base seja definida manualmente no banco de dados.

---

## 2. Cenários de Teste e Exemplos

### Cenário: Excluindo a Moeda Base

1.  **Preparação:**
    - Verifique no endpoint `/currencies/list` qual moeda está com `"is_base": true`. Suponha que seja a moeda com `id: 1` e `code: "BRL"`.
    - Tenha pelo menos uma outra moeda, como "USD" (`id: 2`).
    - Tenha contas em BRL e USD.

2.  **Ação:**
    - Envie uma requisição `POST` para `/currencies/remove` com o corpo `{"id": 1}`.

3.  **Resultado Esperado (Com Bug):**
    - A API retorna `{"result": "ok"}` (status 200), indicando que a operação foi um sucesso.
    - A moeda "BRL" é removida da tabela `wp_money_manager_currencies`.
    - O sistema agora não possui NENHUMA moeda com `is_base = true`.

4.  **Verificação do Erro:**
    - Faça uma requisição `GET` para `/reports/cash-flow?currency=USD`. A consulta SQL para conversão de moeda provavelmente falhará, pois a lógica `coalesce(tmp.quote1, tmp.default_quote1) / coalesce(tmp.quote2, tmp.default_quote2)` não encontrará a referência da moeda base para `quote2`.
    - A resposta do endpoint de relatório será um erro 500 ou um resultado vazio/incorreto.

---

## 3. Propostas de Correção

### Proposta 1: Impedir a Exclusão (Recomendado)

**Descrição Detalhada:**
Esta é a solução mais simples e segura. Antes de executar a exclusão, o método `remove_currency` deve buscar a moeda no banco de dados e verificar o valor do seu campo `is_base`.

Se `is_base` for `true`, a operação deve ser abortada e um erro claro deve ser retornado ao cliente, informando que a moeda base não pode ser excluída.

**Implementação (em `remove_currency`):**

```php
public function remove_currency( WP_REST_Request $request )
{
    $id = $request->get_param( 'id' );

    // Buscar a moeda antes de deletar
    $currency = Currency::find( $id );

    if ( ! $currency ) {
        // Comportamento padrão, não há o que deletar.
        return rest_ensure_response( array( 'result' => 'ok' ) );
    }

    // Adicionar a verificação de segurança
    if ( $currency->is_base ) {
        return new WP_Error(
            'rest_cannot_delete_base_currency',
            'A moeda base não pode ser excluída. Defina outra moeda como base antes de tentar excluir esta.',
            array( 'status' => 409 ) // 409 Conflict
        );
    }

    Currency::destroy( $id );

    return rest_ensure_response( array( 'result' => 'ok' ) );
}
```

- **Vantagens:**
    - Resolve o bug de forma completa e segura.
    - É muito simples de implementar.
    - Fornece feedback claro ao usuário sobre por que a ação falhou e como proceder.
- **Desvantagens:**
    - Nenhuma significativa.

### Proposta 2: Promover Automaticamente Outra Moeda

**Descrição Detalhada:**
Uma abordagem muito mais complexa seria, ao tentar excluir a moeda base, o sistema automaticamente promover outra moeda para se tornar a nova base. Por exemplo, a próxima moeda em ordem alfabética ou a primeira encontrada na tabela.

- **Vantagens:**
    - O sistema nunca fica sem uma moeda base.
- **Desvantagens:**
    - **Comportamento Mágico e Imprevisível:** O usuário pode não perceber que, ao excluir uma moeda, outra foi promovida e todo o sistema de cotações foi recalculado (ou invalidado), pois a promoção de uma nova base pode apagar o histórico de cotações (conforme a lógica de `save_currency`).
    - **Complexidade de Implementação:** A lógica para escolher a "próxima" moeda base pode não ser trivial e pode levar a resultados inesperados.
    - **Não Recomendado:** Esta abordagem viola o princípio do menor espanto (Principle of Least Astonishment), onde a ação de "deletar" tem um efeito colateral massivo e não óbvio.
