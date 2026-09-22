# Changelog

Todas as alterações relevantes desta skill são registadas aqui.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.1.0] - 2026-09-22

Auditoria completa do [Plugin Handbook](https://developer.wordpress.org/plugins/) e das
[Security APIs](https://developer.wordpress.org/apis/security/), com leitura integral dos submenus
de cada capítulo.

### Adicionado

**Referências novas**

- `references/capabilities.md` — roles vs capabilities, primitive vs meta caps, instalação de caps na
  ativação com guard de versão para upgrades, CPT com `capability_type` + `map_meta_cap`, filtro
  `map_meta_cap` com `do_not_allow`, multisite, remoção no uninstall.
- `references/privacy.md` — `wp_add_privacy_policy_content()`, exporter e eraser paginados com o
  formato de retorno correto, `wp_privacy_anonymize_ip()`, consentimento explícito (guideline #7),
  higiene de logs e retenção, hooks e options de privacidade do core.
- `references/developer-tools.md` — wp-env, wp-now, WP Playground, constantes de debug,
  Query Monitor, catálogo dos add-ons do Debug Bar por sintoma, Plugin Check, receitas WP-CLI,
  PHPStan/PHPCS, PHPUnit, CI com GitHub Actions, `blueprint.json` para o botão Preview do diretório,
  e servidor MCP do WordPress.org.
- `references/admin-ui.md` — menus de administração, Settings API, meta boxes e campos no perfil
  de utilizador.

**Conteúdo em referências existentes**

- `security.md` — princípios orientadores da Security API; ciclo de vida do nonce (validade de 12–24h,
  interação com cache de página, filtros `nonce_life` / `nonce_user_logged_out`); secção de validação
  (filosofias safelist/blocklist/format detection/format correction, `in_array` estrito, `switch ( true )`,
  validadores do core); `wp_safe_remote_*` contra SSRF; identificadores em SQL (`%i`, allowlist para
  `ORDER BY`); path traversal com `validate_file()`; `register_post_meta` e `show_in_rest` como decisão
  de segurança; sanitizar ≠ escapar; `filter_var` sem filtro; `wp_handle_upload`;
  proibição de HEREDOC/short tags; exemplo completo das cinco camadas; secção "manter-se atual".
- `checklist.md` — as 18 Detailed Plugin Guidelines mapeadas para verificações; revisão automática de
  segurança do WP.org (cooldown e bloqueio de releases); Common Issues da Plugin Review Team;
  fluxo de SVN com os gotchas que partem o plugin; papéis no diretório; regras de readme, assets,
  tags e changelog; regras da fila de revisão e tipos de plugin não aceites.
- `standards.md` — regras de prefixo da Plugin Review Team e a armadilha do `function_exists`;
  nome do ficheiro principal; enqueue moderno (WP 6.3, `strategy`/`in_footer`) e bibliotecas do core;
  segurança em i18n (tradução é input não confiável); caminhos e URLs sem hardcode.
- `hooks-catalog.md` — remoção de hooks de terceiros (regra dos parâmetros idênticos, timing,
  callbacks de objeto, closures irremovíveis); criação de hooks próprios com contrato documentado;
  Heartbeat API.
- `scaffolding.md` — `register_post_meta`; registo de taxonomias com capabilities próprias e o split
  de termos do WP 4.2; atributos de shortcode com `shortcode_atts` e normalização de chaves.
- `performance.md` — intervalos de cron e cron real do sistema, inspeção e testes de eventos,
  array numa option vs várias options, options de rede em multisite.
- `examples/anti-patterns.md` — 7 pares novos (#21 a #27): role em vez de capability, meta exposta ao
  REST, traduzir antes do `init`, allowlist com comparação frouxa, path traversal em `include`,
  `remove_menu_page()` como controlo de acesso, URL dentro de string traduzível.

### Alterado

- `SKILL.md` — princípios de segurança, i18n e privacidade atualizados; tabela rápida de funções
  ampliada; ordem de auditoria passa a incluir permissões, UI do admin e privacidade; modo Publish
  passa a referir a revisão automática de segurança.
- `README.md` — árvore de ficheiros, princípios e tabela de funções alinhados com o conteúdo novo;
  badge de versão a apontar para este changelog.
- **Uniformização de toda a skill**: português europeu consistente (ficheiro, utilizador, correr, ecrã,
  guardar, apagar, personalizado); text domain único `'acme-widgets'` em todos os exemplos, com grupo
  de cache `acme_widgets` e handles `acme-*`; cabeçalho igual em todas as referências
  (título, linha de propósito, linha `Handbook:`); secções finais de verificação uniformizadas
  para `## Checklist`.
- **Conformidade com o guia de autoria de skills da Anthropic**: índice (`## Conteúdo`) em todas as
  referências com mais de 100 linhas, porque os agentes fazem leituras parciais de ficheiros
  referenciados; versão movida do campo `version` (fora do spec) para `metadata.version`, a convenção
  usada pelas skills instaladas.

### Corrigido

- `standards.md` — instrução de i18n desatualizada: `load_plugin_textdomain()` deixou de ser
  necessário para plugins do WP.org (desde WP 4.6) e chamar `__()` antes do hook `init` emite o
  notice `_load_textdomain_just_in_time` desde o WP 6.7.
- `checklist.md` — nomes de hook errados na secção de privacidade: o registo faz-se pelos filtros
  `wp_privacy_personal_data_exporters` e `wp_privacy_personal_data_erasers`.
- `security.md` — `esc_url_raw()` identificada como função de sanitização, não de escaping.
- `standards.md` — atributos de script: o mecanismo atual é o 5.º argumento array de
  `wp_enqueue_script()` (WP 6.3+), não apenas `wp_script_add_data()`.

## [1.0.0] - 2026-05-25

### Adicionado

- Versão inicial da skill: `SKILL.md`, `README.md`.
- Referências: `security.md`, `performance.md`, `scaffolding.md`, `standards.md`, `checklist.md`,
  `hooks-catalog.md`.
- `examples/anti-patterns.md` com 20 pares "errado vs certo".
- Templates: `plugin-main.php`, `src/Plugin.php`, `uninstall.php`, `readme.txt`, `phpcs.xml.dist`,
  `index.php`, `.gitignore`.

[1.1.0]: https://github.com/antoniocostalopes/wp-plugin-best-practices/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/antoniocostalopes/wp-plugin-best-practices/releases/tag/v1.0.0
