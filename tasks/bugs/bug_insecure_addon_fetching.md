
# Análise de Bug Potencial: Busca Insegura da Lista de Add-ons

- **ID do Bug:** MM-BUG-004
- **Severidade:** Alta
- **Componente Afetado:** `Addons_Controller`
- **Rota Afetada:** `GET /addons/list`
- **Tipo de Vulnerabilidade:** CWE-829: Inclusão de Funcionalidade de Fonte Não Confiável

---

## 1. Descrição Detalhada do Bug

O endpoint `GET /addons/list` busca uma lista de add-ons disponíveis fazendo uma requisição HTTP GET para uma URL externa: `https://getmoneymanager.com/addons/info.json`. O conteúdo deste arquivo JSON é então exibido diretamente na área de administração do WordPress do usuário.

O problema de segurança reside no fato de que o plugin confia implicitamente no conteúdo deste arquivo JSON. Não há nenhum mecanismo para verificar a integridade ou a autenticidade do arquivo recebido. 

Isso cria um vetor de ataque. Se um ator malicioso conseguir comprometer o servidor `getmoneymanager.com` ou executar um ataque Man-in-the-Middle (MITM) na conexão do usuário, ele poderá alterar o conteúdo do `info.json` para incluir um add-on falso com um `download_link` apontando para um arquivo ZIP contendo malware.

O administrador do site veria este add-on malicioso como uma opção legítima em sua interface e poderia ser levado a instalá-lo, comprometendo todo o site WordPress.

### Impacto

- **Execução Remota de Código (RCE):** Um invasor pode enganar um administrador para que instale um plugin com código malicioso, dando ao invasor controle total sobre o site.
- **Roubo de Dados:** O plugin malicioso pode ser projetado para roubar dados sensíveis do banco de dados do WordPress (usuários, senhas, dados de clientes, etc.).
- **Perda de Confiança:** A exploração desta vulnerabilidade pode minar a confiança dos usuários no plugin Money Manager.

---

## 2. Cenários de Teste e Exemplos

### Cenário: Ataque Man-in-the-Middle (MITM)

1.  **Preparação:**
    - O invasor se posiciona entre o servidor WordPress do usuário e a internet (ex: em uma rede Wi-Fi pública não segura).
    - O invasor intercepta as requisições HTTP.

2.  **Ação:**
    - O administrador do site acessa a página de Add-ons no painel do Money Manager.
    - O plugin faz uma requisição para `https://getmoneymanager.com/addons/info.json`.
    - O invasor intercepta esta requisição e, em vez de repassar a resposta legítima, envia uma resposta JSON modificada.

3.  **JSON Malicioso Injetado:**

    ```json
    {
        "result": [
            {
                "name": "Legitimate Add-on",
                ...
            },
            {
                "name": "Performance Optimizer - URGENT UPDATE!",
                "slug": "wp-performance-booster",
                "description": "Otimizador de performance falso que contém malware.",
                "version": "3.0",
                "download_link": "https://servidor-do-invasor.com/malware.zip"
            }
        ]
    }
    ```

4.  **Resultado Esperado (Com Bug):**
    - A interface do Money Manager exibe o add-on "Performance Optimizer" como uma opção válida e urgente.
    - O administrador, acreditando ser uma atualização de segurança ou performance, clica em "Instalar".
    - O endpoint `POST /addons/install` é chamado com o `download_link` malicioso.
    - O WordPress baixa e instala o `malware.zip`, comprometendo o site.

---

## 3. Propostas de Correção

### Proposta 1: Verificação de Checksum (Soma de Verificação)

**Descrição Detalhada:**
Esta abordagem adiciona uma camada de verificação de integridade. O arquivo `info.json` no servidor remoto precisaria ser modificado para incluir um checksum (hash SHA-256) para cada arquivo de add-on.

**`info.json` Modificado:**
```json
{
    "name": "Advanced Reports",
    "slug": "money-manager-reports-pro",
    "download_link": ".../reports-pro.zip",
    "checksum": "a1b2c3d4...e5f6" // Hash SHA-256 do arquivo reports-pro.zip
}
```

O método `install_addon` seria modificado para:
1.  Receber o `download_link` e o `checksum` esperado.
2.  Baixar o arquivo `.zip` para um local temporário.
3.  Calcular o hash SHA-256 do arquivo baixado.
4.  Comparar o hash calculado com o `checksum` esperado.
5.  Se os hashes corresponderem, prosseguir com a instalação. Se não, apagar o arquivo e retornar um erro de falha na verificação de integridade.

- **Vantagens:** Relativamente simples de implementar e protege eficazmente contra a corrupção de arquivos durante o download.
- **Desvantagens:** Não protege contra o comprometimento do próprio arquivo `info.json`. Se o JSON for alterado, o invasor pode simplesmente colocar o hash do seu arquivo malicioso no campo `checksum`.

### Proposta 2: Assinaturas Digitais (Recomendado)

**Descrição Detalhada:**
Esta é a solução mais robusta, pois verifica a **autenticidade** e a **integridade**. 

1.  **No Servidor:** O desenvolvedor do plugin gera um par de chaves (pública/privada), por exemplo, usando GPG ou Ed25519.
2.  A chave pública é embutida no código do plugin Money Manager.
3.  Ao gerar o `info.json`, o servidor também gera um arquivo de assinatura (ex: `info.json.sig`) assinando o conteúdo do `info.json` com a chave privada.
4.  **No Cliente (`list_addons`):**
    - O plugin baixa tanto o `info.json` quanto o `info.json.sig`.
    - Ele usa a chave pública embutida para verificar se a assinatura em `info.json.sig` é válida para o conteúdo do `info.json`.
    - Se a assinatura for inválida, a resposta é descartada e um erro é exibido. Se for válida, a interface é renderizada.

- **Vantagens:**
    - **Proteção Completa:** Garante que o `info.json` não foi alterado (integridade) e que ele realmente veio do desenvolvedor legítimo (autenticidade).
    - É o padrão da indústria para atualizações de software seguras (usado por gerenciadores de pacotes como APT, YUM, etc.).
- **Desvantagens:**
    - Mais complexo de implementar, pois requer o gerenciamento de chaves criptográficas e a adição de uma biblioteca de verificação de assinatura no plugin.
