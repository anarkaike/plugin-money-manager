
# Proposta de Melhoria: Consolidar Endpoints de Configurações

- **ID da Tarefa:** MM-BKLOG-005
- **Tipo:** Melhoria de Arquitetura / Manutenibilidade
- **Componentes Afetados:** `WooCommerce_Controller`, `App_Controller`
- **Rotas Afetadas:** `POST /woocommerce/save`, `POST /app/save-meta`

---

## 1. Descrição do Problema

Atualmente, as configurações do plugin Money Manager estão dispersas em diferentes endpoints:

- `POST /woocommerce/save`: Salva as configurações de integração com o WooCommerce.
- `POST /app/save-meta`: Salva metadados específicos do usuário (preferências de UI, etc.).

Essa dispersão pode levar a:

- **Dificuldade de Gerenciamento:** Para o desenvolvedor do front-end, é necessário saber onde cada tipo de configuração é salvo e como interagir com múltiplos endpoints para gerenciar as configurações.
- **Falta de Visibilidade:** Não há um ponto central para ver ou gerenciar todas as configurações do plugin de forma programática.
- **Potencial de Inconsistência:** Se novas configurações forem adicionadas no futuro, elas podem acabar em novos endpoints, aumentando a complexidade.

## 2. Proposta de Implementação

A proposta é criar um endpoint unificado para gerenciar todas as configurações do plugin, tanto as globais quanto as específicas do usuário, através de um novo `Settings_Controller`.

### Passo 1: Criar um Novo `Settings_Controller`

Este novo controller seria responsável por todas as operações de configuração.

### Passo 2: Implementar Endpoints `GET /settings` e `POST /settings`

- **`GET /settings`:**
    - Retornaria um objeto JSON consolidado contendo todas as configurações do plugin (globais e do usuário logado).
    - As configurações globais seriam lidas de `wp_options` (ex: `money_manager_woocommerce`).
    - As configurações do usuário seriam lidas de `wp_usermeta` (ex: `money_manager`).
    - O objeto retornado seria uma representação completa do estado configurável do plugin.

- **`POST /settings`:**
    - Aceitaria um objeto JSON no corpo da requisição que representaria o estado completo das configurações a serem salvas.
    - O controller seria responsável por parsear este objeto e salvar as partes relevantes em `wp_options` (para configurações globais) e `wp_usermeta` (para configurações do usuário).

### Passo 3: Refatorar Endpoints Existentes

- Os endpoints `POST /woocommerce/save` e `POST /app/save-meta` seriam removidos ou marcados como obsoletos, com a funcionalidade migrada para o novo `POST /settings`.

### Exemplo de Estrutura de Requisição para `POST /settings`:

```json
{
  "global": {
    "woocommerce": {
      "enabled": true,
      "account_id": 1
    },
    "general_options": {
      "default_currency": "BRL"
    }
  },
  "user": {
    "dark_mode": true,
    "last_report_viewed": "cash-flow"
  }
}
```

## 3. Impacto da Melhoria

- **Centralização e Clareza:** Fornece um ponto único e claro para gerenciar todas as configurações do plugin, tanto para o front-end quanto para outros desenvolvedores.
- **Manutenibilidade Aprimorada:** Adicionar novas configurações no futuro se torna mais simples, pois há um padrão e um local definidos para elas.
- **Redução de Código Duplicado:** Evita a duplicação de lógica de leitura/escrita de opções e meta dados em múltiplos controllers.
- **Melhor Experiência do Desenvolvedor (DX):** Simplifica a interação com as configurações do plugin, tornando a API mais intuitiva.
- **Esforço de Implementação:** Requer a criação de um novo controller e a refatoração dos endpoints existentes, além de uma migração de dados se a estrutura de armazenamento for alterada significativamente.
