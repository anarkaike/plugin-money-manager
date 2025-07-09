
# Proposta de Melhoria: Refatorar a Geração de Consultas de Relatório

- **ID da Tarefa:** MM-BKLOG-003
- **Tipo:** Melhoria de Código / Manutenibilidade
- **Componente Afetado:** `Reports_Controller`
- **Método Principal:** `get_report_data()`

---

## 1. Descrição do Problema

O método `get_report_data` no `Reports_Controller` é o coração da funcionalidade de relatórios do plugin. Atualmente, ele constrói uma única e massiva consulta SQL através da concatenação de strings. A consulta final inclui múltiplas sub-consultas correlacionadas para buscar cotações de moeda, junções (`joins`) e uma lógica condicional complexa.

Este tipo de abordagem apresenta vários problemas:

- **Baixa Legibilidade:** A consulta é extremamente difícil de ler e entender. Um novo desenvolvedor levaria um tempo considerável para decifrar o que ela faz.
- **Dificuldade de Manutenção:** Se for necessário adicionar um novo filtro, um novo agrupamento ou alterar a lógica de conversão de moeda, modificar a string da consulta sem introduzir erros é uma tarefa de alto risco.
- **Depuração Complexa:** Quando a consulta retorna um resultado inesperado ou um erro de sintaxe, depurar a string SQL final é um processo manual e penoso.
- **Performance:** Embora consultas monolíticas possam ser rápidas, as sub-consultas correlacionadas (que buscam a cotação para cada linha de transação individualmente) podem, na verdade, ser menos performáticas do que abordagens alternativas em certos cenários de banco de dados.

## 2. Proposta de Implementação

A proposta é refatorar o método `get_report_data` para decompor a lógica complexa em passos mais gerenciáveis, priorizando a clareza e a manutenibilidade sobre a compactação do código.

### Abordagem Sugerida: Múltiplas Consultas e Processamento em PHP

Em vez de uma única consulta gigante, a lógica seria dividida em etapas:

1.  **Buscar Transações Relevantes:**
    - Execute uma consulta SQL mais simples para buscar todas as transações que se encaixam nos filtros de data (`range`) e conta (`account_id`).
    - Esta consulta selecionaria os campos principais: `id`, `date`, `amount`, `currency`, `category_id`, `party_id`, etc.

2.  **Buscar Cotações Relevantes:**
    - Com base nas datas e moedas das transações retornadas no passo 1, execute uma segunda consulta para buscar **todas** as cotações de moeda necessárias para o período em questão de uma só vez.
    - O resultado seria um array associativo, fácil de consultar, como `quotes[DATE][CURRENCY_CODE] = value`.

3.  **Processar e Converter em PHP:**
    - Itere (faça um loop) sobre o array de transações obtido no passo 1.
    - Para cada transação, use o array de cotações (passo 2) para encontrar a cotação correta para sua data e moeda e fazer a conversão para a moeda do relatório.
    - Agregue os resultados convertidos em um array PHP, agrupando por mês ou favorecido, conforme solicitado pelo parâmetro `group_by`.

**Exemplo de Esboço do Novo Código:**

```php
protected function get_report_data( $currency, $range, $group_by, ... )
{
    // Passo 1: Buscar transações com filtros de data/conta
    $transactions = $this->fetch_transactions_for_range( $range, $account_id );

    if ( empty( $transactions ) ) {
        return ['income' => [], 'expenses' => []];
    }

    // Passo 2: Buscar todas as cotações necessárias de uma vez
    $min_date = min( array_column( $transactions, 'date' ) );
    $max_date = max( array_column( $transactions, 'date' ) );
    $currency_codes = array_unique( array_column( $transactions, 'currency' ) );
    $quotes_map = Quote_Manager::get_quotes_map_for_period( $min_date, $max_date, $currency_codes );

    // Passo 3: Processar em PHP
    $report_data = ['income' => [], 'expenses' => []];
    foreach ( $transactions as $txn ) {
        // Lógica de conversão usando o $quotes_map
        $converted_amount = $this->convert_currency( $txn->amount, $txn->currency, $currency, $txn->date, $quotes_map );

        // Lógica de agregação e agrupamento em $report_data
        // ...
    }

    return $report_data;
}
```

## 3. Impacto da Melhoria

- **Manutenibilidade:** Este é o maior ganho. Cada etapa da lógica (buscar transações, buscar cotações, converter, agregar) fica isolada em seu próprio método, tornando o código muito mais fácil de entender, modificar e estender no futuro.
- **Facilidade de Depuração:** É possível depurar o resultado de cada etapa de forma independente. Pode-se verificar se as transações corretas foram buscadas antes de se preocupar com a conversão, por exemplo.
- **Clareza do Código:** O código se torna auto-documentado, pois a separação das responsabilidades deixa o fluxo de dados muito mais claro.
- **Potencial de Performance:** Embora possa parecer menos eficiente fazer mais de uma consulta, essa abordagem pode na verdade ser mais rápida. Ela substitui sub-consultas correlacionadas (lentas) por `joins` ou consultas `WHERE IN (...)` em conjuntos de dados pré-filtrados, que os otimizadores de banco de dados geralmente lidam melhor.
- **Reusabilidade:** Métodos como `get_quotes_map_for_period` podem ser reutilizados em outras partes do plugin que também precisem de dados de cotação.
