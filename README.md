# 🔌 wp-plugin-best-practices

> 🤖 **Claude Code Skill** para desenvolvimento profissional de plugins **WordPress 6.x** com PHP **8.0+** (7.4 mínimo absoluto, EOL).

Cobre **auditoria**, **scaffolding**, **implementação guiada** e **checklist pré-publicação** — incluindo segurança, capabilities, privacidade/RGPD, UI do admin, performance e ferramentas de desenvolvimento. Alinhado com o WordPress Plugin Handbook, as Security APIs, as WordPress.org Plugin Guidelines e o WPCS.

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-yellow)](#-licença)
[![Claude Code](https://img.shields.io/badge/Claude%20Code-Skill-d97757)](https://claude.com/claude-code)
[![Version](https://img.shields.io/badge/vers%C3%A3o-1.1.0-brightgreen)](CHANGELOG.md)

---

## 💡 O que é uma Skill?

Pasta carregada pelo [Claude Code](https://claude.com/claude-code) que ativa instruções, referências e templates especializados quando o contexto da conversa corresponde ao gatilho definido. Pensa nisto como **conhecimento de domínio plug-and-play** para o Claude.

---

## 📦 Instalação

Clona para a pasta de skills do Claude Code (global ou por projeto):

```bash
# 🌍 Global (todas as conversas)
git clone https://github.com/antoniocostalopes/wp-plugin-best-practices.git \
    ~/.claude/skills/wp-plugin-best-practices

# 📁 Por projeto (apenas dentro do repositório)
git clone https://github.com/antoniocostalopes/wp-plugin-best-practices.git \
    .claude/skills/wp-plugin-best-practices
```

✅ A skill é detetada automaticamente na próxima invocação do Claude Code.

---

## 🎯 Quando dispara

O Claude Code ativa a skill automaticamente quando o contexto envolve:

- 🆕 Pedidos para **criar / scaffold / auditar / refatorar / publicar** um plugin WordPress
- 📂 Ficheiros em `wp-content/plugins/`, `mu-plugins/`, ou PHP com header `Plugin Name:`
- 🔧 Menções a símbolos da Plugin API: `add_action`, `add_filter`, `register_post_type`, `wp_enqueue_script`, `wp_nonce_*`, `WP_Query`, `register_rest_route`, `register_block_type`, `wp_kses`, `sanitize_*`, `esc_*`, `current_user_can`, `add_role`, `map_meta_cap`, `register_post_meta`, `show_in_rest`, `remove_action`
- 🚀 Conversas sobre publicação no WordPress.org, `readme.txt`, ou GPL para plugins

---

## ⚙️ Modos de operação

A skill identifica automaticamente o modo a partir da intenção:

| Sinal do utilizador | Modo | Comportamento |
|---|---|---|
| "audita", "revê", "verifica" | 🔍 **Audit** | Relatório agrupado por severidade (Crítico → Baixo). Não refatora sem confirmação. |
| "cria", "scaffold", "novo plugin" | 🏗️ **Scaffold** | Confirma nome/slug/escopo antes; gera estrutura completa a partir dos templates. |
| "implementa X", "adiciona Y" | 🛠️ **Implement** | Antes de codar, confirma: hook? capability? input? output context? cache? |
| "vou publicar", "submeter ao WP.org" | 📤 **Publish** | Corre o checklist pré-publicação completo. |

---

## 🛡️ Princípios não-negociáveis

A skill aplica estes princípios em cima de qualquer comportamento default:

> 🧠 **Base:** os princípios da [Security API](https://developer.wordpress.org/apis/security/) — nunca confiar em input (nem no que vem da própria base de dados), escapar o mais tarde possível, validar antes de sanitizar, allowlist sempre.

1. 🔒 **Segurança por defeito** — toda ação de estado exige nonce + capability check; toda saída passa por função `esc_*`; toda query custom usa `$wpdb->prepare()`; sem `eval`, `extract`, `unserialize` em input externo.
2. 🏷️ **Sem poluição global** — prefixo único ≥4 caracteres em funções, classes, constantes, hooks, options, meta, capabilities.
3. 🎣 **Plugin API, não hacks** — hooks em vez de modificar core/tema; `WP_Query` em vez de SQL direto; `wp_enqueue_*` em vez de `<script>` no `wp_head`.
4. 🌍 **i18n desde o dia 1** — toda string visível passa por `__()` / `esc_html__()`; text domain igual ao slug.
5. ⚡ **Performance em escala** — transients para queries caras, `autoload=no` em options grandes, enqueue condicionado por página.
6. 🔑 **Capability, nunca role** — `current_user_can( cap )`; ação sobre objeto usa meta cap + ID (`edit_post`, `$post_id`).
7. 🕵️ **Privacidade por defeito** — exporter/eraser RGPD se há dados pessoais; contacto com servidor externo só com opt-in explícito (default off).

---

## 📁 Estrutura

```
wp-plugin-best-practices/
├── 📄 SKILL.md                    # Entrada da skill (gatilho + decisão)
├── 📋 CHANGELOG.md                # Histórico de versões da skill
├── 📚 references/
│   ├── 🔐 security.md             # Nonces, escaping, sanitização, caps, SQL, OWASP
│   ├── ⚡ performance.md          # Queries, transients, cache, enqueue, autoload
│   ├── 🏗️ scaffolding.md          # Estrutura, headers, bootstrap, activation/uninstall
│   ├── 📐 standards.md            # WPCS, prefixos, namespaces, PHPDoc, i18n
│   ├── ✅ checklist.md            # Pré-publicação, 18 guidelines, common issues, revisão automática, SVN
│   ├── 🎣 hooks-catalog.md        # Catálogo de hooks, remoção de hooks, hooks próprios
│   ├── 🔑 capabilities.md         # Roles, caps, meta caps, map_meta_cap, caps de CPT
│   ├── 🕵️ privacy.md              # RGPD: exporter, eraser, política, consentimento
│   ├── 🛠️ developer-tools.md      # wp-env, Query Monitor, Debug Bar, Plugin Check, WP-CLI, CI, MCP
│   └── 🖥️ admin-ui.md             # Menus admin, Settings API, meta boxes, campos de perfil
├── 🧪 examples/
│   └── ⚠️ anti-patterns.md        # 27 pares "errado vs certo" para audit/refactor
└── 📦 templates/
    ├── plugin-main.php            # Ficheiro principal com header completo
    ├── src/Plugin.php             # Classe singleton com lifecycle
    ├── uninstall.php              # Uninstall seguro com opt-in destrutivo
    ├── readme.txt                 # Readme WP.org com todas as secções
    ├── phpcs.xml.dist             # Ruleset WPCS pronto
    ├── index.php                  # Silence is golden (uma por pasta)
    └── .gitignore                 # Gitignore padrão
```

💨 As referências são carregadas **sob demanda** — o `SKILL.md` é suficiente para decidir qual consultar.

---

## 🔐 Tabela rápida de funções de segurança

| Cenário | Função correta |
|---|---|
| 📝 Output em HTML body | `esc_html()` |
| 🏷️ Output em atributo HTML | `esc_attr()` |
| 🔗 Output de URL em href/src | `esc_url()` |
| 📋 Output em `<textarea>` | `esc_textarea()` |
| ⚙️ Output em JS inline | `wp_json_encode()` |
| 🎨 HTML permitido (rich text) | `wp_kses_post()` ou `wp_kses()` com allowlist |
| ⌨️ Input texto simples | `sanitize_text_field()` |
| 📧 Input email | `sanitize_email()` |
| 🌐 Input URL (storage) | `esc_url_raw()` |
| 🔑 Input chave/slug | `sanitize_key()` |
| 📰 Input HTML rico | `wp_kses_post()` |
| 🔢 Input inteiro positivo | `absint()` |
| 🗄️ SQL com variáveis | `$wpdb->prepare()` |
| 👮 Verificar permissão | `current_user_can( 'capability' )` |
| 🎫 Verificar origem do request | `wp_verify_nonce()` / `check_admin_referer()` / `check_ajax_referer()` |
| 📄 Output em XML/feed | `esc_xml()` |
| 🗺️ Validar caminho de ficheiro | `validate_file()` (0 = seguro) + allowlist |
| ↩️ Redirect com destino de input | `wp_safe_redirect()` |
| ✔️ Validar valor contra lista | `in_array( $v, $allowed, true )` |
| 🧍 Permissão sobre um objeto | `current_user_can( 'edit_post', $post_id )` |
| 🌐 HTTP com URL vinda de input | `wp_safe_remote_get()` / `wp_safe_remote_post()` |
| 🗂️ Meta com sanitização e auth | `register_post_meta()` com `sanitize_callback` + `auth_callback` |

📖 Detalhe completo em [`references/security.md`](references/security.md).

---

## 🧩 Compatibilidade e versões

| Componente | Versão alvo | Mínimo absoluto |
|---|---|---|
| 🟦 WordPress | 6.8 (atual) | 6.2 (para `%i` em `$wpdb->prepare`) |
| 🐘 PHP | 8.0+ | 7.4 ⚠️ (EOL desde nov/2022 — só para legado) |
| 📏 WPCS | WordPress-Extra + WordPress-Docs | — |
| 🔍 PHPCompatibility | PHPCompatibilityWP | — |

---

## 🚫 Razões mais comuns de rejeição no WP.org (cobertas pela skill)

1. 💉 Falta de sanitização/escaping (XSS, SQLi)
2. 🎫 Ausência de nonces em forms
3. 📡 Phone home / tracking sem opt-in explícito
4. 🌍 Strings sem i18n
5. 🔗 Hardcode de URLs externas (Google Fonts, CDN jQuery, etc.)
6. 🔨 Modificação de outros plugins/temas/core
7. 📚 Includes diretos de bibliotecas em vez de versões bundled do WP
8. 🏷️ Falta de prefixo em funções/classes globais
9. 🚪 Direct file access sem `defined( 'ABSPATH' )`
10. ™️ Trademark no nome do plugin

---

## 📚 Recursos oficiais consultados

- 📖 [Plugin Handbook](https://developer.wordpress.org/plugins/)
- 🔎 [Code Reference](https://developer.wordpress.org/reference/)
- 📏 [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/)
- 📋 [Plugin Guidelines (WordPress.org)](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- 🪧 [Plugin Header Requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- 🛡️ [Plugin Security APIs](https://developer.wordpress.org/apis/security/)
- 🔑 [Roles & Capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/)
- 🕵️ [Privacy](https://developer.wordpress.org/plugins/privacy/)
- 🛠️ [Developer Tools](https://developer.wordpress.org/plugins/developer-tools/)
- 🌍 [Internationalization](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)

---

## 🤝 Contribuir

Issues e PRs são bem-vindos! Para mudanças significativas, abre primeiro uma issue para discussão.

Quando contribuíres:

- 🎨 Mantém consistência com o estilo das referências existentes (tom direto, exemplos código-primeiro)
- 📌 Cita a fonte oficial (Plugin Handbook / WPCS / Guidelines) ao introduzir uma regra nova
- ✅ Atualiza o checklist se a regra for bloqueadora de publicação
- 📋 Regista a alteração no [`CHANGELOG.md`](CHANGELOG.md), seguindo Keep a Changelog + SemVer

---

## 📄 Licença

MIT. Vê [`LICENSE`](LICENSE) se incluído, ou usa livremente em projetos próprios e comerciais.

---

## 👤 Autor

[António Costa Lopes](https://github.com/antoniocostalopes)
