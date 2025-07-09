
# Documentação da API do Plugin Money Manager

Bem-vindo à documentação completa da API REST do plugin Money Manager. Esta documentação detalha todos os endpoints disponíveis, seus parâmetros, fluxos de execução e exemplos de uso.

A API é organizada em torno de "Controllers", cada um responsável por uma área específica do plugin.

Navegue pelos controllers abaixo para ver os detalhes de cada endpoint.

---

## Índice de Controllers

- **[App](./app.md)**
  - Endpoints para carregar os dados iniciais da aplicação e salvar metadados do usuário.

- **[Accounts](./accounts.md)**
  - Gerenciamento de Contas (criar, listar, atualizar, remover).

- **[Transactions](./transactions.md)**
  - Gerenciamento de Transações, incluindo listagem com filtros avançados, anexos de arquivos e importação em massa.

- **[Categories](./categories.md)**
  - Gerenciamento de Categorias e Subcategorias para classificar as transações.

- **[Parties](./parties.md)**
  - Gerenciamento de Favorecidos (lojas, pessoas, etc.) envolvidos nas transações.

- **[Currencies](./currencies.md)**
  - Gerenciamento das Moedas e da Moeda Base do sistema.

- **[Quotes](./quotes.md)**
  - Gerenciamento do histórico de Cotações de Câmbio, incluindo a busca de dados em APIs externas.

- **[Reports](./reports.md)**
  - Endpoints para gerar relatórios agregados de fluxo de caixa e receitas/despesas com conversão de moeda.

- **[Add-ons](./addons.md)**
  - Endpoints para gerenciar os add-ons do plugin (instalar, ativar, desativar).

- **[WooCommerce](./woocommerce.md)**
  - Endpoint para configurar a integração com o WooCommerce, que automatiza a criação de transações a partir de pedidos da loja.
