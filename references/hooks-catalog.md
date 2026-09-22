# Catálogo de Hooks WordPress

Lookup "quero X → use hook Y na prioridade Z". Use ao decidir onde plugar lógica nova. Cada hook tem **timing** (quando dispara) e **gotcha** (armadilha comum).

Handbook: https://developer.wordpress.org/plugins/hooks/

## Conteúdo

- [Como ler este catálogo](#como-ler-este-catálogo)
- [1. Ciclo de vida do request (front-end)](#1-ciclo-de-vida-do-request-front-end)
- [2. Ciclo de vida do request (admin)](#2-ciclo-de-vida-do-request-admin)
- [3. Posts e custom post types](#3-posts-e-custom-post-types)
- [4. Taxonomias e termos](#4-taxonomias-e-termos)
- [5. Utilizadores e autenticação](#5-utilizadores-e-autenticação)
- [6. Assets (enqueue)](#6-assets-enqueue)
- [7. AJAX e REST](#7-ajax-e-rest)
- [8. Mídia e uploads](#8-mídia-e-uploads)
- [9. Comentários](#9-comentários)
- [10. Crons e schedules](#10-crons-e-schedules)
- [11. Options e meta](#11-options-e-meta)
- [12. URLs e rewrite](#12-urls-e-rewrite)
- [13. Block editor (Gutenberg)](#13-block-editor-gutenberg)
- [14. WooCommerce (se aplicável)](#14-woocommerce-se-aplicável)
- [Padrões de prioridade](#padrões-de-prioridade)
- [Hooks que poucos conhecem mas resolvem problemas](#hooks-que-poucos-conhecem-mas-resolvem-problemas)
- [15. Remover hooks de outros (actions/filters)](#15-remover-hooks-de-outros-actionsfilters)
- [16. Criar hooks próprios (extensibilidade)](#16-criar-hooks-próprios-extensibilidade)
- [17. Heartbeat API — polling do servidor](#17-heartbeat-api--polling-do-servidor)

## Como ler este catálogo

| Coluna | Significado |
|---|---|
| **Hook** | Nome do action/filter |
| **Tipo** | `action` (faz algo) ou `filter` (modifica valor) |
| **Quando** | Em que momento do ciclo de request dispara |
| **Use para** | Quando este é o hook certo |
| **Gotcha** | O que dá errado com frequência |

Prioridade default: `10`. Use `< 10` para correr **antes** de outros plugins; `> 10` para **depois**. **Nunca** use `PHP_INT_MAX` sem razão concreta.

---

## 1. Ciclo de vida do request (front-end)

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `muplugins_loaded` | action | Após MU plugins | Raríssimo. Não usar em plugins normais | Plugin normal não chegou ainda |
| `plugins_loaded` | action | Após todos plugins carregarem | Inicialização leve, registrar classes | Tema ainda não carregou; `wp_get_current_user()` ainda não funciona |
| `setup_theme` | action | Antes do tema setup | Modificar tema antes de boot | Pulado em XMLRPC/REST/AJAX |
| `after_setup_theme` | action | Tema configurado | Hookear features que dependem de theme support | `is_admin()` já funciona |
| `init` | action | WP totalmente carregado | **Padrão** para registrar CPT, taxonomies, shortcodes, traduções, REST | Dispara em **toda** request — não corra lógica cara aqui |
| `wp_loaded` | action | Após init | Raro; uso específico (ex: redirects condicionais) | — |
| `parse_request` | action | URL sendo parseada | Modificar query vars manualmente | Cuidado: já saiu do hook stack normal |
| `wp` | action | WP main query rodada | Acesso a `$wp_query` global pronto | Não dispara em admin/AJAX |
| `template_redirect` | action | Antes do template carregar | Redirects, custom headers, 404s | `wp_redirect()` precisa `exit;` depois |
| `template_include` | filter | Escolha do template | Substituir template (`single-X.php` etc.) | Retorne path absoluto |
| `wp_head` | action | `<head>` do front | Meta tags, scripts inline (último recurso) | Prefira `wp_enqueue_*` |
| `wp_footer` | action | Antes de `</body>` | Scripts inline finais | Prefira enqueue com `in_footer=true` |
| `shutdown` | action | Após resposta enviada | Logging, cleanup async | Response já foi para o utilizador; não echo |

## 2. Ciclo de vida do request (admin)

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `admin_init` | action | Toda página admin | Registrar settings, processar forms | Dispara em AJAX `admin-ajax.php` também — condicione com `! wp_doing_ajax()` se precisar |
| `admin_menu` | action | Construindo menu admin | `add_menu_page`, `add_submenu_page`, `add_options_page` | Dispara antes de `admin_init` |
| `network_admin_menu` | action | Multisite network admin | Network-level menus | — |
| `admin_enqueue_scripts` | action | Enqueue admin | `wp_enqueue_script/style` para admin | Recebe `$hook_suffix` — condicione por página |
| `admin_notices` | action | Topo do admin | Notices (success/error/warning/info) | Sempre escape mensagens; ofereça dismiss |
| `admin_footer` | action | Footer admin | Scripts inline admin | Raro hoje |
| `current_screen` | action | Ecrã do admin identificado | Lógica específica de ecrã | Use `get_current_screen()` para condicionais |
| `load-{hook_suffix}` | action | Antes da página admin carregar | Processar form da página atual | Use o `hook_suffix` retornado por `add_menu_page` |

## 3. Posts e custom post types

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `save_post` | action | Post guardado (criar/editar) | Guardar meta boxes | Dispara em autosave, revision, bulk edit — sempre verificar `wp_is_post_autosave()`, `wp_is_post_revision()`, capability |
| `save_post_{post_type}` | action | Save específico de CPT | Mesmo que acima, escopado | Mesmos gotchas |
| `wp_insert_post` | action | Post inserido (após save_post) | Lógica que precisa do ID final | Dispara também em revisões |
| `wp_after_insert_post` | action | **WP 5.6+** Após save + terms + meta gravados | Melhor hook pra lógica pós-save (notify, sync) | Antes deste, terms/meta podiam ainda não estar persistidos |
| `transition_post_status` | action | Status mudou | Trigger em publicação/agendamento | 3 args: `$new`, `$old`, `$post` |
| `{old_status}_to_{new_status}` | action | Transição específica | Ex: `draft_to_publish` | Frágil — prefira `transition_post_status` |
| `before_delete_post` | action | Antes de apagar | Cleanup que precisa do post ainda existir | `wp_trash_post` não dispara isso |
| `deleted_post` | action | Depois de apagar | Cleanup posterior | — |
| `pre_get_posts` | action | Antes da query principal | Modificar query do front (filtros, archives) | Sempre verificar `! is_admin() && $query->is_main_query()` |
| `the_content` | filter | Conteúdo sendo renderizado | Injetar conteúdo no post | Cuidado com loops infinitos; alta prioridade pode ser tarde demais |
| `the_title` | filter | Título sendo renderizado | Modificar título | Dispara em **muitos** contextos (menu, breadcrumb, etc.) — geralmente quer condicionar com `in_the_loop()` |
| `post_class` | filter | Classes CSS do post | Adicionar classes | — |
| `post_row_actions` | filter | Ações na lista admin | Adicionar/remover ações ("Edit", "Trash") | — |
| `manage_{post_type}_posts_columns` | filter | Colunas da listagem admin | Adicionar colunas | Combinado com `manage_{post_type}_posts_custom_column` |

## 4. Taxonomias e termos

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `created_term` | action | Termo criado | Sync, cache invalidation | — |
| `edited_term` | action | Termo editado | Sync | — |
| `delete_term` | action | Antes de apagar | Cleanup com termo ainda existente | — |
| `set_object_terms` | action | Termos atribuídos a objeto | Reagir a categorização | Dispara várias vezes em bulk |
| `{taxonomy}_add_form_fields` | action | Form de adicionar termo | Campos personalizados em termo | Combinado com `created_{taxonomy}` para guardar |
| `{taxonomy}_edit_form_fields` | action | Form de editar termo | Campos personalizados em edição | Combinado com `edited_{taxonomy}` |

## 5. Utilizadores e autenticação

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `user_register` | action | Utilizador criado | Trigger onboarding | Recebe só ID; carregue com `get_user_by('id', $id)` |
| `profile_update` | action | Perfil atualizado | Sync | Dispara em qualquer update, inclusive password reset |
| `delete_user` | action | Antes de apagar | Cleanup | — |
| `wp_login` | action | Login bem-sucedido | Log, redirect, sync | 2 args: `$user_login`, `$user` |
| `wp_logout` | action | Logout | Cleanup de sessão | — |
| `wp_authenticate_user` | filter | Validando login | Bloquear logins (rate limit, banlist) | Retorne `WP_Error` para bloquear |
| `authenticate` | filter | Auth pipeline | Auth personalizada | Cuidado: dispara várias vezes |
| `password_change_email` | filter | Email de mudança de senha | Customizar mensagem | — |
| `personal_options_update` | action | Próprio perfil atualizado | Validar campos próprios | Não dispara quando admin edita outro user |
| `edit_user_profile_update` | action | Admin atualizou outro user | Validar campos | Não dispara para próprio perfil |
| `show_user_profile` | action | Renderizando próprio perfil | Adicionar campos | — |
| `edit_user_profile` | action | Renderizando outro user | Adicionar campos | — |

## 6. Assets (enqueue)

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `wp_enqueue_scripts` | action | Front enqueue | Scripts/styles do front | **Não** dispara em admin |
| `admin_enqueue_scripts` | action | Admin enqueue | Scripts/styles admin | Recebe `$hook_suffix` |
| `login_enqueue_scripts` | action | Login enqueue | Custom de login page | — |
| `enqueue_block_editor_assets` | action | Editor Gutenberg | Scripts do editor (blocos) | Só editor admin |
| `enqueue_block_assets` | action | Editor + front | Scripts comuns aos dois | Dispara em ambos — condicione |
| `script_loader_tag` | filter | Tag `<script>` final | Adicionar `async`/`defer`/`type=module` | Prefira `strategy` arg em `wp_enqueue_script` (WP 6.3+) |
| `style_loader_tag` | filter | Tag `<link>` final | Modificar atributos | — |

## 7. AJAX e REST

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `wp_ajax_{action}` | action | AJAX autenticado | Handler AJAX para logged-in | Precisa `check_ajax_referer()` + `current_user_can()` |
| `wp_ajax_nopriv_{action}` | action | AJAX anônimo | Handler AJAX para visitantes | Mesmo nonce check; cuidado com rate limit |
| `rest_api_init` | action | REST inicializando | `register_rest_route` | Único lugar para registrar |
| `rest_pre_dispatch` | filter | Antes de dispatch | Bloquear/modificar request | Retorne `WP_Error` para abortar |
| `rest_post_dispatch` | filter | Após dispatch | Modificar response | — |
| `rest_request_before_callbacks` | filter | Antes do callback | Auth/rate limit antes do callback correr | — |
| `rest_authentication_errors` | filter | Pipeline de auth REST | Auth personalizada para REST | Retorne `WP_Error` ou `true`/`null` |

## 8. Mídia e uploads

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `add_attachment` | action | Attachment criado | Pós-processar imagem | Dispara antes de subsizes gerados |
| `wp_generate_attachment_metadata` | filter | Metadata sendo gerada | Customizar tamanhos/processar | Caro — não chamar manualmente em loop |
| `image_size_names_choose` | filter | Sizes no dropdown do editor | Mostrar custom sizes ao utilizador | Combinado com `add_image_size` |
| `wp_handle_upload_prefilter` | filter | Antes do upload processar | Validar ficheiro | Retorne `$file['error']` para bloquear |
| `upload_mimes` | filter | MIMEs permitidos | Permitir/bloquear tipos | **Cuidado**: permitir SVG sem sanitize é XSS |

## 9. Comentários

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `comment_post` | action | Comentário criado | Notify, sync | 3 args |
| `transition_comment_status` | action | Status mudou (approve/spam) | Trigger pós-moderação | — |
| `preprocess_comment` | filter | Antes de guardar | Modificar/validar | Retorne `WP_Error` para bloquear |
| `pre_comment_approved` | filter | Decisão de aprovação | Auto-approve/moderate | Retorne `0`, `1`, ou `'spam'` |

## 10. Crons e schedules

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `cron_schedules` | filter | Definindo intervalos | Adicionar intervalo custom (ex: 5min) | Combinado com `wp_schedule_event` |
| `{custom_hook}` | action | Cron event personalizado | Job real | Não roda sob tráfego sem `DISABLE_WP_CRON` + cron real do sistema |

```php
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['five_minutes'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => __( 'A cada 5 minutos', 'acme-widgets' ),
    ];
    return $schedules;
} );
```

## 11. Options e meta

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `update_option_{name}` | action | Option atualizada | Reagir a mudança | Não dispara se valor é igual |
| `add_option_{name}` | action | Option criada | Setup pós-criação | — |
| `pre_option_{name}` | filter | Antes de buscar | Override de valor (não-persistido) | Retorne valor ou `false` para deixar buscar |
| `option_{name}` | filter | Valor retornado | Modificar valor lido | Dispara em todo `get_option` — caro se complexo |
| `updated_{object_type}_meta` | action | Meta atualizado | Sync | `object_type` = `post`/`user`/`comment`/`term` |
| `added_{object_type}_meta` | action | Meta criado | — | — |
| `deleted_{object_type}_meta` | action | Meta deletado | Cleanup | — |

## 12. URLs e rewrite

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `init` | action | Registrar rewrite rules custom | Registrar regras + flush no activate | `add_rewrite_rule`, `add_rewrite_tag` |
| `query_vars` | filter | Vars permitidas em URL | Adicionar vars custom | Sem isso, `get_query_var('x')` é vazio |
| `rewrite_rules_array` | filter | Mexer no array final | Reordenar/remover rules | Última opção; geralmente `add_rewrite_rule` basta |
| `permalink_structure_changed` | action | Estrutura mudou | Flush + reindex | Você precisa flush em `register_activation_hook` |

## 13. Block editor (Gutenberg)

| Hook | Tipo | Quando | Use para | Gotcha |
|---|---|---|---|---|
| `init` | action | Registrar block | `register_block_type( __DIR__ . '/blocks/my-block' )` | Use `block.json` |
| `block_categories_all` | filter | Categorias de blocos | Adicionar categoria custom | **WP 5.8+**; antes era `block_categories` (deprecado) |
| `allowed_block_types_all` | filter | Allowlist de blocos | Restringir blocos por contexto | Recebe `$editor_context` |
| `render_block` | filter | Output do bloco | Modificar HTML renderizado | Dispara para **todos** blocos — condicione por `$block['blockName']` |
| `enqueue_block_editor_assets` | action | Editor enqueue | JS/CSS do editor | Só admin editor |

## 14. WooCommerce (se aplicável)

Hooks WC fora do escopo deste catálogo — consulte https://woocommerce.github.io/code-reference/hooks/hooks.html. Padrão de prefixo: `woocommerce_`.

## Padrões de prioridade

| Prioridade | Quando usar |
|---|---|
| `1` | Antes de quase tudo (ex: bloquear request muito cedo) |
| `5` | Antes do default mas não primeiro |
| `10` (default) | Padrão para a maioria das coisas |
| `15-20` | Depois de plugins que usaram default |
| `99` | Quase último (ex: classes no `body_class` finais) |
| `PHP_INT_MAX` | Último a todo custo — raramente justificável |

## Hooks que poucos conhecem mas resolvem problemas

| Problema | Hook |
|---|---|
| Reagir após save + meta + terms persistirem | `wp_after_insert_post` (5.6+) |
| Auth personalizada para REST | `rest_authentication_errors` |
| Bloquear plugin de carregar baseado em condição | `option_active_plugins` (filter) |
| Modificar query principal só no front | `pre_get_posts` com `! is_admin() && $q->is_main_query()` |
| Logar todo plugin enqueue | `script_loader_src`, `style_loader_src` |
| Capturar antes do template 404 | `template_redirect` |
| Adicionar item ao menu admin top-right | `admin_bar_menu` |
| Reagir a tema trocado | `switch_theme` |
| Reagir a WP atualizado | `upgrader_process_complete` |

## 15. Remover hooks de outros (actions/filters)

Handbook: https://developer.wordpress.org/plugins/hooks/advanced-topics/

### Regra de ouro

`remove_action()` / `remove_filter()` só funcionam se **callback e prioridade forem idênticos** ao `add_action()` original. Prioridade errada = remoção silenciosa que não faz nada.

```php
// Plugin alheio:
add_action( 'wp_head', 'thirdparty_print_meta', 5 );

// Remoção — prioridade 5, não 10.
remove_action( 'wp_head', 'thirdparty_print_meta', 5 );
```

### Timing: remover **depois** de registar

O `add_action` do outro plugin tem de já ter corrido. Regra prática por origem:

| Callback registado em | Remova em |
|---|---|
| Ficheiro principal de outro plugin (topo) | `plugins_loaded` (prioridade alta) ou `init` |
| `functions.php` do tema | `after_setup_theme` ou `init` |
| Dentro de `init` | `init` com prioridade maior (ex: 20) |
| Dentro do próprio hook alvo | `add_action( 'alvo', 'remover', prioridade_menor )` |

### Callback que é método de objeto

O caso que mais falha. `[ $obj, 'method' ]` só é removível com **a mesma instância**:

```php
// Registado assim por outro plugin:
add_action( 'init', [ $this, 'setup' ] ); // $this = instância interna, inacessível

// Funciona se houver singleton:
remove_action( 'init', [ ThirdParty\Plugin::instance(), 'setup' ] );

// Método estático — sempre removível:
remove_action( 'init', [ 'ThirdParty\Plugin', 'setup' ] );
remove_action( 'init', 'ThirdParty\Plugin::setup' ); // forma string também serve
```

Closures anónimas registadas por outros são **irremovíveis** na prática. Por isso: no seu plugin, **não registe closures em hooks públicos** que terceiros possam querer remover — use métodos nomeados (ver `references/standards.md`).

Último recurso para closures alheias: percorrer `$GLOBALS['wp_filter']['hook']->callbacks` e comparar por reflexão. Frágil — evite.

### Ferramentas de inspeção

| Função | Devolve |
|---|---|
| `has_action( $hook, $callback )` | `false` ou a prioridade registada — use para descobrir a prioridade antes de remover |
| `has_filter( $hook, $callback )` | Idem |
| `current_action()` / `current_filter()` | Nome do hook a correr — um callback partilhado por vários hooks decide por aqui |
| `doing_action()` / `doing_filter()` | Se um hook específico está a correr agora |
| `did_action( $hook )` | Quantas vezes já correu — guard de idempotência |
| `remove_all_actions()` / `remove_all_filters()` | Limpa tudo do hook. Brutal; só com prioridade específica e razão forte |

```php
// Guard de idempotência: correr só na primeira vez.
add_action( 'save_post', function ( $post_id ) {
    if ( did_action( 'acme_sync_done' ) ) {
        return;
    }
    acme_sync( $post_id );
    do_action( 'acme_sync_done' );
} );
```

### Debug: o hook `all`

Dispara em **todos** os hooks. Só em dev — custa caro.

```php
if ( 'production' !== wp_get_environment_type() ) {
    add_action( 'all', function () {
        error_log( current_filter() );
    } );
}
```

Alternativa sem código: painel "Hooks & Actions" do Query Monitor (ver `references/developer-tools.md`).

## 16. Criar hooks próprios (extensibilidade)

Se o plugin quer ser extensível, exponha hooks — é o que distingue plugin de caixa fechada.

```php
/**
 * Filtra os dados antes de gravar a submissão.
 *
 * @since 1.4.0
 *
 * @param array  $data    Dados sanitizados.
 * @param int    $form_id ID do formulário.
 * @param string $context Contexto ('frontend'|'admin'|'rest').
 */
$data = apply_filters( 'acme_submission_data', $data, $form_id, $context );

/**
 * Dispara após gravar a submissão.
 *
 * @since 1.4.0
 *
 * @param int   $submission_id ID criado.
 * @param array $data          Dados gravados.
 */
do_action( 'acme_submission_saved', $submission_id, $data );
```

Regras:

| Regra | Porquê |
|---|---|
| Prefixe sempre (`acme_`) | Colisão com core/outros plugins |
| Nome estável: `{prefixo}_{objeto}_{evento}` | Previsível para quem integra |
| Documente com PHPDoc + `@since` | Vira contrato público; sem isto ninguém usa |
| Passe contexto suficiente (IDs, args) | Filtro sem contexto é inútil |
| **Nunca** confie no retorno de um filtro | Outro plugin pode devolver lixo — valide/sanitize depois |
| Não mude nem remova hook em minor release | Quebra integrações silenciosamente; deprecie primeiro |
| `do_action` para eventos, `apply_filters` para valores | Filter que não devolve valor parte tudo |

Validação após filtro (ponto de segurança esquecido):

```php
$data = apply_filters( 'acme_submission_data', $data, $form_id, $context );

if ( ! is_array( $data ) ) {
    return new WP_Error( 'acme_bad_filter', __( 'Filtro devolveu valor inválido.', 'acme-widgets' ) );
}
$data['email'] = sanitize_email( $data['email'] ?? '' ); // re-sanitize após terceiros mexerem
```

Deprecar um hook:

```php
$value = apply_filters_deprecated( 'acme_old_hook', [ $value ], '2.0.0', 'acme_new_hook' );
do_action_deprecated( 'acme_old_event', [ $id ], '2.0.0', 'acme_new_event' );
```

## 17. Heartbeat API — polling do servidor

https://developer.wordpress.org/plugins/javascript/heartbeat-api/

Polling já incluído no core: o cliente dispara um "tick" a cada **15 a 120 segundos**, envia dados por `admin-ajax.php` e recebe resposta em JSON. Evita construir polling próprio.

Três passos (pode usar só os que precisa):

```js
// 1. Enviar dados junto ao tick
jQuery( document ).on( 'heartbeat-send', function ( event, data ) {
    data.acme_watch_order = 123;
} );

// 3. Reagir à resposta
jQuery( document ).on( 'heartbeat-tick', function ( event, data ) {
    if ( ! data.acme_order_status ) {
        return;
    }
    document.querySelector( '#acme-status' ).textContent = data.acme_order_status;
} );
```

```php
// 2. Responder no servidor
add_filter( 'heartbeat_received', function ( array $response, array $data ): array {
    if ( empty( $data['acme_watch_order'] ) ) {
        return $response; // não é para nós — devolver intacto
    }

    $order_id = absint( $data['acme_watch_order'] );

    if ( ! current_user_can( 'edit_post', $order_id ) ) {
        return $response; // heartbeat NÃO dispensa capability check
    }

    $response['acme_order_status'] = get_post_meta( $order_id, '_acme_status', true );

    return $response;
}, 10, 2 );
```

| Hook | Lado | Nota |
|---|---|---|
| `heartbeat-send` (jQuery) | JS | Acrescentar campos ao payload |
| `heartbeat_received` | PHP | Filtro; `$data` chega **unslashed**. Devolva `$response` sempre |
| `heartbeat-tick` (jQuery) | JS | Dados de volta |
| `heartbeat_settings` | PHP | Ajustar o intervalo |
| `heartbeat_nopriv_received` | PHP | Equivalente para visitantes sem sessão |

Cuidados:

- Cada tick é um request a `admin-ajax.php`. Em admin com muitos separadores abertos, o custo soma — não meta queries pesadas no `heartbeat_received`; cacheie.
- Verifique sempre capability: o heartbeat corre autenticado, mas não valida permissão sobre o objeto que você devolve.
- Se o campo do seu plugin não veio no `$data`, devolva `$response` sem tocar — outros plugins partilham o mesmo canal.
- Para trabalho pesado ou periódico do lado do servidor, heartbeat é a ferramenta errada: use cron ou Action Scheduler (ver `references/performance.md`).
