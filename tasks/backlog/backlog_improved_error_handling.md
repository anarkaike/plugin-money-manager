
# Proposta de Melhoria: Padronização e Melhoria das Mensagens de Erro da API

- **ID da Tarefa:** MM-BKLOG-004
- **Tipo:** Melhoria de Qualidade da API / Experiência do Desenvolvedor
- **Componentes Afetados:** Todos os Controllers e Rotas que retornam erros.

---

## 1. Descrição do Problema

Atualmente, o tratamento de erros na API do Money Manager é inconsistente e, em muitos casos, não segue as melhores práticas de APIs REST. Os principais problemas são:

- **Mensagens Genéricas:** Erros como `{"error": {"code": "ERROR_ERROR"}}` ou `{"error": {"code": "RECORD_NOT_FOUND"}}` são comuns. Embora o código seja útil para o desenvolvedor do plugin, ele não é amigável para o cliente da API (o front-end ou um desenvolvedor externo).
- **Status HTTP Incorretos:** Em alguns casos, erros são retornados com um status HTTP `200 OK` (ex: `RECORD_NOT_FOUND` no `Accounts_Controller`), quando o correto seria um status de erro (ex: `404 Not Found`). Isso dificulta a detecção de falhas por parte do cliente da API.
- **Falta de Detalhes:** As mensagens de erro não fornecem detalhes suficientes sobre o que deu errado, dificultando a depuração por parte do cliente.
- **Não Internacionalizadas:** As mensagens de erro não são internacionalizadas, o que significa que não podem ser facilmente traduzidas para diferentes idiomas.

## 2. Proposta de Implementação

A proposta é padronizar o retorno de erros da API, seguindo as diretrizes da API REST do WordPress e as melhores práticas de APIs RESTful.

### Passo 1: Utilizar `WP_Error` Consistentemente

Todos os retornos de erro devem ser feitos utilizando a classe `WP_Error` do WordPress, que permite definir um código de erro, uma mensagem legível e dados adicionais, além de garantir que o status HTTP correto seja enviado.

### Passo 2: Mapear Códigos de Erro para Mensagens e Status HTTP

Criar um mapeamento claro entre os códigos de erro internos (como `RECORD_NOT_FOUND`) e mensagens de erro legíveis e seus respectivos status HTTP.

**Exemplo de Implementação (em `Accounts_Controller` para `save_account`):**

**Antes:**
```php
// ...
if ( ! $account ) {
    return rest_ensure_response( array( 'error' => array( 'code' => 'RECORD_NOT_FOUND' ) ) );
}
// ...
```

**Depois:**
```php
// ...
if ( ! $account ) {
    return new WP_Error(
        'money_manager_account_not_found', // Código de erro único e específico
        __( 'Conta não encontrada.', 'money-manager' ), // Mensagem internacionalizada
        array( 'status' => 404 ) // Status HTTP correto
    );
}
// ...
```

### Passo 3: Adicionar Detalhes Contextuais

Sempre que possível, incluir detalhes adicionais no array `data` do `WP_Error` que ajudem o cliente a entender o problema. Por exemplo, para erros de validação, indicar qual campo falhou.

### Passo 4: Internacionalização (i18n)

Todas as mensagens de erro legíveis devem ser envolvidas em funções de internacionalização do WordPress (ex: `__()` ou `_e()`) para permitir a tradução.

## 3. Impacto da Melhoria

- **Melhor Experiência do Desenvolvedor (DX):** Desenvolvedores que consomem a API terão muito mais facilidade em entender o que deu errado e como corrigir seus pedidos, reduzindo o tempo de depuração.
- **Melhor Experiência do Usuário (UX):** O front-end poderá exibir mensagens de erro mais claras e úteis para o usuário final, melhorando a usabilidade do aplicativo.
- **Consistência:** A API se tornará mais consistente em seu comportamento de erro, tornando-a mais previsível e confiável.
- **Conformidade com Padrões:** A API estará mais alinhada com as melhores práticas de APIs RESTful e com o comportamento esperado da API REST do WordPress.
- **Esforço de Implementação:** Requer uma revisão de todos os pontos onde erros são retornados, mas o benefício a longo prazo justifica o esforço.
