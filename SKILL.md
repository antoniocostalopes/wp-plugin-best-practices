---
name: wp-plugin-best-practices
description: Guia completo de desenvolvimento de plugins WordPress (WP 6.x + PHP 8.x preferencial, 7.4 mínimo absoluto) cobrindo código, segurança e performance. Use quando o usuário pedir para criar, auditar, refatorar ou publicar um plugin WordPress; quando mencionar hooks, shortcodes, blocos Gutenberg, REST API, custom post types, nonces, sanitização, escaping, transients, ou readme.txt; ou ao trabalhar com arquivos PHP dentro de wp-content/plugins/.
---

# WordPress Plugin Development

Skill abrangente para desenvolvimento profissional de plugins WordPress. Cobre quatro frentes:

1. **Auditoria** de código existente (segurança, performance, padrões)
2. **Scaffolding** de plugins novos com estrutura correta
3. **Guia de implementação** durante o desenvolvimento
4. **Checklist** pré-publicação no WordPress.org

**Alvo:** WordPress 6.2+ (para `%i` em `$wpdb->prepare`), PHP 8.0 mínimo recomendado. PHP 7.4 é o piso absoluto, mas está EOL desde nov/2022 — use só para legado.

## Quando usar esta skill

Acione automaticamente quando:

- O usuário pedir para **criar/iniciar/scaffold** um plugin WordPress
- O usuário pedir **revisão/auditoria/security review** de plugin
- O contexto envolver arquivos em `wp-content/plugins/`, `mu-plugins/`, ou um arquivo PHP com header `Plugin Name:`
- Surgirem termos: `add_action`, `add_filter`, `register_post_type`, `wp_enqueue_script`, `wp_nonce`, `WP_Query`, `register_rest_route`, `register_block_type`, `wp_kses`, `sanitize_*`, `esc_*`, `current_user_can`
- O usuário mencionar publicação no diretório WordPress.org, `readme.txt`, ou GPL para plugins

## Princípios fundamentais (não negociáveis)

Estes princípios sobrescrevem qualquer comportamento padrão ao trabalhar com plugins WordPress:

### 1. Segurança por padrão

- **Nunca** confie em dados de `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, ou meta de usuário sem sanitizar
- **Toda** ação que modifica estado precisa de **nonce** + **capability check**
- **Toda** saída em HTML/JS/atributos passa por função `esc_*` apropriada
- **Toda** query SQL customizada usa `$wpdb->prepare()` — nunca concatenação
- **Nunca** use `extract`, `eval`, `assert` com dados externos, ou `unserialize` em input do usuário

### 2. Não polua o namespace global

- Prefixe **tudo**: funções, classes, constantes, options, post meta, hooks customizados
- Use namespaces PHP (`namespace MyVendor\MyPlugin;`) ou classes como container
- Prefixo deve ter ≥4 caracteres, único e único ao plugin (ex: `acme_widgets_`)

### 3. Use a Plugin API — não hacks

- Use **hooks** (actions/filters) em vez de modificar core/temas
- Use **WP_Query** ou `get_posts()` em vez de SQL direto sempre que possível
- Use **Settings API** / **Options API** em vez de constantes hardcoded
- Use **wp_enqueue_script/style** — nunca `<script>` ou `<link>` diretos no `wp_head`

### 4. Internacionalização desde o dia 1

- Toda string visível ao usuário passa por `__()`, `_e()`, `_n()`, `_x()`, `_nx()`, etc.
- Use text domain único e consistente (igual ao slug do plugin)
- Carregue traduções com `load_plugin_textdomain()` no hook `init`

### 5. Performance importa em escala

- Cache queries pesadas com **transients** (`set_transient`/`get_transient`)
- Use **autoload=no** em options grandes ou raramente acessadas
- Não execute queries em `wp_loaded` ou `init` sem necessidade (toda página carrega)
- Enfileire scripts apenas onde precisar (não em todas as páginas)

## Workflow por tipo de solicitação

### Criando plugin novo (scaffolding)

1. Pergunte ao usuário: nome do plugin, slug (prefixo), descrição curta, e features iniciais
2. Confirme se é plugin simples (1 arquivo), médio (estrutura `includes/`), ou OOP (classes + autoloader)
3. Leia `references/scaffolding.md` e gere a estrutura
4. Sempre inclua: header válido, ativação/desativação, uninstall, `index.php` silencioso em cada pasta, `readme.txt` se for público

### Auditando plugin existente

1. Localize o arquivo principal (header `Plugin Name:`)
2. Mapeie estrutura: hooks, classes, AJAX/REST endpoints, shortcodes, blocos
3. Execute checks na seguinte ordem (cada um detalhado em referência):
   - **Segurança** → `references/security.md` (nonces, escaping, sanitização, capabilities, SQL)
   - **Padrões de código** → `references/standards.md` (WPCS, prefixos, namespaces)
   - **Performance** → `references/performance.md` (queries, cache, enqueue, autoload)
   - **i18n** → strings sem `__()`, text domain consistente
4. Apresente achados agrupados por severidade: **Crítico** (segurança), **Alto** (bugs/perf), **Médio** (padrões), **Baixo** (estilo)
5. Não refatore sem confirmação — apresente o relatório primeiro

### Guiando implementação de feature

Antes de escrever código para uma feature, confirme:

- **Qual hook** é o ponto de entrada correto? (consultar Plugin Handbook)
- **Quem pode** acionar isso? (capability check necessário)
- **Que input** vem do usuário? (cada campo precisa de sanitização específica)
- **Onde o output aparece**? (escaping específico ao contexto)
- **Precisa de cache**? (frequência de chamada vs custo)

### Checklist pré-publicação

Antes de submeter ao WordPress.org ou empacotar release, rode `references/checklist.md` completo.

## Funções de segurança — referência rápida

| Cenário | Função correta |
|---|---|
| Output em HTML body | `esc_html()` |
| Output em atributo HTML | `esc_attr()` |
| Output de URL em href/src | `esc_url()` |
| Output em `<textarea>` | `esc_textarea()` |
| Output em JS inline | `wp_json_encode()` ou `esc_js()` (legado) |
| HTML permitido (rich text) | `wp_kses_post()` ou `wp_kses()` com allowlist |
| Input texto simples | `sanitize_text_field()` |
| Input email | `sanitize_email()` |
| Input URL | `esc_url_raw()` (para storage) |
| Input chave/slug | `sanitize_key()` |
| Input HTML rico | `wp_kses_post()` |
| Input inteiro | `absint()` ou `(int)` |
| SQL com variáveis | `$wpdb->prepare()` |
| Verificar permissão | `current_user_can( 'capability' )` |
| Verificar origem do request | `wp_verify_nonce()` / `check_admin_referer()` / `check_ajax_referer()` |

Detalhamento completo em `references/security.md`.

## Decision tree — escolha rápida

Use esta tabela **antes** de mergulhar em código. Cada decisão tem um "se → então" claro. Se a resposta exigir mais nuance, abra a referência indicada.

### Onde armazenar dados?

| Tipo de dado | Solução | Por quê |
|---|---|---|
| Config global do plugin | `Options API` (`get_option` / `update_option`) | Cacheado em memória; ideal para 1-50 KB |
| Config grande (>100 KB) | Option com `autoload=no` | Não carrega em toda request |
| Cache temporário | `Transient API` (`set_transient`) | TTL automático; persistente |
| Cache de request | `wp_cache_set` (object cache) | Não persiste sem Redis/Memcached |
| Por-post | Post meta (`update_post_meta`) | Indexado, query-friendly |
| Por-usuário | User meta (`update_user_meta`) | Idem |
| Estrutura própria com queries complexas | Tabela custom + `dbDelta` | Indexação controlada, sem overhead de meta |
| Secrets (API keys) | `wp-config.php` constantes ou option criptografada | Não vazar em backup/export |

### Onde renderizar UI?

| Caso | Solução |
|---|---|
| Página de configuração do plugin | Settings API + `add_options_page` |
| Multiple pages de admin | `add_menu_page` + `add_submenu_page` |
| Box no editor de post | Metabox clássica **ou** bloco/sidebar Gutenberg (preferir Gutenberg em código novo) |
| Componente reutilizável no editor | Bloco Gutenberg (`block.json` + render callback) |
| Output em conteúdo de post (legado) | Shortcode (retorna string, nunca echo) |
| Widget de sidebar (legado) | `WP_Widget` (deprecado em favor de blocos, mas ainda suportado) |
| UI no front-end gerada dinamicamente | REST API + JS no front |

### Qual hook usar?

| Quero... | Hook | Prioridade |
|---|---|---|
| Inicializar plugin (registrar classes) | `plugins_loaded` | 10 |
| Registrar CPT/taxonomy/shortcode/REST | `init` | 10 |
| Registrar menu admin | `admin_menu` | 10 |
| Registrar settings | `admin_init` | 10 |
| Enfileirar scripts front | `wp_enqueue_scripts` | 10 |
| Enfileirar scripts admin | `admin_enqueue_scripts` (com `$hook_suffix`) | 10 |
| Modificar query principal do front | `pre_get_posts` (condicionado em `! is_admin() && $q->is_main_query()`) | 10 |
| Reagir a save de post **com meta + terms** persistidos | `wp_after_insert_post` (WP 5.6+) | 10 |
| Limpar cache custom | hook do evento que invalida (`save_post`, `updated_option_X`, etc.) | 10 |

Catálogo completo em `references/hooks-catalog.md`.

### Comunicação com servidor — AJAX ou REST?

| Caso | Escolha |
|---|---|
| Endpoint público que pode ser cacheado por CDN | **REST API** (`GET`) |
| Endpoint que muda estado | **REST API** com `permission_callback` |
| Compatibilidade com código antigo / handler simples no admin | **AJAX legacy** (`admin-ajax.php`) |
| Auth não-cookie (API externa, mobile) | **REST API** com Application Passwords ou auth custom |

### Trabalho pesado — onde rodar?

| Duração esperada | Solução |
|---|---|
| <500ms | Inline na request |
| 500ms–5s | Inline mas com cache de resultado (transient) |
| 5s–60s | WP-Cron (mas só dispara em tráfego sem cron real do sistema) |
| >60s ou volume alto (>50 jobs/min) | **Action Scheduler** (vem com WooCommerce; standalone também) |

### Modo de operação (instrução ao modelo)

Ao receber tarefa, identifique o modo e siga estritamente:

| Sinal do usuário | Modo | Comportamento obrigatório |
|---|---|---|
| "audita", "revê", "review", "verifica" | **Audit** | Relatório agrupado por severidade. **Não refatorar sem confirmação.** |
| "cria", "scaffold", "novo plugin", "começa" | **Scaffold** | Confirmar nome/slug/scope antes; gerar estrutura completa |
| "implementa X", "adiciona feature Y" | **Implement** | Antes de codar, confirmar: hook? capability? input? output context? cache? |
| "vou publicar", "submeter ao WP.org" | **Publish** | Rodar `references/checklist.md` completo antes de aprovar |

## Estrutura de arquivos da skill

```
wp-plugin-best-practices/
├── SKILL.md                    (este arquivo — entrada)
├── references/
│   ├── security.md             (nonces, escaping, sanitização, caps, SQL, OWASP)
│   ├── performance.md          (queries, transients, cache, enqueue, autoload)
│   ├── scaffolding.md          (templates de estrutura + headers + boilerplate)
│   ├── standards.md            (WPCS, prefixos, namespaces, PHPDoc, i18n)
│   ├── checklist.md            (checklist pré-publicação WordPress.org)
│   └── hooks-catalog.md        (catálogo de hooks por caso de uso)
├── examples/
│   └── anti-patterns.md        (20 pares "errado vs certo" para audit/refactor)
└── templates/
    ├── plugin-main.php         (template do arquivo principal)
    ├── uninstall.php           (template de uninstall seguro)
    ├── readme.txt              (template readme.txt para WP.org)
    ├── phpcs.xml.dist          (ruleset WPCS pronto)
    ├── .gitignore              (gitignore padrão)
    ├── index.php               (index.php silencioso para cada pasta)
    └── src/Plugin.php          (classe singleton do plugin)
```

Carregue arquivos de `references/` sob demanda — não leia todos de uma vez. O `SKILL.md` aqui é suficiente para acionar a skill e decidir qual referência consultar.

## Recursos oficiais (consulte quando houver dúvida)

- Plugin Handbook: https://developer.wordpress.org/plugins/
- Code Reference: https://developer.wordpress.org/reference/
- WPCS (Coding Standards): https://developer.wordpress.org/coding-standards/
- WordPress.org Plugin Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Plugin Security: https://developer.wordpress.org/apis/security/
