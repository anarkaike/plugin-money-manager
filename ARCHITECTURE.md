# Visão Geral da Estrutura do Projeto

Este documento explica o propósito de cada pasta e arquivo encontrados no plugin WordPress `money-manager`. As informações foram obtidas a partir da leitura direta do código-fonte.

## Arquivos na raiz

| Arquivo | Função |
| --- | --- |
| `money-manager.php` | Ponto de entrada principal do plugin. Registra o autoloader para o namespace `MoneyManager` e inicia a aplicação via `MoneyManager()->run()`. |
| `class-app.php` | Classe central que inicializa hooks, controladores REST, tarefas agendadas e callbacks de ativação/desativação. |
| `class-i18n.php` | Contém um grande array de textos traduzidos utilizados para localizar a aplicação JavaScript. |
| `class-install.php` | Responsável pela instalação e remoção do plugin. Cria ou remove tabelas no banco e opções iniciais. |
| `class-multisite.php` | Adiciona suporte a instalações multisite, permitindo ativação/desativação quando sites são criados ou removidos. |
| `class-page.php` | Auxiliar abstrato para páginas administrativas. Fornece lógica para registrar submenus e carregar scripts/estilos. |
| `class-sample-data.php` | Gera dados de exemplo (contas, categorias, partes, transações) usados na primeira execução. |
| `class-update.php` | Lógica de migração do banco de dados. Executa atualizações incrementais para manter o esquema em sincronia com a versão do plugin. |
| `readme.txt` | Readme do WordPress contendo metadados do plugin e descrição pública. |

## Diretórios

### `controllers`
Implementa a camada de API REST. Cada controlador estende `class-base-controller.php` e registra endpoints sob o namespace `money-manager/v1`.

### `managers`
Classes de serviço que encapsulam tarefas em segundo plano ou lógica específica de domínio (ex.: atualização de saldo, busca de cotações, integração com WooCommerce).

### `models`
Modelos no estilo ActiveRecord que encapsulam as tabelas de banco de dados. Proporcionam operações CRUD e conversão de campos.

### `pages`
Páginas administrativas renderizadas no painel do WordPress. Elas exibem pequenos contêineres onde a aplicação JavaScript ou outro HTML é inserido.

### `views`
Templates PHP contendo pequenos trechos de HTML, usados principalmente na tela de boas‑vindas.

### `css` / `js` / `webfonts`
Arquivos estáticos utilizados pela interface administrativa. Os scripts JavaScript já se encontram empacotados/minificados. As webfonts armazenam ícones FontAwesome usados na interface.

## Resumo de arquivos por pasta

A seguir está uma breve descrição dos arquivos existentes em cada pasta principal.

### controllers
- **class-base-controller.php** – Fornece utilidades para registrar rotas REST e verificar permissões.
- **class-accounts-controller.php** – CRUD de contas.
- **class-addons-controller.php** – Interage com o marketplace do plugin: lista, instala, ativa e desativa complementos.
- **class-app-controller.php** – Carrega dados iniciais da aplicação e salva configurações do usuário (meta).
- **class-categories-controller.php** – CRUD para categorias de transações.
- **class-currencies-controller.php** – CRUD de moedas com lógica para mudança da moeda base.
- **class-parties-controller.php** – CRUD de partes/contatos.
- **class-quotes-controller.php** – Ações de cotações de moeda: carregar, listar, atualizar, buscar histórico e limpar histórico.
- **class-reports-controller.php** – Gera relatórios de fluxo de caixa e de receitas/despesas.
- **class-transactions-controller.php** – Responsável por listar, salvar, excluir e importar transações via CSV.
- **class-woocommerce-controller.php** – Salva configurações de integração com o WooCommerce.

### managers
- **class-account-manager.php** – Calcula e atualiza saldos das contas com base nas transações.
- **class-file-manager.php** – Sincroniza anexos do WordPress com os registros de arquivos do plugin.
- **class-quote-manager.php** – Busca cotações de moedas no Yahoo! Finance e mantém histórico.
- **class-woocommerce-manager.php** – Detecta o WooCommerce, monitora alterações em pedidos e cria transações conforme necessário.

### models
Cada modelo corresponde a uma tabela do banco de dados e define campos preenchíveis e regras de conversão.
- **class-account.php** – Representa uma conta bancária ou outro ativo.
- **class-budget.php** – Valores de orçamento anuais ou mensais.
- **class-category.php** – Categorias de transações com relacionamento pai opcional.
- **class-currency.php** – Códigos de moeda e indicador da moeda base.
- **class-file.php** – Metadados de anexos associados às transações.
- **class-notification.php** – Notificações agendadas para transações recorrentes.
- **class-party.php** – Contrapartes como clientes ou fornecedores.
- **class-quote.php** – Taxas de câmbio armazenadas por dia.
- **class-transaction.php** – Transações financeiras envolvendo contas e categorias.

### pages
- **class-addons-page.php** – Renderiza a tela de complementos e passa opções de JS informando os complementos instalados.
- **class-home-page.php** – Página principal do aplicativo. Pode acionar a importação de dados de exemplo na primeira utilização e carrega o pacote JavaScript com configurações de localização.
- **class-welcome-page.php** – Tela inicial de boas‑vindas com links para importar dados de exemplo ou abrir o aplicativo principal.

### views
- **welcome.php** – HTML da página de boas‑vindas exibida logo após a ativação. Oferece botões para importar dados de exemplo ou começar a usar a aplicação.

### css/js/webfonts
Armazenam estilos CSS compilados, pacotes JavaScript e fontes web do FontAwesome utilizadas na interface administrativa.


**Observação:** toda documentação deste repositório deve permanecer em Português (Brasil).
