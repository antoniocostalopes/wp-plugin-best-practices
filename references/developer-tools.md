# Ferramentas de Desenvolvimento

Referência de ambiente, depuração, testes e CI. Use ao montar ambiente local, diagnosticar problemas ou preparar a publicação.

Handbook: https://developer.wordpress.org/plugins/developer-tools/

## Conteúdo

- [Ambiente local](#ambiente-local)
- [Constantes de debug (`wp-config.php`)](#constantes-de-debug-wp-configphp)
- [In-browser](#in-browser)
- [WP-CLI](#wp-cli)
- [Qualidade estática](#qualidade-estática)
- [Testes](#testes)
- [CI — GitHub Actions mínimo](#ci--github-actions-mínimo)
- [Debug de plugin em produção (com cuidado)](#debug-de-plugin-em-produção-com-cuidado)
- [Preview no diretório: `blueprint.json`](#preview-no-diretório-blueprintjson)
- [Servidor MCP do WordPress.org](#servidor-mcp-do-wordpressorg)
- [Ordem de diagnóstico](#ordem-de-diagnóstico)

## Ambiente local

| Ferramenta | Para quê | Comando |
|---|---|---|
| **wp-env** (oficial, Docker) | Ambiente WP descartável por plugin, com versão de WP/PHP fixada | `npx @wordpress/env start` |
| **wp-now** | Arranque instantâneo sem Docker (SQLite/PHP embutido) | `npx @wp-now/wp-now start` |
| **WP Playground** | WP no browser (WASM) — demos, testes de PR e o botão "Preview" do diretório | https://playground.wordpress.net/ |
| **LocalWP / DDEV / Lando** | Ambiente completo com mail catcher, SSL, multisite | — |

### `.wp-env.json` mínimo

```json
{
  "core": "WordPress/WordPress#6.6",
  "phpVersion": "8.2",
  "plugins": [ "." ],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "WP_DEBUG_DISPLAY": false,
    "SCRIPT_DEBUG": true,
    "SAVEQUERIES": true
  },
  "env": {
    "tests": { "config": { "WP_DEBUG": true } }
  }
}
```

`npx wp-env run cli wp plugin list` corre WP-CLI dentro do container.

## Constantes de debug (`wp-config.php`)

| Constante | Efeito | Em dev |
|---|---|---|
| `WP_DEBUG` | Ativa notices/deprecations | `true` |
| `WP_DEBUG_LOG` | Escreve em `wp-content/debug.log` (ou caminho dado) | `true` |
| `WP_DEBUG_DISPLAY` | Mostra erros no HTML | `false` (quebra JSON/REST/AJAX) |
| `SCRIPT_DEBUG` | Carrega JS/CSS do core não minificados | `true` |
| `SAVEQUERIES` | Guarda queries em `$wpdb->queries` | `true` (pesado; só dev) |
| `WP_DISABLE_FATAL_ERROR_HANDLER` | Desliga o "recovery mode" que esconde fatais | `true` em dev |
| `WP_ENVIRONMENT_TYPE` | `local`/`development`/`staging`/`production` | ler com `wp_get_environment_type()` |

Use `wp_get_environment_type()` no plugin para ligar logging verboso só fora de produção — nunca `WP_DEBUG` como proxy de "é dev".

## In-browser

| Ferramenta | Diagnostica |
|---|---|
| **Query Monitor** | Queries lentas/duplicadas, hooks executados, chamadas HTTP, erros PHP, caps verificadas, REST, transients. Instalação obrigatória em dev. |
| **Debug Bar** + add-ons | Base extensível; cada add-on resolve um problema específico (tabela abaixo) |
| **Plugin Check** (`plugin-check`) | Corre as verificações oficiais do WP.org **antes** da submissão |
| **Health Check & Troubleshooting** | Desliga plugins só para a sua sessão — isola conflitos sem afetar visitantes |
| **User Switching** | Testar caps/roles sem logout |
| **WP Crontrol** | Ver, correr e apagar eventos de cron |

Query Monitor mostra a cap exata verificada em cada `current_user_can` — atalho para auditar `references/capabilities.md`.

### Debug Bar: escolher o add-on pelo sintoma

O Debug Bar base mostra queries e cache. Com `WP_DEBUG` ativo passa também a registar Warnings e Notices; com `SAVEQUERIES` ativo mostra as queries MySQL. Cada add-on acrescenta um painel:

| Sintoma / pergunta | Add-on |
|---|---|
| "Que shortcodes estão registados, que função chamam e em que posts são usados?" | Debug Bar Shortcodes |
| "Que CPT/taxonomias estão registados e com que args?" | Debug Bar Post Types |
| "O meu cron corre? Quando é o próximo evento? Que schedules existem?" | Debug Bar Cron |
| "Que actions e filters correram neste request, com que prioridade?" | Debug Bar Actions and Filters Addon |
| "Que transients existem e qual está preso?" (permite apagar) | Debug Bar Transients |
| "Que scripts/styles carregam, por que ordem e com que dependências?" | Debug Bar List Script & Style Dependencies |
| "Que chamadas HTTP saem e quanto demoram?" (`?dbrr_full=1` mostra o dump completo) | Debug Bar Remote Requests |
| "Que constantes estão definidas neste request?" | Debug Bar Constants |
| "Quero correr PHP arbitrário no contexto deste site" | Debug Bar Console |

Se só instalar um: Query Monitor cobre a maioria destes painéis num único plugin. O Debug Bar compensa quando precisa de um destes ângulos específicos (shortcodes, dependências de assets, consola PHP).

⚠️ **Debug Bar Console executa PHP arbitrário a partir do admin.** Nunca em produção; desinstale antes de publicar o site.

## WP-CLI

```bash
wp scaffold plugin acme-widgets --plugin_name="Acme Widgets"   # esqueleto + testes
wp plugin check acme-widgets                                    # Plugin Check em CLI
wp i18n make-pot . languages/acme-widgets.pot                    # extrair strings
wp i18n make-json languages/ --no-purge                          # traduções JS
wp cron event list                                               # ver cron agendado
wp transient delete --all                                        # limpar transients
wp db query "SELECT option_name, LENGTH(option_value) FROM wp_options WHERE autoload='yes' ORDER BY 2 DESC LIMIT 20"
wp profile stage --all                                           # (add-on) onde o tempo vai
wp eval-file scripts/debug.php                                   # correr PHP no contexto WP
```

## Qualidade estática

```bash
composer require --dev wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
composer require --dev szepeviktor/phpstan-wordpress phpstan/extension-installer
composer require --dev phpcompatibility/phpcompatibility-wp

vendor/bin/phpcs --standard=phpcs.xml.dist .
vendor/bin/phpcbf --standard=phpcs.xml.dist .        # auto-fix
vendor/bin/phpstan analyse --level=6 src/
vendor/bin/phpcs -p . --standard=PHPCompatibilityWP --runtime-set testVersion 8.0-
```

Ruleset pronto: `templates/phpcs.xml.dist`.

## Testes

```bash
# Com wp-env (traz a test suite do WP)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/acme-widgets vendor/bin/phpunit

# Sem Docker
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
vendor/bin/phpunit
```

`WP_UnitTestCase` dá factories (`$this->factory()->post->create()`), rollback por teste e helpers de hooks. Para testes de unidade puros sem WP a correr, use Brain Monkey ou WP_Mock.

Testes E2E do editor/blocos: `@wordpress/e2e-test-utils-playwright`.

## CI — GitHub Actions mínimo

```yaml
name: CI
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', tools: composer }
      - run: composer install --prefer-dist --no-progress
      - run: vendor/bin/phpcs --standard=phpcs.xml.dist .
      - run: vendor/bin/phpstan analyse --level=6 src/
  plugin-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: wordpress/plugin-check-action@v1   # verificações oficiais do WP.org no PR
```

Para releases no WP.org: `10up/action-wordpress-plugin-deploy` (push da tag para SVN) e `10up/action-wordpress-plugin-asset-update` (banners/ícones/readme).

## Debug de plugin em produção (com cuidado)

**Aviso:** ativar logging verboso ou `WP_DEBUG_DISPLAY` em produção expõe caminhos, queries e possivelmente dados pessoais. Nunca ligar `WP_DEBUG_DISPLAY` num site com tráfego; use `WP_DEBUG_LOG` com caminho fora do webroot e apague o ficheiro no fim.

```php
// Log condicionado ao ambiente, sem dados pessoais.
if ( 'production' !== wp_get_environment_type() ) {
    error_log( sprintf( '[acme] sync falhou para submission #%d', $submission_id ) );
}
```

## Preview no diretório: `blueprint.json`

https://developer.wordpress.org/plugins/wordpress-org/previews-and-blueprints/

O botão **Preview** ao lado do Download abre o plugin num WordPress Playground já configurado. Não aparece por omissão; são precisas duas coisas:

1. `blueprint.json` válido em `assets/blueprints/blueprint.json` (commit por SVN).
2. Um committer marcar o preview como "public" na Advanced view.

Com o ficheiro presente mas sem o passo 2, o botão aparece **só aos committers**, com o rótulo "Test Preview" — serve exatamente para testar antes de abrir ao público. Por agora só é suportado **um** blueprint.

```json
{
  "landingPage": "/wp-admin/edit.php?post_type=acme_order",
  "preferredVersions": { "php": "8.2", "wp": "latest" },
  "phpExtensionBundles": [ "kitchen-sink" ],
  "steps": [
    { "step": "login", "username": "admin", "password": "password" },
    {
      "step": "installPlugin",
      "pluginZipFile": { "resource": "wordpress.org/plugins", "slug": "woocommerce" },
      "options": { "activate": true }
    },
    {
      "step": "installTheme",
      "themeZipFile": { "resource": "wordpress.org/themes", "slug": "twentytwentyfour" }
    },
    { "step": "setSiteOptions", "options": { "acme_demo_mode": "1" } },
    {
      "step": "runPHP",
      "code": "<?php require_once 'wordpress/wp-load.php'; wp_insert_post( [ 'post_title' => 'Encomenda de exemplo', 'post_type' => 'acme_order', 'post_status' => 'publish' ] ); ?>"
    }
  ]
}
```

| Chave | Faz |
|---|---|
| `landingPage` | Onde o utilizador aterra — aponte para o ecrã que mostra o valor do plugin, não para o dashboard |
| `preferredVersions` | Versões de PHP e WP do ambiente |
| `phpExtensionBundles` | `kitchen-sink` disponibiliza as extensões PHP comuns |
| `steps` | Sequência antes de mostrar a página: `login`, `installPlugin`, `installTheme`, `setSiteOptions`, `runPHP` |

`runPHP` é o que transforma um preview vazio numa demo real: crie posts, opções e dados de exemplo. Note o `require_once 'wordpress/wp-load.php'` — sem isso não há funções do WordPress.

A página do plugin oferece um blueprint **gerado automaticamente** ("Test in Playground" + "Download blueprint.json") como ponto de partida — descarregue, ajuste e faça commit.

## Servidor MCP do WordPress.org

Documentação: https://developer.wordpress.org/plugins/wordpress-org/using-the-mcp-server/

Liga o Claude Code (ou Claude Desktop, Cursor, VS Code) diretamente à infraestrutura do diretório de plugins — evita copiar e colar entre o assistente e o WP.org.

```bash
npx -y @wporg/mcp   # deteta o cliente e configura; alternativa: colar o JSON manualmente
```

**Endpoint:** `https://wordpress.org/wp-json/mcp/wporg`
**Autenticação:** application password gerada no fluxo de login do WordPress.org — **mostrada uma única vez**, guarde-a no momento.

| Ferramenta exposta | Faz |
|---|---|
| Validate Readme | Valida `readme.txt` / `readme.md` e devolve erros, avisos e sugestões |
| Get Plugin Status | Estado da revisão e feedback do revisor |
| Submit Plugin | Submete plugin novo, ou atualiza uma submissão ainda em revisão |

Também expõe como recursos consultáveis: Detailed Plugin Guidelines, Plugin Developer FAQ, documentação do readme, campos de header, guia do Plugin Check e a lista de slugs reservados. E prompts guiados: "Prepare Plugin for Submission", instalar/correr o Plugin Check, e "Address Review Feedback".

No VS Code o ficheiro é `.vscode/mcp.json` e a chave de topo é `servers`, não `mcpServers`. Reinicie o cliente depois de gravar.

Fluxos que a documentação sugere pedir ao assistente:

```text
Help me prepare my plugin for submission to WordPress.org.
What's the review status of my plugin "my-awesome-plugin"?
Help me address the review feedback for "my-awesome-plugin".
```

**Troubleshooting:** erro de autenticação normalmente é application password expirada ou revogada — repetir o fluxo de autorização gera uma nova e **substitui** a anterior automaticamente (é preciso atualizar a config do cliente). Para cortar o acesso: profiles.wordpress.org → Account & Security → Application Passwords → apagar a password da ligação MCP.

Limites que a documentação sublinha:

- **Depois de aprovado, as atualizações vão por SVN** — o MCP não publica releases.
- Corra o **Plugin Check localmente antes** de submeter; o MCP não substitui esse passo.
- Submissão via MCP passa **exatamente** pela mesma revisão humana.
- O autor continua responsável por todo o código, incluindo o gerado por IA. Reveja antes de submeter.

## Ordem de diagnóstico

1. Query Monitor — erro PHP? query lenta? hook errado?
2. `debug.log` — deprecations e notices acumulados
3. Health Check — desligar outros plugins só na sua sessão
4. `wp plugin check` — problema já conhecido do WP.org?
5. PHPStan — bug de tipo antes de chegar a runtime
