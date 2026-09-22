---
name: wp-plugin-best-practices
description: Guia completo de desenvolvimento de plugins WordPress (WP 6.x + PHP 8.x preferencial, 7.4 mínimo absoluto) cobrindo código, segurança e performance. Use quando o utilizador pedir para criar, auditar, refatorar ou publicar um plugin WordPress; quando mencionar hooks, shortcodes, blocos Gutenberg, REST API, custom post types, nonces, sanitização, escaping, transients, ou readme.txt; capabilities, roles, privacidade/RGPD, register_post_meta; ou ao trabalhar com ficheiros PHP dentro de wp-content/plugins/.
metadata:
  author: António Costa Lopes
  version: "1.1.0"
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

- O utilizador pedir para **criar/iniciar/scaffold** um plugin WordPress
- O utilizador pedir **revisão/auditoria/security review** de plugin
- O contexto envolver ficheiros em `wp-content/plugins/`, `mu-plugins/`, ou um ficheiro PHP com header `Plugin Name:`
- Surgirem termos: `add_action`, `add_filter`, `register_post_type`, `wp_enqueue_script`, `wp_nonce`, `WP_Query`, `register_rest_route`, `register_block_type`, `wp_kses`, `sanitize_*`, `esc_*`, `current_user_can`, `add_role`, `add_cap`, `map_meta_cap`, `register_post_meta`, `show_in_rest`, `wp_privacy_personal_data_exporters`, `remove_action`, `wp_set_script_translations`
- O utilizador mencionar publicação no diretório WordPress.org, `readme.txt`, ou GPL para plugins

## Princípios fundamentais (não negociáveis)

Estes princípios sobrescrevem qualquer comportamento padrão ao trabalhar com plugins WordPress:

### 1. Segurança por padrão

- **Nunca** confie em dados de `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, ou meta de utilizador sem sanitizar
- **Toda** ação que modifica estado precisa de **nonce** + **capability check**
- **Toda** saída em HTML/JS/atributos passa por função `esc_*` apropriada — incluindo dados lidos da própria base de dados
- Escape **o mais tarde possível** e a string **inteira**, nunca segmentos concatenados
- Prefira **validar/rejeitar** a sanitizar; allowlist sempre, denylist nunca
- Nonce prova intenção, não permissão: nonce **e** `current_user_can()`, sempre os dois
- **Toda** query SQL personalizada usa `$wpdb->prepare()` — nunca concatenação
- **Nunca** use `extract`, `eval`, `assert` com dados externos, ou `unserialize` em input do utilizador
- Permissão verifica-se por **capability**, nunca por role; ação sobre objeto usa meta cap + ID (`edit_post`, `$post_id`)
- URL vinda de input/option vai em `wp_safe_remote_*`, não `wp_remote_*`

### 2. Não polua o namespace global

- Prefixe **tudo**: funções, classes, constantes, options, post meta, hooks personalizados
- Use namespaces PHP (`namespace MyVendor\MyPlugin;`) ou classes como container
- Prefixo deve ter ≥4 caracteres, único e único ao plugin (ex: `acme_widgets_`)

### 3. Use a Plugin API — não hacks

- Use **hooks** (actions/filters) em vez de modificar core/temas
- Use **WP_Query** ou `get_posts()` em vez de SQL direto sempre que possível
- Use **Settings API** / **Options API** em vez de constantes hardcoded
- Use **wp_enqueue_script/style** — nunca `<script>` ou `<link>` diretos no `wp_head`

### 4. Internacionalização desde o dia 1

- Toda string visível ao utilizador passa por `__()`, `_e()`, `_n()`, `_x()`, `_nx()`, etc.
- Use text domain único e consistente (igual ao slug do plugin)
- **Nunca** chame `__()` antes do hook `init` (WP 6.7 emite `_load_textdomain_just_in_time was called incorrectly`)
- `load_plugin_textdomain()` só para distribuição fora do WP.org — o diretório carrega automaticamente desde WP 4.6
- Strings em JS: `wp_set_script_translations()` + `wp i18n make-json`

### 5. Performance importa em escala

- Cache queries pesadas com **transients** (`set_transient`/`get_transient`)
- Use **autoload=no** em options grandes ou raramente acessadas
- Não execute queries em `wp_loaded` ou `init` sem necessidade (toda página carrega)
- Enfileire scripts apenas onde precisar (não em todas as páginas)

### 6. Privacidade não é opcional

- Se guarda email, IP, nome ou telemetria, implemente exporter + eraser (`wp_privacy_personal_data_exporters` / `_erasers`) e declare em `wp_add_privacy_policy_content()`
- Qualquer contacto com servidor externo exige **opt-in explícito, default off** (guideline #7 do WP.org)
- Nunca logue `$_POST` inteiro — apanha emails, passwords e tokens

## Workflow por tipo de solicitação

### Criando plugin novo (scaffolding)

1. Pergunte ao utilizador: nome do plugin, slug (prefixo), descrição curta, e features iniciais
2. Confirme se é plugin simples (1 ficheiro), médio (estrutura `includes/`), ou OOP (classes + autoloader)
3. Leia `references/scaffolding.md` e gere a estrutura
4. Sempre inclua: header válido, ativação/desativação, uninstall, `index.php` silencioso em cada pasta, `readme.txt` se for público

### Auditando plugin existente

1. Localize o ficheiro principal (header `Plugin Name:`)
2. Mapeie estrutura: hooks, classes, AJAX/REST endpoints, shortcodes, blocos
3. Execute checks na seguinte ordem (cada um detalhado em referência):
   - **Segurança** → `references/security.md` (nonces, escaping, sanitização, capabilities, SQL)
   - **Padrões de código** → `references/standards.md` (WPCS, prefixos, namespaces)
   - **Permissões** → `references/capabilities.md` (caps vs roles, meta caps, caps de CPT)
   - **UI do admin** → `references/admin-ui.md` (menus, Settings API, meta boxes, perfil)
   - **Performance** → `references/performance.md` (queries, cache, enqueue, autoload)
   - **i18n** → `references/standards.md` (strings sem `__()`, text domain, tradução antes do `init`)
   - **Privacidade** → `references/privacy.md` (só se houver dados pessoais ou chamadas externas)
4. Apresente achados agrupados por severidade: **Crítico** (segurança), **Alto** (bugs/perf), **Médio** (padrões), **Baixo** (estilo)
5. Não refatore sem confirmação — apresente o relatório primeiro

### Guiando implementação de feature

Antes de escrever código para uma feature, confirme:

- **Qual hook** é o ponto de entrada correto? (consultar Plugin Handbook)
- **Quem pode** acionar isso? (capability check necessário)
- **Que input** vem do utilizador? (cada campo precisa de sanitização específica)
- **Onde o output aparece**? (escaping específico ao contexto)
- **Precisa de cache**? (frequência de chamada vs custo)

### Checklist pré-publicação

Antes de submeter ao WordPress.org ou empacotar release, corra `references/checklist.md` completo.

## Funções de segurança — referência rápida

| Cenário | Função correta |
|---|---|
| Output em HTML body | `esc_html()` |
| Output em atributo HTML | `esc_attr()` |
| Output de URL em href/src | `esc_url()` |
| Output em `<textarea>` | `esc_textarea()` |
| Output em XML/feed | `esc_xml()` |
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
| Validar caminho de ficheiro | `validate_file()` (0 = seguro) + allowlist |
| Redirect com destino de input | `wp_safe_redirect()` |
| Validar valor contra lista | `in_array( $v, $allowed, true )` — o `true` não é opcional |
| Nome de tabela/coluna em SQL | `%i` no `prepare()` (WP 6.2+); `ORDER BY` só por allowlist |

Detalhe completo em `references/security.md`.

## Decision tree — escolha rápida

Use esta tabela **antes** de mergulhar em código. Cada decisão tem um "se → então" claro. Se a resposta exigir mais nuance, abra a referência indicada.

### Onde armazenar dados?

| Tipo de dado | Solução | Porquê |
|---|---|---|
| Config global do plugin | `Options API` (`get_option` / `update_option`) | Cacheado em memória; ideal para 1-50 KB |
| Config grande (>100 KB) | Option com `autoload=no` | Não carrega em toda request |
| Cache temporário | `Transient API` (`set_transient`) | TTL automático; persistente |
| Cache de request | `wp_cache_set` (object cache) | Não persiste sem Redis/Memcached |
| Por-post | Post meta (`update_post_meta`) | Indexado, query-friendly |
| Por-utilizador | User meta (`update_user_meta`) | Idem |
| Estrutura própria com queries complexas | Tabela custom + `dbDelta` | Indexação controlada, sem overhead de meta |
| Secrets (API keys) | `wp-config.php` constantes ou option criptografada | Não vazar em backup/export |

### Onde renderizar UI?

| Caso | Solução |
|---|---|
| Página de configuração do plugin | Settings API + `add_options_page` (detalhe em `references/admin-ui.md`) |
| Multiple pages de admin | `add_menu_page` + `add_submenu_page` |
| Box no editor de post | Meta box clássica **ou** bloco/sidebar Gutenberg (preferir Gutenberg em código novo) — `references/admin-ui.md` |
| Campo no perfil de utilizador | `show_user_profile` + `edit_user_profile` (e os dois hooks de update) |
| Atualização quase-em-tempo-real no admin | Heartbeat API (`references/hooks-catalog.md`, secção 17) |
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

### Trabalho pesado — onde correr?

| Duração esperada | Solução |
|---|---|
| <500ms | Inline na request |
| 500ms–5s | Inline mas com cache de resultado (transient) |
| 5s–60s | WP-Cron (mas só dispara em tráfego sem cron real do sistema) |
| >60s ou volume alto (>50 jobs/min) | **Action Scheduler** (vem com WooCommerce; standalone também) |

### Modo de operação (instrução ao modelo)

Ao receber tarefa, identifique o modo e siga estritamente:

| Sinal do utilizador | Modo | Comportamento obrigatório |
|---|---|---|
| "audita", "revê", "review", "verifica" | **Audit** | Relatório agrupado por severidade. **Não refatorar sem confirmação.** |
| "cria", "scaffold", "novo plugin", "começa" | **Scaffold** | Confirmar nome/slug/scope antes; gerar estrutura completa |
| "implementa X", "adiciona feature Y" | **Implement** | Antes de codar, confirmar: hook? capability? input? output context? cache? |
| "vou publicar", "submeter ao WP.org" | **Publish** | Correr `references/checklist.md` completo antes de aprovar. Lembrar que desde jun/2026 toda a release passa por revisão automática de segurança e pode ser **bloqueada** na distribuição |

## Estrutura de ficheiros da skill

```
wp-plugin-best-practices/
├── SKILL.md                    (este ficheiro — entrada)
├── CHANGELOG.md                (histórico de versões da skill)
├── references/
│   ├── security.md             (nonces, escaping, sanitização, caps, SQL, OWASP)
│   ├── performance.md          (queries, transients, cache, enqueue, autoload)
│   ├── scaffolding.md          (templates de estrutura + headers + boilerplate)
│   ├── standards.md            (WPCS, prefixos, namespaces, PHPDoc, i18n)
│   ├── checklist.md            (pré-publicação, 18 guidelines, common issues, revisão automática, SVN)
│   ├── hooks-catalog.md        (catálogo de hooks, remoção de hooks, hooks próprios)
│   ├── capabilities.md         (roles, caps, meta caps, map_meta_cap, caps de CPT)
│   ├── privacy.md              (RGPD: exporter, eraser, política, consentimento)
│   ├── developer-tools.md      (wp-env, Query Monitor, Debug Bar, Plugin Check, WP-CLI, CI, MCP do WP.org)
│   └── admin-ui.md             (menus admin, Settings API, meta boxes, campos de perfil)
├── examples/
│   └── anti-patterns.md        (27 pares "errado vs certo" para audit/refactor)
└── templates/
    ├── plugin-main.php         (template do ficheiro principal)
    ├── uninstall.php           (template de uninstall seguro)
    ├── readme.txt              (template readme.txt para WP.org)
    ├── phpcs.xml.dist          (ruleset WPCS pronto)
    ├── .gitignore              (gitignore padrão)
    ├── index.php               (index.php silencioso para cada pasta)
    └── src/Plugin.php          (classe singleton do plugin)
```

Carregue ficheiros de `references/` sob demanda — não leia todos de uma vez. O `SKILL.md` aqui é suficiente para acionar a skill e decidir qual referência consultar.

## Recursos oficiais (consulte quando houver dúvida)

- Plugin Handbook: https://developer.wordpress.org/plugins/
- Segurança em i18n: https://developer.wordpress.org/plugins/internationalization/security/
- Code Reference: https://developer.wordpress.org/reference/
- WPCS (Coding Standards): https://developer.wordpress.org/coding-standards/
- WordPress.org Plugin Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Plugin Security: https://developer.wordpress.org/apis/security/
- Roles & Capabilities: https://developer.wordpress.org/plugins/users/roles-and-capabilities/
- Privacidade: https://developer.wordpress.org/plugins/privacy/
- Developer Tools: https://developer.wordpress.org/plugins/developer-tools/
