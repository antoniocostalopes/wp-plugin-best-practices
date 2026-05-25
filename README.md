# wp-plugin-best-practices

Claude Code Skill para desenvolvimento profissional de plugins **WordPress 6.x** com PHP **8.0+** (7.4 mínimo absoluto, EOL). Cobre auditoria, scaffolding, implementação guiada e checklist pré-publicação — alinhado com o WordPress Plugin Handbook, WordPress.org Plugin Guidelines e WPCS.

> **O que é uma Skill?** Pasta carregada pelo [Claude Code](https://claude.com/claude-code) que ativa instruções, referências e templates especializados quando o contexto da conversa corresponde ao gatilho definido.

---

## Instalação

Clone para a pasta de skills do Claude Code (global ou por projeto):

```bash
# Global (todas as conversas)
git clone https://github.com/antoniocostalopes/wp-plugin-best-practices.git \
    ~/.claude/skills/wp-plugin-best-practices

# Por projeto (apenas dentro do repositório)
git clone https://github.com/antoniocostalopes/wp-plugin-best-practices.git \
    .claude/skills/wp-plugin-best-practices
```

A skill é detetada automaticamente na próxima invocação do Claude Code.

---

## Quando dispara

O Claude Code ativa a skill automaticamente quando o contexto envolve qualquer um destes sinais:

- Pedidos para **criar / scaffold / auditar / refatorar / publicar** um plugin WordPress
- Ficheiros em `wp-content/plugins/`, `mu-plugins/`, ou PHP com header `Plugin Name:`
- Menções a símbolos da Plugin API: `add_action`, `add_filter`, `register_post_type`, `wp_enqueue_script`, `wp_nonce_*`, `WP_Query`, `register_rest_route`, `register_block_type`, `wp_kses`, `sanitize_*`, `esc_*`, `current_user_can`
- Conversas sobre publicação no WordPress.org, `readme.txt`, ou GPL para plugins

---

## Modos de operação

A skill identifica automaticamente o modo a partir da intenção do utilizador:

| Sinal do utilizador | Modo | Comportamento |
|---|---|---|
| "audita", "revê", "verifica" | **Audit** | Relatório agrupado por severidade (Crítico → Baixo). Não refatora sem confirmação. |
| "cria", "scaffold", "novo plugin" | **Scaffold** | Confirma nome/slug/escopo antes; gera estrutura completa a partir dos templates. |
| "implementa X", "adiciona Y" | **Implement** | Antes de codar, confirma: hook? capability? input? output context? cache? |
| "vou publicar", "submeter ao WP.org" | **Publish** | Corre o checklist pré-publicação completo. |

---

## Princípios não-negociáveis

A skill aplica estes princípios em cima de qualquer comportamento default:

1. **Segurança por defeito** — toda ação de estado exige nonce + capability check; toda saída passa por função `esc_*`; toda query custom usa `$wpdb->prepare()`; sem `eval`, `extract`, `unserialize` em input externo.
2. **Sem poluição global** — prefixo único ≥4 caracteres em funções, classes, constantes, hooks, options, meta, capabilities.
3. **Plugin API, não hacks** — hooks em vez de modificar core/tema; `WP_Query` em vez de SQL direto; `wp_enqueue_*` em vez de `<script>` no `wp_head`.
4. **i18n desde o dia 1** — toda string visível passa por `__()`/`esc_html__()`; text domain igual ao slug.
5. **Performance em escala** — transients para queries caras, `autoload=no` em options grandes, enqueue condicionado por página.

---

## Estrutura

```
wp-plugin-best-practices/
├── SKILL.md                    # Entrada da skill (gatilho + decisão)
├── references/
│   ├── security.md             # Nonces, escaping, sanitização, caps, SQL, OWASP
│   ├── performance.md          # Queries, transients, cache, enqueue, autoload
│   ├── scaffolding.md          # Estrutura, headers, bootstrap, activation/uninstall
│   ├── standards.md            # WPCS, prefixos, namespaces, PHPDoc, i18n
│   ├── checklist.md            # Checklist pré-publicação WordPress.org
│   └── hooks-catalog.md        # Catálogo de hooks por caso de uso
├── examples/
│   └── anti-patterns.md        # 20 pares "errado vs certo" para audit/refactor
└── templates/
    ├── plugin-main.php         # Arquivo principal com header completo
    ├── src/Plugin.php          # Classe singleton com lifecycle
    ├── uninstall.php           # Uninstall seguro com opt-in destrutivo
    ├── readme.txt              # Readme WP.org com todas as secções
    ├── phpcs.xml.dist          # Ruleset WPCS pronto
    ├── index.php               # Silence is golden (uma por pasta)
    └── .gitignore              # Gitignore padrão
```

As referências são carregadas **sob demanda** — o `SKILL.md` é suficiente para decidir qual consultar.

---

## Tabela rápida de funções de segurança

| Cenário | Função correta |
|---|---|
| Output em HTML body | `esc_html()` |
| Output em atributo HTML | `esc_attr()` |
| Output de URL em href/src | `esc_url()` |
| Output em `<textarea>` | `esc_textarea()` |
| Output em JS inline | `wp_json_encode()` |
| HTML permitido (rich text) | `wp_kses_post()` ou `wp_kses()` com allowlist |
| Input texto simples | `sanitize_text_field()` |
| Input email | `sanitize_email()` |
| Input URL (storage) | `esc_url_raw()` |
| Input chave/slug | `sanitize_key()` |
| Input HTML rico | `wp_kses_post()` |
| Input inteiro positivo | `absint()` |
| SQL com variáveis | `$wpdb->prepare()` |
| Verificar permissão | `current_user_can( 'capability' )` |
| Verificar origem do request | `wp_verify_nonce()` / `check_admin_referer()` / `check_ajax_referer()` |

Detalhe completo em [`references/security.md`](references/security.md).

---

## Compatibilidade e versões

| Componente | Versão alvo | Mínimo absoluto |
|---|---|---|
| WordPress | 6.8 (atual) | 6.2 (para `%i` em `$wpdb->prepare`) |
| PHP | 8.0+ | 7.4 (EOL desde nov/2022 — só para legado) |
| WPCS | WordPress-Extra + WordPress-Docs | — |
| PHPCompatibility | PHPCompatibilityWP | — |

---

## Razões mais comuns de rejeição no WP.org (cobertas pela skill)

1. Falta de sanitização/escaping (XSS, SQLi)
2. Ausência de nonces em forms
3. Phone home / tracking sem opt-in explícito
4. Strings sem i18n
5. Hardcode de URLs externas (Google Fonts, CDN jQuery, etc.)
6. Modificação de outros plugins/temas/core
7. Includes diretos de bibliotecas em vez de versões bundled do WP
8. Falta de prefixo em funções/classes globais
9. Direct file access sem `defined( 'ABSPATH' )`
10. Trademark no nome do plugin

---

## Recursos oficiais consultados

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Code Reference](https://developer.wordpress.org/reference/)
- [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/)
- [Plugin Guidelines (WordPress.org)](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Plugin Header Requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Plugin Security APIs](https://developer.wordpress.org/apis/security/)

---

## Contribuir

Issues e PRs são bem-vindos. Para mudanças significativas, abra primeiro uma issue para discussão.

Quando contribuir:

- Mantenha consistência com o estilo das referências existentes (tom direto, exemplos código-primeiro)
- Cite a fonte oficial (Plugin Handbook / WPCS / Guidelines) ao introduzir uma regra nova
- Atualize o checklist se a regra for bloqueadora de publicação

---

## Licença

MIT. Veja [`LICENSE`](LICENSE) se incluído, ou use livremente em projetos próprios e comerciais.

---

## Autor

[António Costa Lopes](https://github.com/antoniocostalopes) — Agency Coders
