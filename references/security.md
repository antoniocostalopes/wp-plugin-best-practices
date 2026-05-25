# Segurança em Plugins WordPress

Referência detalhada de segurança. Use ao auditar código ou implementar features que tocam input do usuário, saída em HTML, queries SQL, ou ações que modificam estado.

## Modelo mental

Toda vulnerabilidade em WP cai em uma destas categorias:

1. **XSS** — output sem escaping
2. **CSRF** — ação sem nonce
3. **Privilege escalation** — falta de `current_user_can()`
4. **SQL injection** — query sem `$wpdb->prepare()`
5. **Path traversal / LFI** — paths sem validação
6. **SSRF** — `wp_remote_get` para URL controlada pelo usuário
7. **Object injection** — `unserialize()` em input

Sempre se pergunte para cada bloco de código: **de onde veio esse dado? para onde vai?**

## 1. Nonces (CSRF protection)

Toda ação que **muda estado** (POST, AJAX que grava, GET com side effect) precisa de nonce.

### Em formulários

```php
// No HTML do formulário
wp_nonce_field( 'acme_save_settings', 'acme_settings_nonce' );

// No handler
if ( ! isset( $_POST['acme_settings_nonce'] )
    || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['acme_settings_nonce'] ) ), 'acme_save_settings' )
) {
    wp_die( esc_html__( 'Verificação de segurança falhou.', 'acme' ) );
}
```

Atalho para admin: `check_admin_referer( 'acme_save_settings', 'acme_settings_nonce' );`

### Em links/URLs

```php
$url = wp_nonce_url(
    admin_url( 'admin.php?page=acme&action=delete&id=' . $id ),
    'acme_delete_' . $id
);

// No handler
check_admin_referer( 'acme_delete_' . absint( $_GET['id'] ) );
```

### Em AJAX

```php
// JS
wp.ajax.post( 'acme_action', { _ajax_nonce: acmeData.nonce, ... } );

// PHP — enfileirar
wp_localize_script( 'acme-admin', 'acmeData', [
    'nonce' => wp_create_nonce( 'acme_ajax' ),
] );

// PHP — handler
add_action( 'wp_ajax_acme_action', function() {
    check_ajax_referer( 'acme_ajax' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
    }
    // ...
} );
```

### Em REST API

A REST API usa nonce automaticamente quando autenticada via cookie. O nonce vem em `X-WP-Nonce` header. Mas **sempre** declare `permission_callback`:

```php
register_rest_route( 'acme/v1', '/items', [
    'methods'             => WP_REST_Server::EDITABLE,
    'callback'            => 'acme_create_item',
    'permission_callback' => function() {
        return current_user_can( 'edit_posts' );
    },
] );
```

**Nunca** use `'permission_callback' => '__return_true'` em endpoints que mudam estado. Endpoint público de leitura pode usar, mas documente o porquê.

## 2. Capability checks

Nonce ≠ autorização. Nonce só confirma que o request veio de uma página sua. Sempre combine com `current_user_can()`.

```php
// Errado — só nonce
check_admin_referer( 'acme_action' );
delete_post( $id );

// Certo — nonce + capability
check_admin_referer( 'acme_action' );
if ( ! current_user_can( 'delete_post', $id ) ) {
    wp_die( esc_html__( 'Sem permissão.', 'acme' ), 403 );
}
delete_post( $id );
```

Capabilities comuns:
- `manage_options` — admin geral (cuidado, é alta)
- `edit_posts` / `edit_post` / `edit_others_posts`
- `publish_posts`
- `delete_post`
- `upload_files`
- `read` — qualquer usuário logado

Prefira a forma com objeto (`edit_post`, `$id`) quando aplicável — verifica ownership.

## 3. Sanitização de input

**Toda** variável que vem de `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, ou qualquer fonte externa, sanitize **antes** de usar.

```php
// Padrão: unslash + sanitize
$name  = isset( $_POST['name'] )  ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
$url   = isset( $_POST['url'] )   ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
$id    = isset( $_POST['id'] )    ? absint( $_POST['id'] ) : 0;
$key   = isset( $_POST['key'] )   ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
$slug  = isset( $_POST['slug'] )  ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
$html  = isset( $_POST['bio'] )   ? wp_kses_post( wp_unslash( $_POST['bio'] ) ) : '';
```

**Sempre** use `wp_unslash()` antes da sanitização — WP adiciona slashes automaticamente em superglobals (legado do magic_quotes).

### Tabela de sanitizadores

| Tipo de dado | Função |
|---|---|
| Texto simples (1 linha) | `sanitize_text_field()` |
| Texto multilinha | `sanitize_textarea_field()` |
| Email | `sanitize_email()` |
| URL para storage | `esc_url_raw()` |
| Chave/identificador | `sanitize_key()` |
| Slug | `sanitize_title()` |
| Nome de arquivo | `sanitize_file_name()` |
| HTML rico (post content) | `wp_kses_post()` |
| HTML com tags específicas | `wp_kses( $input, [ 'a' => [ 'href' => [] ] ] )` |
| Inteiro positivo | `absint()` |
| Inteiro qualquer | `(int) $value` ou `intval()` |
| Float | `floatval()` |
| Boolean | `(bool) $value` ou `rest_sanitize_boolean()` |
| Array de inteiros | `array_map( 'absint', (array) $value )` |
| Hex color | `sanitize_hex_color()` |
| Username | `sanitize_user()` |
| MIME type | `sanitize_mime_type()` |

### Validação vs sanitização

Sanitização **transforma** dados para forma segura. Validação **rejeita** dados inválidos. Use ambas:

```php
$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
if ( ! is_email( $email ) ) {
    wp_die( esc_html__( 'Email inválido.', 'acme' ) );
}
```

## 4. Escaping de output

**Onde** o dado vai determina **qual função** usar. Sempre escape o mais tarde possível (no momento do output).

```php
// HTML body
echo '<h1>' . esc_html( $title ) . '</h1>';

// Atributo HTML
echo '<div class="' . esc_attr( $class ) . '">';
echo '<input type="text" value="' . esc_attr( $value ) . '">';

// href / src
echo '<a href="' . esc_url( $link ) . '">';
echo '<img src="' . esc_url( $img ) . '">';

// JS inline (preferir wp_json_encode)
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// textarea
echo '<textarea>' . esc_textarea( $value ) . '</textarea>';

// HTML rico permitido
echo wp_kses_post( $bio );

// Translation com placeholders
printf(
    /* translators: %s: nome do usuário */
    esc_html__( 'Olá, %s!', 'acme' ),
    esc_html( $user_name )
);
```

### Funções `_e()` vs `esc_html_e()`

`_e()` e `__()` **não** escapam. Sempre use a variante escapada quando faz output direto:

- `esc_html_e( 'text', 'domain' )` — escapa + ecoa
- `esc_attr_e( 'text', 'domain' )` — para atributos
- `esc_html__( 'text', 'domain' )` — escapa + retorna

## 5. SQL — prepared statements

**Nunca** concatene variáveis em SQL. Use `$wpdb->prepare()`:

```php
global $wpdb;

// Errado
$results = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_author = $user_id" );

// Certo
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_author = %d",
        $user_id
    )
);
```

Placeholders: `%d` (int), `%f` (float), `%s` (string), `%i` (identifier — WP 6.2+, para nomes de tabela/coluna).

### IN clauses

```php
$ids = array_map( 'absint', $ids );
$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID IN ($placeholders)",
    ...$ids
);
```

### LIKE com `%`

```php
$like = '%' . $wpdb->esc_like( $search ) . '%';
$wpdb->prepare( "... WHERE title LIKE %s", $like );
```

### Quando usar WP_Query ao invés

Prefira `WP_Query` / `get_posts()` / `get_users()` / `get_terms()` — eles fazem prepare + cache + filtros automaticamente. SQL direto só quando WP_Query não dá conta (joins complexos, agregações).

## 6. Uploads e arquivos

```php
// Upload de mídia — sempre use as funções core
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$attachment_id = media_handle_upload( 'file_input_name', $post_id );
if ( is_wp_error( $attachment_id ) ) {
    // tratar erro
}
```

Para arquivos não-mídia:

- **Nunca** confie em `$_FILES['file']['name']` — use `sanitize_file_name()` e `wp_check_filetype()`
- **Sempre** use `wp_upload_dir()` para destino — nunca paths absolutos hardcoded
- **Nunca** permita upload de `.php`, `.phtml`, `.htaccess`, etc. — use `wp_check_filetype_and_ext()` com allowlist

```php
$allowed = [ 'jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf' ];
$check   = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
if ( ! $check['type'] ) {
    wp_die( esc_html__( 'Tipo de arquivo não permitido.', 'acme' ) );
}
```

## 7. HTTP requests externos

Use a HTTP API, nunca `file_get_contents()`, `curl_exec()`, etc:

```php
$response = wp_remote_get( $url, [
    'timeout'     => 10,
    'redirection' => 3,
    'sslverify'   => true,  // nunca false em produção
] );

if ( is_wp_error( $response ) ) {
    return $response;
}

$code = wp_remote_retrieve_response_code( $response );
$body = wp_remote_retrieve_body( $response );
```

**SSRF**: se a URL vem do usuário, valide o host contra allowlist. Bloqueie `127.0.0.1`, `localhost`, `169.254.*` (metadata cloud), ranges privados.

## 8. Padrões perigosos a evitar

| Função | Por quê | Use em vez |
|---|---|---|
| `eval()` | Code injection | Refatorar — nunca há razão legítima |
| `extract( $_POST )` | Variable injection | Atribuir explicitamente |
| `unserialize( $input )` | Object injection | `json_decode()` ou `maybe_unserialize()` em dado interno apenas |
| `assert( $code )` | Eval disfarçado | Refatorar |
| `create_function()` | Eval disfarçado, removido PHP 8 | Closures |
| `mt_rand()` para tokens | Não criptograficamente seguro | `wp_generate_password()` ou `random_bytes()` |
| `md5()` / `sha1()` para senhas | Quebráveis | `wp_hash_password()` / `password_hash()` |
| `$_SERVER['HTTP_HOST']` confiável | Spoofável | Use `home_url()` / `site_url()` |
| `$_SERVER['REMOTE_ADDR']` atrás de proxy | Pode ser spoofado | Validar contra proxy conhecido |
| Inclusão de arquivos por path do usuário | LFI/RFI | Allowlist de paths permitidos |

## 9. Direct file access

Cada arquivo PHP do plugin deve começar com:

```php
<?php
defined( 'ABSPATH' ) || exit;
```

Isso previne execução direta caso o arquivo seja acessado via URL. Em pastas que não devem listar (uploads, includes), adicione `index.php` silencioso:

```php
<?php
// Silence is golden.
```

## 10. Logs e exposição de informação

- **Nunca** logue dados sensíveis (senhas, tokens, chaves API) — mesmo em `error_log()`
- **Nunca** exponha `display_errors = On` em produção
- **Nunca** retorne stack traces em respostas — use `WP_Error` ou `wp_die()` com mensagem genérica
- **Nunca** versione `.env`, `wp-config.php`, chaves privadas

## Checklist de auditoria de segurança

Ao auditar, percorra:

- [ ] Todo arquivo PHP tem `defined( 'ABSPATH' ) || exit;`
- [ ] Todo formulário/AJAX/REST tem nonce verificado
- [ ] Toda ação tem `current_user_can()` apropriado
- [ ] Todo input de superglobal é sanitizado com função certa pro tipo
- [ ] Todo output é escapado conforme o contexto (html/attr/url/js)
- [ ] Toda query SQL custom usa `$wpdb->prepare()`
- [ ] Sem `eval`, `extract`, `unserialize` em input externo
- [ ] Uploads validam MIME + extensão por allowlist
- [ ] HTTP externo usa `wp_remote_*` com timeout e sslverify
- [ ] Sem credenciais hardcoded
- [ ] `permission_callback` declarado em todo endpoint REST
- [ ] Erros não vazam paths/SQL/stack traces
