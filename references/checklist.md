# Checklist Pré-Publicação WordPress.org

Use antes de submeter ao diretório WordPress.org ou empacotar release. Cada item violado é razão comum de rejeição.

Handbook: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ · [Common Issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/) · [Revisão automática](https://developer.wordpress.org/plugins/wordpress-org/automated-security-review/)

## Conteúdo

- [1. Licença e cabeçalho](#1-licença-e-cabeçalho)
- [2. readme.txt válido](#2-readmetxt-válido)
- [3. Segurança (bloqueador)](#3-segurança-bloqueador)
- [4. Boas práticas WP (rejeições comuns)](#4-boas-práticas-wp-rejeições-comuns)
- [5. Padrões e estrutura](#5-padrões-e-estrutura)
- [6. i18n](#6-i18n)
- [7. Performance](#7-performance)
- [8. Compatibilidade](#8-compatibilidade)
- [9. Conteúdo e nomenclatura](#9-conteúdo-e-nomenclatura)
- [10. Assets do plugin (banner, ícone)](#10-assets-do-plugin-banner-ícone)
- [11. Privacidade / GDPR](#11-privacidade--gdpr)
- [12. Acessibilidade](#12-acessibilidade)
- [13. Quality gates](#13-quality-gates)
- [14. Empacotamento](#14-empacotamento)
- [15. Submissão ao WP.org](#15-submissão-ao-wporg)
- [SVN do WordPress.org — release, não desenvolvimento](#svn-do-wordpressorg--release-não-desenvolvimento)
- [Papéis no diretório (owner, committer, support rep, contributor)](#papéis-no-diretório-owner-committer-support-rep-contributor)
- [Página do plugin: readme, assets e changelog](#página-do-plugin-readme-assets-e-changelog)
- [Revisão automática de segurança do WP.org](#revisão-automática-de-segurança-do-wporg)
- [Common Issues — o que a Plugin Review Team mais devolve](#common-issues--o-que-a-plugin-review-team-mais-devolve)
- [As 18 Detailed Plugin Guidelines — o que verificar no código](#as-18-detailed-plugin-guidelines--o-que-verificar-no-código)
- [Razões mais comuns de rejeição (por frequência)](#razões-mais-comuns-de-rejeição-por-frequência)
- [Plugin Check (ferramenta oficial)](#plugin-check-ferramenta-oficial)

## 1. Licença e cabeçalho

- [ ] Licença é **GPL v2 ou compatível** (GPLv3, MIT, BSD 2/3-clause). **Apache 2.0 NÃO é compatível com GPLv2** (conflito de cláusula de patentes) — só com GPLv3. Evite Apache 2.0 sob GPLv2-or-later
- [ ] Header inclui `License: GPL v2 or later` e `License URI`
- [ ] Ficheiro `LICENSE` ou `LICENSE.txt` na raiz
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

- [ ] Todo ficheiro PHP começa com `defined( 'ABSPATH' ) || exit;`
- [ ] Toda ação que muda estado tem nonce verificado
- [ ] Toda ação que muda estado tem `current_user_can()` apropriado
- [ ] Todo input externo é sanitizado com função certa
- [ ] Todo output é escapado (esc_html/esc_attr/esc_url/wp_kses)
- [ ] Toda query SQL custom usa `$wpdb->prepare()`
- [ ] Sem `eval`, `assert` com strings, `create_function`
- [ ] Sem `unserialize` em input externo
- [ ] Sem credenciais hardcoded (API keys, secrets)
- [ ] HTTP requests usam `wp_remote_*` (não `file_get_contents`, `curl_exec`)
- [ ] REST endpoints têm `permission_callback` (nunca `__return_true` em writes)
- [ ] Capabilities: meta cap + ID em ações sobre objeto (`edit_post`, não `edit_posts`); zero verificação de role
- [ ] Meta registada com `sanitize_callback` e `auth_callback`; `show_in_rest` não expõe dados privados
- [ ] URL vinda de input/option usa `wp_safe_remote_*`
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
- [ ] Sem ficheiros fora da pasta do plugin (uploads, must-use, raiz)
- [ ] `uninstall.php` ou `register_uninstall_hook` faz cleanup completo (opcional, mas ESPERADO)
- [ ] Activation/deactivation hooks **não** modificam dados de outros plugins
- [ ] **Não** desativa outros plugins
- [ ] **Não** apaga tabelas/options de outros plugins

## 6. i18n

- [ ] Toda string visível ao utilizador em função de tradução (`__`, `_e`, `esc_html__`, etc.)
- [ ] Text domain igual ao slug do plugin
- [ ] `Domain Path: /languages` no header
- [ ] `load_plugin_textdomain` **apenas** se distribuir fora do WP.org (WP.org carrega automaticamente desde WP 4.6)
- [ ] Nenhuma chamada a `__()` antes do hook `init` (WP 6.7 emite `_load_textdomain_just_in_time was called incorrectly`)
- [ ] Strings com placeholders têm comentário `/* translators: */`
- [ ] Plurais usam `_n()`, não condicional
- [ ] Ficheiro `.pot` em `languages/`
- [ ] JS traduzido: `wp_set_script_translations()` + dependência `wp-i18n` + `wp i18n make-json`

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

- [ ] Se coleta dados pessoais, declara em `wp_add_privacy_policy_content()` (hook `admin_init`)
- [ ] Exporter registado no filtro `wp_privacy_personal_data_exporters`, com paginação
- [ ] Eraser registado no filtro `wp_privacy_personal_data_erasers`, com `messages` ao reter dados
- [ ] IP anonimizado com `wp_privacy_anonymize_ip()` quando o valor exato não é necessário
- [ ] Zero dados pessoais em `error_log`
- [ ] Não envia dados a terceiros sem opt-in explícito (default **off**)
- [ ] Documenta cookies usados (se houver)
- [ ] Prazo de retenção definido; uninstall apaga dados pessoais

Detalhe e código em `references/privacy.md`.

## 12. Acessibilidade

- [ ] Forms admin têm `<label>` associados
- [ ] Botões têm texto descritivo (não só ícone)
- [ ] Ícone-only buttons têm `aria-label` ou screen-reader text (`.screen-reader-text`)
- [ ] Cores têm contraste mínimo WCAG AA
- [ ] Funciona com teclado (Tab navega tudo)

## 13. Quality gates

Corra antes de empacotar:

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

- [ ] Zip contém **apenas** os ficheiros necessários
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

> 🤖 O WordPress.org tem servidor MCP (`npx -y @wporg/mcp`) com ferramentas de validar readme, ver estado da revisão e submeter plugin, mais os prompts "Prepare Plugin for Submission" e "Address Review Feedback". Não dispensa correr o Plugin Check localmente nem a revisão humana. Detalhe em `references/developer-tools.md`.

1. Plugin Name único — verifique em https://wordpress.org/plugins/
2. Upload do zip em https://wordpress.org/plugins/developers/add/ (**≤10 MB**, formato instalável por "Upload Plugin", pronto para produção)
3. Email automático confirma a submissão e indica o **slug**
4. Revisão humana. Sem problemas: aprovado (normalmente até 14 dias após a revisão inicial). Com problemas: email com assunto `[WordPress Plugin Directory] Review in Progress: {nome}` — verifique o spam
5. Após aprovação, acesso SVN em `https://plugins.svn.wordpress.org/seu-plugin/`

### O slug decide tudo e é irreversível

Gerado a partir de `Plugin Name:` do ficheiro principal. Define:

- URL pública: `wordpress.org/plugins/acme-widgets`
- Pasta de instalação: `wp-content/plugins/acme-widgets`
- Repositório SVN e trac
- **Text domain** das funções de i18n

Depois de aprovado **não pode ser mudado** — só o display name (editando `Plugin Name:` no ficheiro principal e no readme). Antes da aprovação pode alterar-se **uma vez** pela página de submissão.

Restrições de nome/slug:

- Sem vulgaridades; sem `wordpress` nem `plugin` no slug (salvo casos extremos); sem números de versão
- Só letras inglesas e algarismos árabes
- Não pode **começar por** — nem conter de forma confundível — marca registada ou nome de projeto/biblioteca alheios, salvo representante oficial. Contornar com grafia alterada (`WuuCommerce`, `Face-Range`) é pior: a equipa suspende a submissão
- Plugin oficial de uma organização tem de ser submetido pela conta da organização (o domínio do email conta; conta de gmail leva a flag de trademark)

### Regras da fila de revisão

| Situação | Regra |
|---|---|
| Submissões em simultâneo | **Uma**. Autores com >1 milhão de instalações ativas: até 10 |
| Várias contas para contornar | Suspensão de todas as contas secundárias |
| Resposta da equipa | Até 10 dias úteis por revisão; `plugins@wordpress.org` responde em 7 dias úteis |
| Prazo para corrigir | Sem limite enquanto houver progresso visível. Aos **3 meses** a submissão é rejeitada, mas a revisão fica aberta |
| Rejeitado aos 3 meses | Submeter outra vez **e** responder ao email antigo. Mesmo passados 18 meses |
| Rejeitado por outro motivo | **Não** resubmeter — responder ao email |
| Submetido com a conta errada | Responder ao email de imediato; ou adicionar a conta certa como committer e remover a errada |
| Precisa de data específica | Não há furar a fila, exceto questão de segurança ou legal (email a explicar) |

### Plugins que não são aceites

- Sem finalidade real ou sem funcionalidade prática
- Que facilitem atividade ilegal ou nociva: black-hat SEO, content spinners, geradores de conteúdo falso, scrapers, conteúdo de ódio
- **Novos** plugins que permitam inserir/executar código arbitrário: editores de PHP/JS, gestores de ficheiros, ferramentas de IA que gerem código para ser executado no site (output HTML é permitido, se devidamente escapado)
- Que descarreguem ou executem código externo, ou que sirvam só para instalar outros plugins (guideline #8)
- **Frameworks e bibliotecas**: se obriga outros plugins/temas a editarem-se para o usar, é biblioteca; se é um template para construir código por cima, é framework. Empacote-os dentro de cada plugin
- Cópias de trabalho existente, ou duplicação do que o core já faz
- Funcionalidade já representada por centenas de alternativas, sem diferenciação clara

**Rebranding/reescrita:** não submeta plugin novo — faça major release do existente. Não há forma de renomear nem transferir utilizadores, avaliações, tópicos de suporte, downloads ou favoritos.

### Erros que a equipa vê com mais frequência (resumo do FAQ)

Não incluir `readme.txt` quando o plugin atua como serviço · não testar com `WP_DEBUG` · incluir versões próprias de bibliotecas JS já empacotadas · chamar ficheiros externos sem necessidade · links "Powered by" · phone home.

## SVN do WordPress.org — release, não desenvolvimento

https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

**Conta:** o username é o do WordPress.org (não o email) e a **capitalização importa** — `JaneDoe` falha como `janedoe`. Defina uma password específica para SVN em Account Settings (`profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password`).

**Três pastas** (a `branches/` já não é criada — ignore-a):

| Pasta | Conteúdo |
|---|---|
| `trunk/` | Versão em desenvolvimento, sempre atualizada mesmo que desenvolva em Git |
| `tags/X.Y.Z/` | Releases. Só números e pontos no nome da tag (`2.8.4` sim, `minha release` não) |
| `assets/` | Screenshots, banner, ícone, `blueprints/blueprint.json` |

### Fluxo de release correto

```bash
svn co https://plugins.svn.wordpress.org/acme-widgets
# editar em trunk/, incluindo Stable Tag no trunk/readme.txt
svn ci -m "Prepara 1.4.0"
svn cp trunk tags/1.4.0       # tag copiada DE trunk, não escrita diretamente
svn ci -m "Tag 1.4.0"
```

Copiar de `trunk` em vez de escrever direto na tag faz commits mais pequenos (o SVN só envia o que mudou) e permite comparar versões.

### Gotchas que partem o plugin

- ⚠️ **O ficheiro principal não pode estar numa subpasta de `trunk/`** (`trunk/acme-widgets/acme-widgets.php`) — quebra o gerador de zip. `readme.txt` e ficheiro principal vão direitos para `trunk/`; subpastas só para includes.
- **Cada push reconstrói todos os zips de todas as versões.** Daí as atualizações demorarem até 6 horas a aparecer. Faça **um** commit quando estiver pronto, não commits de desenvolvimento.
- **Assim que houver código no SVN, o plugin está live.** Não há botão de desligar — só fechar o plugin, o que é permanente.
- `Stable Tag: trunk` complica rollbacks e não é suportado. Use sempre tag.
- **SVN externals não entram no zip.** Zips e comprimidos dentro do plugin: não permitidos.
- Guarde poucas releases antigas — uma ou duas por linha de versão chega. O SVN não é o seu versionamento.
- Screenshots em `assets/`, não em `trunk/` — mantém o zip pequeno (não são enviados para os sites).
- JS minificado é permitido **se** mantiver a versão não-minificada no plugin ou indicar no readme onde a obter.

### Quando é preciso nova versão

| Mudou | Nova versão? |
|---|---|
| Código | **Sim** — sem bump ninguém recebe a atualização |
| Só readme, ícone ou banner | Não. Atualize `trunk` **e** a tag |

## Papéis no diretório (owner, committer, support rep, contributor)

https://developer.wordpress.org/plugins/wordpress-org/special-user-roles-capabilities/

Cada pessoa que faz push ou dá suporte precisa de **conta individual própria** (pode ser não-identificável, tipo `AcmeSupport1`, mas uma conta por humano). Geridos na Advanced view da página do plugin.

| Papel | Pode | Aparece como |
|---|---|---|
| **Owner** | Definido por quem submete; transfere a propriedade na "Danger Zone" (o novo dono tem de ter commit access antes) | — |
| **Committer** | Push por SVN, pedir fecho do plugin, adicionar/remover committers e support reps | "Plugin Author" nos fóruns |
| **Support Rep** | Nada sobre o plugin; marca tópicos como resolvidos | "Plugin Support" nos fóruns, listado na página do plugin |
| **Contributor** | Nada sobre o plugin; marca tópicos como resolvidos | "Plugin Contributor"; listado em "Contributors & Developers" |

Pontos que confundem:

- **Commit access não aparece publicamente.** Para constar em "Contributors & Developers" e no perfil WordPress.org, a pessoa tem de estar no campo `Contributors` do `readme.txt`.
- Só um committer existente pode adicionar/remover committers e support reps.
- Se o owner original desapareceu, contacte a equipa de plugins.

## Página do plugin: readme, assets e changelog

- **`Tested up to`**: nunca acima da versão estável atual (ou do RC, se existir). Pôr `6.5` ou `7` inventados dá erro na página. É também isto que causa o aviso "não testado com as versões recentes".
- **Tags do readme**: máximo 5 — só as 5 primeiras contam. Tag usada **apenas** pelo seu plugin não é mostrada (não ajuda a descoberta).
- **Changelog**: mantenha a versão atual e uma major anterior; o resto migra para `changelog.txt`. Formato sugerido: Keep a Changelog.
- **Vídeos na descrição**: colar o link do YouTube/Vimeo numa linha isolada (o vídeo tem de permitir embed).
- **Banner e ícone**: ficheiros com nome correto em `assets/`. **Não** use logótipos oficiais de terceiros — mesmo com permissão para o seu site, o diretório não a tem.
- A página do diretório atualiza de poucos em poucos minutos, mas dê **6 horas** antes de reportar problema.

## Revisão automática de segurança do WP.org

https://developer.wordpress.org/plugins/wordpress-org/automated-security-review/

Desde junho de 2026, **todas** as releases passam por um período de *cooldown* antes de serem distribuídas pela update API (a que alimenta as notificações e a atualização com um clique). Durante o cooldown, as alterações da release são analisadas por vários modelos de IA em conjunto com o Jetpack Scan; os resultados são cruzados e convertidos em *findings* com pontuação de risco.

| Pontuação máxima | O que acontece |
|---|---|
| Abaixo do limiar | Distribuição normal. Sem email, sem ação |
| No limiar ou acima | Release **bloqueada** assim que a análise acaba; todos os committers recebem email com os findings (ficheiro e linha, com link para o código) |

O que "bloqueada" significa, ao certo:

- Sites que já têm o plugin **não** recebem a versão bloqueada; continuam na anterior. Nada parte nos sites existentes.
- Só **aquela versão** é bloqueada. O plugin **não** é fechado, a página fica no ar, versões anteriores continuam disponíveis.
- O bloqueio **persiste depois do cooldown**, até ser resolvido numa release seguinte.

Como desbloquear:

1. Ler os findings do email — descrevem exatamente o que disparou o bloqueio.
2. Corrigir e **lançar uma versão nova**. O bloqueio aplica-se só à versão marcada; a nova passa por cooldown e análise normais.

Notas:

- Pontuação alta **não** implica intenção maliciosa — mede risco. Vulnerabilidade introduzida por acidente pontua igual a malware.
- Há falsos positivos. Podem ser contestados por resposta ao email, mas **lançar a correção é quase sempre mais rápido** do que esperar pela revisão manual do recurso.
- Toda a release nova é reanalisada, incluindo a que corrige uma bloqueada.

Consequência prática para o desenvolvimento: `references/security.md` deixou de ser boa prática opcional — falhas aí bloqueiam a distribuição, mesmo depois de o plugin já estar aprovado.

## Common Issues — o que a Plugin Review Team mais devolve

https://developer.wordpress.org/plugins/wordpress-org/common-issues/

Excertos dos emails reais da equipa, agrupados. Mantra oficial: **Sanitize early, Escape late, Always validate.**

### Segurança e código

- [ ] Input sanitizado à entrada, validado, escapado à saída — com a função **do contexto**
- [ ] Funções de escaping não usadas para sanitizar, nem sanitizadores para escapar (são filtráveis — outro plugin pode mudar o que fazem)
- [ ] `esc_url_raw()` reconhecido como **sanitização**; o escaping de URL é `esc_url()`
- [ ] `__()` e `_e()` nunca ecoados sem escapar — use `esc_html__()`, `esc_html_e()`, `esc_attr__()`
- [ ] `esc_html()` **não** usado para "escapar HTML" — para isso, `wp_kses_post()` / `wp_kses()`
- [ ] JSON ecoado com `wp_json_encode()` (e sem flags que anulem o escaping)
- [ ] `filter_input`/`filter_var` com filtro explícito
- [ ] Nonce sanitizado antes de `wp_verify_nonce()` (função pluggable)
- [ ] Sem percorrer `$_POST`/`$_GET`/`$_REQUEST` inteiros
- [ ] `$wpdb->prepare()` em todas as queries; arrays via placeholders gerados
- [ ] Sem HEREDOC/NOWDOC e sem short tags (`<?`, `<?=`)
- [ ] `defined( 'ABSPATH' ) || exit;` no topo dos ficheiros
- [ ] `wp_handle_upload()`, nunca `move_uploaded_file()`; `ALLOW_UNFILTERED_UPLOADS` ausente
- [ ] Sem `ini_set()`, `date_default_timezone_set()`, `error_reporting()` globais

### Estrutura e nomenclatura

- [ ] Ficheiro principal com o **mesmo nome da pasta/slug**; headers só nele
- [ ] Prefixo ≥4 caracteres, único; nunca `wp_`, `_`, `__`, nem o prefixo do plugin-pai
- [ ] Sem `if ( ! function_exists() )` a envolver o código próprio (só para bibliotecas partilhadas)
- [ ] Assets por `wp_enqueue_*` / `wp_add_inline_script` / `wp_add_inline_style`
- [ ] Bibliotecas do core (jQuery, PHPMailer, SimplePie, PHPass…) usadas do core, não incluídas
- [ ] HTTP API em vez de `curl_*` no código próprio

### Dependências e licença

- [ ] Sem bibliotecas beta/alpha (salvo justificação técnica), sem bibliotecas descontinuadas, sem versões desatualizadas
- [ ] Licença GPL-compatível declarada **e igual** no header e no readme
- [ ] Código de terceiros com licença compatível e documentada
- [ ] Sem código de fonte "premium" que proíba redistribuição gratuita
- [ ] Ficheiros minificados/compilados com fonte incluída **ou** link público documentado no readme
- [ ] `composer.json` incluído quando se usa Composer (mesmo que só para dev)

### Ficheiros distribuídos

- [ ] Sem pastas de desenvolvimento no ZIP: `node_modules`, `bower_components`, grunt, demos, testes unitários
- [ ] Só tipos de ficheiro esperados: `php`, `js`, `css`, `txt`, `md`, `png`, `svg`, `jpg`, `json`, `xml`

### Comportamento

- [ ] **Sem update checker próprio** — o WP.org é que distribui; interferir com o updater do core é motivo de rejeição
- [ ] Não ativa nem desativa outros plugins (exceção: autodesativar-se quando falta uma dependência — prefira o header `Requires Plugins`, WP 6.5+)
- [ ] Serviço externo de terceiros documentado no readme: o que faz, quando é chamado, link do serviço e link dos termos/política de privacidade
- [ ] Sem ficheiros remotos (JS/CSS/imagens de CDN ou do seu domínio) — incluir localmente

**Exceções que a equipa aceita para chamadas remotas:** famílias de fontes do Google ou CDN aprovado (se GPL-compatível), chamadas à sua API para processar dados (tipo Akismet), comentários alojados no seu servidor (tipo Disqus), e `oEmbed` para fornecedores (YouTube, Twitter). O que **não** passa: alojar CSS/JS/imagens próprias fora do plugin, ou iframes de documentação.

### readme e versões

- [ ] `Stable Tag` **igual** à `Version` do ficheiro principal — desalinhados, os utilizadores recebem a versão errada
- [ ] `Stable Tag: trunk` evitado (funciona, mas não é suportado e causa problemas nas atualizações automáticas)
- [ ] SemVer para numeração
- [ ] `readme.txt` (não `readme.md`) e a passar no validador
- [ ] Readme completo obrigatório para primeiros plugins, plugins com dependências e plugins que chamam serviços externos

### i18n

- [ ] Zero variáveis ou constantes como texto, contexto ou text domain em funções gettext — o parser lê sem executar
- [ ] Text domain literal, igual ao slug

## As 18 Detailed Plugin Guidelines — o que verificar no código

Texto oficial: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

| # | Guideline | Verificação concreta |
|---|---|---|
| 1 | Compatível com GPL | Header `License: GPLv2 or later`; **toda** lib de terceiros com licença compatível (nada de CC-NC nem proprietário) |
| 2 | Autor responsável pelo conteúdo | Rever código de terceiros incluído; cumprir ToS das APIs usadas |
| 3 | Versão estável disponível no diretório | `Stable Tag` aponta para tag existente; sem "descarregue a versão boa no meu site" |
| 4 | Código legível | Sem ofuscação, sem `base64`/`eval` de payload, sem minificado-sem-fonte. Se há build, publicar fonte + passos |
| 5 | Sem trialware | Nada de bloqueio por tempo ou por pagamento na funcionalidade que o plugin anuncia. Freemium só com core grátis funcional |
| 6 | SaaS permitido com condições | Serviço externo tem de acrescentar valor real, estar documentado no readme, com ToS e política de privacidade linkados |
| 7 | Sem tracking sem consentimento | Opt-in explícito (default off) para qualquer envio para fora, incluindo telemetria e "check de licença" |
| 8 | Sem código executável de terceiros | Zero `wp_remote_get` de JS/PHP para executar; zero `<script src>` de CDN; zero updaters que instalam código fora do WP.org |
| 9 | Nada ilegal/desonesto/ofensivo | Sem manipular reviews, sem spam, sem scraping abusivo |
| 10 | Sem links ou créditos no front sem permissão | "Powered by" no site é opt-in, default off |
| 11 | Não sequestrar o admin | Notices dismissable, contextuais, sem full-width permanente; sem redirect para página de boas-vindas sem opt-in |
| 12 | Readme sem spam | Sem keyword stuffing, sem afiliados a granel, tags relevantes (máx. 5) |
| 13 | Usar as libs do core | `wp_enqueue_script( 'jquery' )` do core; PHPMailer, SimplePie, Moment, React via `wp-element`. Nunca bundle da própria cópia |
| 14 | Commits infrequentes no SVN | SVN é repositório de **releases**, não de desenvolvimento. Desenvolva no Git, publique tags |
| 15 | Versão incrementada a cada release | `Version:` do header **e** `Stable Tag` do readme sobem juntos (`version_compare` tem de dar "maior") |
| 16 | Plugin completo na submissão | Sem "reservar o slug"; sem "brevemente" |
| 17 | Respeitar marcas registadas | Slug não começa por marca alheia (`woocommerce-*`, `facebook-*`). Use `acme-for-woocommerce` |
| 18 | WP.org pode intervir | Mantenha contacto de email válido e responda a pedidos da equipa do Plugin Review |

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

Instale o plugin oficial **Plugin Check** ou corra via WP-CLI:

```bash
wp plugin install plugin-check --activate
wp plugin-check acme-widgets
```

Ele roda muitos dos checks acima automaticamente. Use como gate antes de submeter.

Em CI, use `wordpress/plugin-check-action@v1` para correr em cada PR. Deploy automático para o SVN com `10up/action-wordpress-plugin-deploy`. Ver `references/developer-tools.md`.
