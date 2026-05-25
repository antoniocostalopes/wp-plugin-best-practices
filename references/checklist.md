# Checklist Pré-Publicação WordPress.org

Use antes de submeter ao diretório WordPress.org ou empacotar release. Cada item violado é razão comum de rejeição.

## 1. Licença e cabeçalho

- [ ] Licença é **GPL v2 ou compatível** (GPLv3, MIT, BSD 2/3-clause). **Apache 2.0 NÃO é compatível com GPLv2** (conflito de cláusula de patentes) — só com GPLv3. Evite Apache 2.0 sob GPLv2-or-later
- [ ] Header inclui `License: GPL v2 or later` e `License URI`
- [ ] Arquivo `LICENSE` ou `LICENSE.txt` na raiz
- [ ] Header inclui `Plugin Name`, `Description`, `Version`, `Author`
- [ ] Header inclui `Requires at least` e `Requires PHP` (corretos)
- [ ] Header inclui `Text Domain` igual ao slug
- [ ] **Nenhuma** dependência com licença incompatível (proprietária, no-derivatives)

## 2. readme.txt válido

Use [validator oficial](https://wordpress.org/plugins/developers/readme-validator/).

- [ ] `=== Plugin Name ===` na primeira linha
- [ ] `Contributors:` com username(s) WordPress.org
- [ ] `Tags:` ≤5, relevantes
- [ ] `Requires at least:` consistente com header
- [ ] `Tested up to:` versão WP atual ou recente
- [ ] `Requires PHP:` consistente com header
- [ ] `Stable tag:` versão atual (não `trunk` em release)
- [ ] `License:` declarado
- [ ] Short description ≤140 caracteres (limite oficial WP.org)
- [ ] Seções: `== Description ==`, `== Installation ==`, `== Frequently Asked Questions ==`, `== Changelog ==`, `== Screenshots ==` (se houver)
- [ ] Cada release tem entrada no changelog

## 3. Segurança (bloqueador)

Rejeição automática se falhar qualquer um:

- [ ] Todo arquivo PHP começa com `defined( 'ABSPATH' ) || exit;`
- [ ] Toda ação que muda estado tem nonce verificado
- [ ] Toda ação que muda estado tem `current_user_can()` apropriado
- [ ] Todo input externo é sanitizado com função certa
- [ ] Todo output é escapado (esc_html/esc_attr/esc_url/wp_kses)
- [ ] Toda query SQL custom usa `$wpdb->prepare()`
- [ ] Sem `eval()`, `assert()` com strings, `create_function()`
- [ ] Sem `unserialize()` em input externo
- [ ] Sem credenciais hardcoded (API keys, secrets)
- [ ] HTTP requests usam `wp_remote_*` (não `file_get_contents`, `curl_exec`)
- [ ] REST endpoints têm `permission_callback` (nunca `__return_true` em writes)
- [ ] Uploads validam tipo via `wp_check_filetype_and_ext` com allowlist

Detalhe em `references/security.md`.

## 4. Boas práticas WP (rejeições comuns)

- [ ] **Nenhum** "phone home" sem opt-in explícito (tracking, analytics)
- [ ] **Nenhum** redirect ou abertura de página após ativação sem opt-in
- [ ] **Nenhum** admin notice persistente sem dismissable
- [ ] **Nenhuma** menu/submenu desnecessário (não polua admin)
- [ ] **Nenhum** script/CSS enfileirado em todas as páginas
- [ ] **Nenhuma** dependência de CDN externo (Google Fonts, jQuery CDN, etc.) — bundle local
- [ ] **Nenhum** iframe externo com URL hardcoded
- [ ] Plugin **não** modifica core, temas, ou outros plugins
- [ ] Plugin **não** depende de serviço pago/SaaS para funcionalidade básica (premium tier ok, mas funcionalidade core grátis precisa funcionar sem ele)
- [ ] Sem branding agressivo (banners, badges grandes no admin)
- [ ] Sem upsells obstrutivos (1 link discreto está ok, banner full-width não)

## 5. Padrões e estrutura

- [ ] Todo identifier prefixado (funções, classes, hooks, options, meta, capabilities)
- [ ] Prefixo único, ≥4 caracteres
- [ ] Sem nomes genéricos: `init()`, `Plugin`, `Admin`, `Helper`, `Settings` no namespace global
- [ ] Sem arquivos fora da pasta do plugin (uploads, must-use, raiz)
- [ ] `uninstall.php` ou `register_uninstall_hook` faz cleanup completo (opcional, mas ESPERADO)
- [ ] Activation/deactivation hooks **não** modificam dados de outros plugins
- [ ] **Não** desativa outros plugins
- [ ] **Não** apaga tabelas/options de outros plugins

## 6. i18n

- [ ] Toda string visível ao usuário em função de tradução (`__`, `_e`, `esc_html__`, etc.)
- [ ] Text domain igual ao slug do plugin
- [ ] `Domain Path: /languages` no header
- [ ] `load_plugin_textdomain` chamado em `init` (para uso fora do WP.org — WP.org carrega automaticamente desde WP 4.6)
- [ ] Strings com placeholders têm comentário `/* translators: */`
- [ ] Plurais usam `_n()`, não condicional
- [ ] Arquivo `.pot` em `languages/`

## 7. Performance

- [ ] Scripts/styles enfileirados só onde necessários (condicionais por página)
- [ ] Sem queries em `init` global ou `wp_loaded` sem condição
- [ ] Options grandes têm `autoload=no`
- [ ] Sem `posts_per_page => -1` em produção
- [ ] Sem loops com queries internas (usar bulk fetch + cache priming)
- [ ] Transients usados para chamadas externas e queries caras
- [ ] Tabelas custom têm índices em colunas filtradas
- [ ] Wp-cron events são limpos no deactivate

## 8. Compatibilidade

- [ ] Testado em WordPress versão atual e duas anteriores
- [ ] Testado no PHP mínimo declarado e na versão atual
- [ ] **Sem** uso de função PHP acima do mínimo declarado (use phpcompatibility)
- [ ] **Sem** uso de função WP depreciada (verifique no Plugin Handbook)
- [ ] Funciona com `WP_DEBUG = true` sem PHP notices/warnings
- [ ] Funciona em multisite (declare `Network: true` se for network-only)

## 9. Conteúdo e nomenclatura

- [ ] Nome do plugin **não** contém: "WordPress", "WP" (no início), "Plugin"
- [ ] Nome do plugin **não** copia ou se parece com plugin popular existente
- [ ] Nome do plugin **não** infringe trademark (Facebook, Google, etc. — use "for Facebook" não "Facebook Plugin")
- [ ] Slug é único no WP.org
- [ ] Descrição clara, sem hype ("the best", "amazing", "revolutionary")
- [ ] Screenshots reais (não mockups com placeholders)

## 10. Assets do plugin (banner, ícone)

Não vão na pasta do plugin — vão em `assets/` na raiz do SVN no WP.org:

- [ ] `banner-772x250.png` (low-res)
- [ ] `banner-1544x500.png` (high-res, opcional)
- [ ] `icon-128x128.png` ou `icon-256x256.png` (recomendado)
- [ ] `screenshot-1.png`, `screenshot-2.png`, etc. — correspondem ao readme.txt

## 11. Privacidade / GDPR

- [ ] Se coleta dados pessoais, declara em `wp_add_privacy_policy_content`
- [ ] Se grava em log/banco, oferece data export via `wp_privacy_personal_data_export_file`
- [ ] Se grava em log/banco, oferece data erasure via `wp_privacy_personal_data_erase`
- [ ] Não envia dados a terceiros sem opt-in explícito
- [ ] Documenta cookies usados (se houver)

## 12. Acessibilidade

- [ ] Forms admin têm `<label>` associados
- [ ] Botões têm texto descritivo (não só ícone)
- [ ] Ícone-only buttons têm `aria-label` ou screen-reader text (`.screen-reader-text`)
- [ ] Cores têm contraste mínimo WCAG AA
- [ ] Funciona com teclado (Tab navega tudo)

## 13. Quality gates

Rode antes de empacotar:

```bash
# Coding standards
vendor/bin/phpcs --standard=phpcs.xml.dist

# Static analysis
vendor/bin/phpstan analyse src --level=6

# PHP compatibility
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.0-

# Plugin Check (oficial WP)
wp plugin-check acme-widgets

# Validar readme.txt
# https://wordpress.org/plugins/developers/readme-validator/
```

## 14. Empacotamento

- [ ] Zip contém **apenas** os arquivos necessários
- [ ] **Não** inclui: `node_modules/`, `vendor/dev-only/`, `.git/`, `tests/`, `.github/`, `*.zip`, `composer.lock` (debatível)
- [ ] Tamanho do zip <10MB (WP.org limita 10MB efetivamente; >5MB já é considerado grande)
- [ ] `composer install --no-dev --optimize-autoloader` rodado se usa Composer
- [ ] Assets `build/` compilados (não `src/`)
- [ ] Versão no header, readme.txt, e constante batem

`.distignore` recomendado:

```
.git
.github
.gitignore
.editorconfig
.distignore
.phpcs.xml.dist
phpcs.xml.dist
phpstan.neon
composer.json
composer.lock
node_modules
package.json
package-lock.json
tests
docs
*.md
!readme.txt
*.zip
```

## 15. Submissão ao WP.org

1. Plugin Name único — verifique em https://wordpress.org/plugins/
2. Upload zip em https://wordpress.org/plugins/developers/add/
3. Review humano leva 1-14 dias
4. Após aprovação, acesso SVN: `https://plugins.svn.wordpress.org/your-plugin/`
5. Estrutura SVN:
   - `trunk/` — código atual em desenvolvimento
   - `tags/X.Y.Z/` — releases imutáveis
   - `assets/` — banners, screenshots, ícone
6. Lançar release: copia `trunk` para `tags/X.Y.Z`, atualiza `Stable tag` no `readme.txt` do `trunk`

## Razões mais comuns de rejeição (por frequência)

1. Falta de sanitização/escaping (XSS, SQLi)
2. Ausência de nonces em forms
3. Phone home / tracking sem opt-in
4. Strings sem i18n
5. Hardcode de URLs externas (CDN, fontes)
6. Modificação de outros plugins/temas/core
7. Includes diretos de bibliotecas (jQuery, etc.) — use WP bundled
8. Falta de prefixo em funções/classes globais
9. Direct file access sem `defined( 'ABSPATH' )`
10. Trademark no nome ("Facebook Plugin")

## Plugin Check (ferramenta oficial)

Instale o plugin oficial **Plugin Check** ou rode via WP-CLI:

```bash
wp plugin install plugin-check --activate
wp plugin-check acme-widgets
```

Ele roda muitos dos checks acima automaticamente. Use como gate antes de submeter.
