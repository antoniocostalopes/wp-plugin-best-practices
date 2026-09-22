# Segurança em Plugins WordPress

Referência detalhada de segurança. Use ao auditar código ou implementar features que tocam input do utilizador, saída em HTML, queries SQL, ou ações que modificam estado.

Handbook: https://developer.wordpress.org/apis/security/

## Conteúdo

- [Modelo mental](#modelo-mental)
- [Princípios orientadores (WordPress Security API)](#princípios-orientadores-wordpress-security-api)
- [1. Nonces (CSRF protection)](#1-nonces-csrf-protection)
- [2. Capability checks](#2-capability-checks)
- [3. Sanitização de input](#3-sanitização-de-input)
- [4. Escaping de output](#4-escaping-de-output)
- [5. SQL — prepared statements](#5-sql--prepared-statements)
- [6. Uploads e ficheiros](#6-uploads-e-ficheiros)
- [7. HTTP requests externos](#7-http-requests-externos)
- [8. Padrões perigosos a evitar](#8-padrões-perigosos-a-evitar)
- [9. Direct file access](#9-direct-file-access)
- [10. Logs e exposição de informação](#10-logs-e-exposição-de-informação)
- [11. Metadata registada e exposição REST](#11-metadata-registada-e-exposição-rest)
- [12. Exemplo completo — as cinco camadas](#12-exemplo-completo--as-cinco-camadas)
- [13. Manter-se atual](#13-manter-se-atual)
- [Checklist](#checklist)

## Modelo mental

Toda vulnerabilidade em WP cai em uma destas categorias:

1. **XSS** — output sem escaping
2. **CSRF** — ação sem nonce
3. **Privilege escalation** — falta de `current_user_can()`
4. **SQL injection** — query sem `$wpdb->prepare()`
5. **Path traversal / LFI** — paths sem validação
6. **SSRF** — `wp_remote_get` para URL controlada pelo utilizador
7. **Object injection** — `unserialize` em input
8. **Open redirect** — `wp_redirect()` com destino vindo de input (use `wp_safe_redirect()`)
9. **IDOR** — capability genérica em vez de meta cap com o ID do objeto

Sempre se pergunte para cada bloco de código: **de onde veio esse dado? para onde vai?**

## Princípios orientadores (WordPress Security API)

Fonte: https://developer.wordpress.org/apis/security/

1. **Nunca confie em input do utilizador.** Nem de admin, nem do próprio. Input é tudo o que não foi escrito à mão no ficheiro.
2. **Escape o mais tarde possível** — no ponto de output, não ao gravar nem ao ler.
3. **Escape tudo o que vem de fonte não confiável** — isso inclui a **própria base de dados**. Dados gravados por uma versão antiga do plugin, ou por outro plugin, não são confiáveis.
4. **Não assuma nada.** `maxlength`, `<select>`, `type="number"` e validação em JS são sugestões ao browser — um POST direto ignora tudo.
5. **Prefira validar (rejeitar) a sanitizar (transformar).** Sanitizar um valor inválido transforma-o num valor errado mas aceite; validar rejeita-o.

O princípio 3 é o mais esquecido: `echo get_option( 'acme_title' )` é XSS se alguma versão do plugin gravou a option sem sanitizar.

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
    wp_die( esc_html__( 'Verificação de segurança falhou.', 'acme-widgets' ) );
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

### O que um nonce é (e não é)

| Afirmação | Realidade |
|---|---|
| "Nonce = usado uma vez" | Falso. É válido durante **12 a 24 horas** (duas "ticks" de meia-vida; `nonce_life` altera, default 86400) |
| "Nonce autentica o utilizador" | Falso. Nonce prova **intenção**, não identidade nem permissão. `current_user_can()` é sempre obrigatório **além** do nonce |
| "Nonce é único por pessoa" | Só para quem tem sessão. Visitantes anónimos são todos o utilizador `0` e partilham o mesmo nonce — use o filtro `nonce_user_logged_out` para ligar o nonce a outro identificador |
| "`wp_verify_nonce` devolve bool" | Devolve `1`, `2` ou `false`. Compare com `! wp_verify_nonce(...)`, nunca `=== true` |

### Nonce + cache de página

Página em cache serve o nonce gerado quando o HTML foi guardado. Ao fim de 24h **todos** os visitantes recebem um nonce expirado e o formulário rebenta.

Em páginas cacheáveis do front-end:

- injete o nonce por AJAX/REST depois do load, ou
- exclua a página do cache, ou
- use `wp_create_nonce` num endpoint REST chamado no momento da submissão.

Nunca imprima um nonce em HTML cacheado e espere que funcione.

### Filtros do sistema de nonces

| Objetivo | Hook |
|---|---|
| Mudar a validade (default 1 dia) | `nonce_life` — `add_filter( 'nonce_life', fn() => 4 * HOUR_IN_SECONDS )` |
| Dar nonce único a visitantes anónimos | `nonce_user_logged_out` — devolva um id de sessão próprio em vez de `0` |
| Verificação extra depois do nonce passar | ação `check_admin_referer` (e `check_ajax_referer`), args `( $action, $result )` |
| Mudar a mensagem "Are you sure you want to do this?" | filtro `gettext` a intercetar a string (a mensagem é impressa por `wp_nonce_ays()`) |

```php
// Nonce por sessão para quem não tem login — sem isto, todos os visitantes partilham o mesmo nonce.
add_filter( 'nonce_user_logged_out', function ( $uid ) {
    return $uid ?: acme_get_guest_session_id(); // identificador próprio, estável por visitante
} );
```

As funções `wp_create_nonce()`, `wp_verify_nonce()`, `wp_nonce_tick()`, `check_admin_referer()` e `check_ajax_referer()` são **pluggable** — outro plugin pode tê-las substituído. Não assuma a implementação do core ao depurar.

### `check_ajax_referer` mata o request por omissão

```php
check_ajax_referer( 'acme_action', 'nonce' );          // falha => wp_die() imediato
check_ajax_referer( 'acme_action', 'nonce', false );   // falha => devolve false; trate o erro
```

`check_admin_referer()` verifica nonce **e** referrer e mostra o ecrã `wp_nonce_ays()`. `wp_verify_nonce()` não mata nada — a decisão é sua.

## 2. Capability checks

Nonce ≠ autorização. Nonce só confirma que o request veio de uma página sua. Sempre combine com `current_user_can()`.

```php
// Errado — só nonce
check_admin_referer( 'acme_action' );
delete_post( $id );

// Certo — nonce + capability
check_admin_referer( 'acme_action' );
if ( ! current_user_can( 'delete_post', $id ) ) {
    wp_die( esc_html__( 'Sem permissão.', 'acme-widgets' ), 403 );
}
delete_post( $id );
```

Capabilities comuns:
- `manage_options` — admin geral (cuidado, é alta)
- `edit_posts` / `edit_post` / `edit_others_posts`
- `publish_posts`
- `delete_post`
- `upload_files`
- `read` — qualquer utilizador logado

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
| Nome de ficheiro | `sanitize_file_name()` |
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

### Sanitizar e escapar não são intermutáveis

Regra da Plugin Review Team: **função de escaping não sanitiza; função de sanitização não escapa.** Não é purismo — quase todas são *filtráveis* ou *pluggable*, e outro plugin pode alterar o que fazem. Depender de um efeito lateral torna o seu código explorável por terceiros.

```php
// ERRADO — esc_html não sanitiza input
update_option( 'acme_name', esc_html( $_POST['name'] ) );

// ERRADO — sanitize_text_field não é escaping de output
echo sanitize_text_field( $value );

// CERTO — sanitize ao entrar, escape ao sair
$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
update_option( 'acme_name', $name );
// ...
echo esc_html( get_option( 'acme_name' ) );
```

Caso especial já referido: `esc_url_raw()` tem nome de escaping mas **é sanitização** (para BD e redirects). O escaping de URL é `esc_url()`.

### `filter_var` sem filtro não filtra nada

```php
// ERRADO — sem o 3.º argumento, PHP aplica FILTER_DEFAULT, que não sanitiza
$id = filter_input( INPUT_GET, 'post_id' );

// CERTO
$id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );
```

Vale para `filter_var`, `filter_var_array`, `filter_input`, `filter_input_array`. Em WordPress prefira na mesma as funções `sanitize_*` do core.

### Sanitize o próprio nonce

`wp_verify_nonce()` é **pluggable** — outro plugin pode tê-la substituído, por isso não se confia no tratamento interno do input:

```php
if ( ! isset( $_POST['acme_nonce'] )
    || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acme_nonce'] ) ), 'acme_action' ) ) {
    return;
}
```

### Não processe a superglobal inteira

```php
// ERRADO — percorre dados que não são seus, desperdiça ciclos e abre superfície
foreach ( $_POST as $key => $value ) {
    update_option( $key, sanitize_text_field( $value ) );
}

// CERTO — lista explícita dos campos que o plugin conhece
$fields = [ 'acme_title' => 'sanitize_text_field', 'acme_email' => 'sanitize_email' ];
foreach ( $fields as $field => $sanitizer ) {
    if ( isset( $_POST[ $field ] ) ) {
        update_option( $field, call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) );
    }
}
```

### Sanitizadores menos conhecidos (mas exigidos pelo WPCS)

| Tipo de dado | Função |
|---|---|
| Classe CSS | `sanitize_html_class()` |
| Nome de tag HTML | `tag_escape()` |
| Cláusula `ORDER BY` | `sanitize_sql_orderby()` (+ allowlist de colunas) |
| Valor de option do core | `sanitize_option( $option, $value )` |
| Slug para usar em query | `sanitize_title_for_query()` |
| Hex sem `#` | `sanitize_hex_color_no_hash()` |
| URL (alias de `esc_url_raw`, WP 5.9+) | `sanitize_url()` |
| HTML de comentário (menos permissivo que `_post`) | `wp_kses_data()` |
| Meta registada | `sanitize_meta( $key, $value, 'post' )` — aplica o `sanitize_callback` do `register_post_meta` |

### Validação primeiro, sanitização depois

Sanitização **transforma**; validação **rejeita**. Sanitizar sozinho aceita lixo convertido: `absint( 'delete' )` dá `0`, e `0` pode ser um ID válido noutro sítio.

```php
$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
if ( ! is_email( $email ) ) {
    wp_die( esc_html__( 'Email inválido.', 'acme-widgets' ) );
}
```

#### Validadores do WordPress

| Verificar | Função |
|---|---|
| Email real | `is_email()` |
| Utilizador existe | `username_exists()` / `email_exists()` / `get_user_by()` |
| Termo existe na taxonomia | `term_exists( $term, $taxonomy )` |
| Post existe e é do tipo certo | `get_post_type( $id ) === 'acme_order'` |
| **Caminho de ficheiro seguro** | `validate_file( $file, $allowed_files )` — devolve `0` se seguro, não-zero se há `../`, `:` ou path absoluto |
| Ficheiro é mesmo do tipo declarado | `wp_check_filetype_and_ext( $tmp, $name )` |
| URL bem formada | `wp_http_validate_url()` (também rejeita rede interna) |
| Valor contra esquema REST | `rest_validate_value_from_schema( $value, $schema, $param )` |

#### Validadores PHP

`ctype_alnum()`, `ctype_digit()`, `preg_match()`, `mb_strlen()`, `in_array( $v, $allowed, true )`, `filter_var( $v, FILTER_VALIDATE_INT )`.

**O terceiro argumento de `in_array` é obrigatório.** Sem `true`, `in_array( 0, [ 'admin', 'editor' ] )` é `true` em PHP 7 — comparação frouxa transforma allowlist em passe-vai-tudo. O mesmo para `===` em vez de `==`.

#### As quatro filosofias de validação

| Filosofia | O que faz | Veredito do handbook |
|---|---|---|
| **Safelist** (allowlist) | Aceita só valores de uma lista finita e conhecida | ✅ Preferida. Exige comparação estrita |
| **Blocklist** (denylist) | Rejeita valores de uma lista de maus conhecidos | ❌ "raramente boa ideia" — impossível antecipar tudo |
| **Format detection** | Testa se o dado cumpre o formato esperado e rejeita se não | ✅ Boa quando o conjunto de valores é infinito (email, data, IBAN) |
| **Format correction** | Aceita quase tudo e remove/altera as partes perigosas | ⚠️ É sanitização. Último recurso — altera silenciosamente o que o utilizador escreveu |

Sanitizar é format correction. Por isso o princípio 5: quando o conjunto de valores válidos é conhecido, **rejeite**; só sanitize quando não há como enumerar.

#### `switch` compara de forma frouxa

`switch` usa `==` internamente. Com input não confiável, inverta:

```php
// ERRADO — switch frouxo: 'admin' pode igualar 0
switch ( $untrusted ) {
    case 1:
        // ...
}

// CERTO — switch ( true ) com comparação estrita em cada case
switch ( true ) {
    case 1 === $untrusted:
        // ...
        break;
    default:
        wp_die( esc_html__( 'Dados inválidos.', 'acme-widgets' ) );
}
```

Mesma lógica de `in_array( ..., true )` e `===`.

#### Allowlist, nunca denylist

```php
// ERRADO — tentar adivinhar tudo o que é mau
$orderby = str_replace( [ ';', '--' ], '', $_GET['orderby'] );

// CERTO — só o que é explicitamente permitido existe
$allowed = [ 'date', 'title', 'menu_order' ];
$orderby = in_array( $_GET['orderby'] ?? '', $allowed, true ) ? $_GET['orderby'] : 'date';
```

#### Validação do browser não conta

`maxlength`, `required`, `type="email"`, `<select>` com 3 opções e validação em JS são **sugestões ao browser**. Um `curl -d` ignora todas. Toda a regra de negócio tem de ser revalidada em PHP.

#### Falhar ruidosamente

```php
if ( ! $valid ) {
    if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
        return new WP_Error( 'acme_invalid', __( 'Dados inválidos.', 'acme-widgets' ), [ 'status' => 400 ] );
    }
    wp_die(
        esc_html__( 'Dados inválidos.', 'acme-widgets' ),
        esc_html__( 'Erro', 'acme-widgets' ),
        [ 'response' => 400, 'back_link' => true ]
    );
}
```

Mensagem de erro **não** deve conter o valor recebido sem escapar — é XSS refletido.

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
    /* translators: %s: nome do utilizador */
    esc_html__( 'Olá, %s!', 'acme-widgets' ),
    esc_html( $user_name )
);
```

### Tabela completa de escaping por contexto

| Contexto de output | Função |
|---|---|
| Texto dentro de elemento HTML | `esc_html()` |
| Valor de atributo | `esc_attr()` |
| `href` / `src` | `esc_url()` |
| Conteúdo de `<textarea>` | `esc_textarea()` |
| Dentro de `<script>` | `wp_json_encode()` |
| String JS dentro de atributo (`onclick`) | `esc_js()` — legado, evite atributos de evento |
| Documento XML / feed | `esc_xml()` (WP 5.5+) |
| HTML rico de post | `wp_kses_post()` |
| HTML de comentário (mais restrito) | `wp_kses_data()` |
| HTML com allowlist própria | `wp_kses( $html, $allowed )` |
| Ver o que `wp_kses_post` permite | `wp_kses_allowed_html( 'post' )` |
| Número (inteiro) | `absint()` ou `(int)` — o cast **é** o escaping |
| Número (float) | `(float)` ou `number_format()` |
| XML quando `esc_xml` não serve | `ent2ncr()` |

Variável numérica não precisa de `esc_html()`: `(int)`/`absint()`/`(float)` já garantem que o output não contém nada além de dígitos. Usar `esc_attr( $id )` num ID inteiro é ruído; `absint( $id )` é a forma correta.

### Escape a string inteira, não pedaços

```php
// ERRADO — um `"` no fim de $prefix escapa a montagem, não o resultado final
echo '<div id="' . esc_attr( $prefix ) . '-box-' . esc_attr( $id ) . '">';

// CERTO — monta primeiro, escapa o resultado
$dom_id = sprintf( '%s-box-%s', $prefix, $id );
echo '<div id="' . esc_attr( $dom_id ) . '">';
```

Regra igual para URLs: `esc_url()` na URL completa, nunca em segmentos concatenados depois.

### `esc_url()` vs `esc_url_raw()` / `sanitize_url()`

| Destino | Função |
|---|---|
| Output em HTML (`href`, `src`) | `esc_url()` — codifica entidades para HTML |
| Gravar na BD, `wp_redirect()`, chamada HTTP | `esc_url_raw()` ou `sanitize_url()` (WP 5.9+) |

`esc_url()` num valor que vai ser gravado guarda `&amp;` na base de dados — a URL deixa de funcionar quando reutilizada fora de HTML.

### Quando não dá para escapar no output

Ao montar HTML complexo que não pode passar por `wp_kses()`, escape ao construir e marque a variável no nome (convenção do handbook):

```php
$row_escaped = '<tr><td>' . esc_html( $name ) . '</td><td>' . esc_url( $link ) . '</td></tr>';
// ...
echo $row_escaped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado com escape acima
```

Sufixos aceites: `_escaped`, `_safe`, `_clean`. O `phpcs:ignore` tem de dizer **porquê** — sem justificação é bandeira vermelha em revisão.

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

### Identificadores: tabela, coluna, ORDER BY

`prepare()` só trata **valores**. Nome de tabela, de coluna e direção de ordenação não são valores.

```php
// WP 6.2+ — %i trata identificadores
$wpdb->prepare( "SELECT * FROM %i WHERE post_author = %d", $wpdb->posts, $user_id );

// ORDER BY: prepare() não ajuda. Allowlist, sempre.
$columns   = [ 'created_at', 'total', 'status' ];
$orderby   = in_array( $_GET['orderby'] ?? '', $columns, true ) ? $_GET['orderby'] : 'created_at';
$order     = 'DESC' === strtoupper( $_GET['order'] ?? '' ) ? 'DESC' : 'ASC';

$sql = $wpdb->prepare(
    "SELECT * FROM %i ORDER BY {$orderby} {$order} LIMIT %d",
    $wpdb->prefix . 'acme_orders',
    $limit
);
```

`sanitize_sql_orderby()` valida a **sintaxe** de uma cláusula (`'title ASC, date DESC'`) mas não verifica se as colunas existem nem se o utilizador as pode ver. Allowlist continua obrigatória.

### `esc_sql()` não substitui `prepare()`

`esc_sql()` só escapa aspas. Num contexto numérico sem aspas (`WHERE id = $val`) não protege nada. Use `prepare()`; `esc_sql()` só para casos de escape em massa (`esc_sql( array )`) que depois entram em placeholders.

### Quando usar WP_Query ao invés

Prefira `WP_Query` / `get_posts()` / `get_users()` / `get_terms()` — eles fazem prepare + cache + filtros automaticamente. SQL direto só quando WP_Query não dá conta (joins complexos, agregações).

## 6. Uploads e ficheiros

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

Para ficheiros fora da biblioteca de média, use `wp_handle_upload()` — **nunca** `move_uploaded_file()`, que salta todas as verificações do WordPress:

```php
require_once ABSPATH . 'wp-admin/includes/file.php';

$result = wp_handle_upload( $_FILES['acme_file'], [ 'test_form' => false ] );

if ( isset( $result['error'] ) ) {
    return new WP_Error( 'acme_upload', $result['error'] );
}
```

⚠️ **`ALLOW_UNFILTERED_UPLOADS` é proibido** no diretório — mesmo dentro de condicional. Permite upload de `.php` e outros executáveis. Para acrescentar um tipo específico e seguro, use o filtro `upload_mimes` (a lista base está em `wp_get_mime_types()`).

Para ficheiros não-média:

- **Nunca** confie em `$_FILES['file']['name']` — use `sanitize_file_name()` e `wp_check_filetype()`
- **Sempre** use `wp_upload_dir()` para destino — nunca paths absolutos hardcoded
- **Nunca** permita upload de `.php`, `.phtml`, `.htaccess`, etc. — use `wp_check_filetype_and_ext()` com allowlist

```php
$allowed = [ 'jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf' ];
$check   = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
if ( ! $check['type'] ) {
    wp_die( esc_html__( 'Tipo de ficheiro não permitido.', 'acme-widgets' ) );
}
```

### Path traversal

Caminho de ficheiro que venha de input, option, meta ou nome de template é vetor de LFI.

```php
// ERRADO — ../../../wp-config.php
include plugin_dir_path( __FILE__ ) . 'templates/' . $_GET['tpl'] . '.php';

// CERTO — allowlist + validate_file
$allowed = [ 'list', 'single', 'empty' ];
$tpl     = sanitize_key( wp_unslash( $_GET['tpl'] ?? '' ) );

if ( ! in_array( $tpl, $allowed, true ) ) {
    return;
}

$path = plugin_dir_path( __FILE__ ) . 'templates/' . $tpl . '.php';

// validate_file devolve 0 quando o caminho é seguro (sem ../, sem :, sem path absoluto)
if ( 0 !== validate_file( $path ) || ! file_exists( $path ) ) {
    return;
}

include $path;
```

Para ficheiros fora de uma allowlist fixa, confirme que o caminho resolvido continua dentro da pasta permitida:

```php
$base = realpath( wp_upload_dir()['basedir'] . '/acme' );
$real = realpath( $candidate );

if ( ! $real || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) ) {
    return new WP_Error( 'acme_path', __( 'Caminho não permitido.', 'acme-widgets' ) );
}
```

Nunca passe input para `unlink()`, `file_get_contents()`, `include`, `require` ou `fopen()` sem este par allowlist + `realpath`.

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

### SSRF: use `wp_safe_remote_*` quando a URL vem do utilizador

O core já traz variantes seguras. São iguais às normais mas com `reject_unsafe_urls => true`, que passa a URL por `wp_http_validate_url()`: bloqueia loopback, IPs privados, portas fora de 80/443/8080 e redirects para hosts internos.

```php
// URL controlada pelo plugin (endpoint fixo da sua API):
$response = wp_remote_get( 'https://api.acme.com/v1/status', $args );

// URL que veio de input, option editável, webhook ou conteúdo remoto:
$response = wp_safe_remote_get( $user_supplied_url, $args );
```

Existem `wp_safe_remote_get()`, `wp_safe_remote_post()`, `wp_safe_remote_head()`, `wp_safe_remote_request()`.

`wp_safe_remote_*` **não** substitui allowlist quando só um conjunto de hosts faz sentido — bloqueia rede interna, não `evil.com`:

```php
$host = wp_parse_url( $url, PHP_URL_HOST );
if ( ! in_array( $host, [ 'api.acme.com', 'cdn.acme.com' ], true ) ) {
    return new WP_Error( 'acme_host_blocked', __( 'Host não permitido.', 'acme-widgets' ) );
}
```

Endurecimento extra em `wp-config.php` (útil em staging/produção controlada):

```php
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', 'api.acme.com,*.wordpress.org' );
```

Outras regras:

- `sslverify => false` nunca em produção — desliga verificação de certificado, abre MITM.
- `timeout` explícito (5–10s). Default de 5s aplica-se a `wp_remote_get`, mas seja explícito.
- `redirection` baixo (3). Redirect chain é vetor clássico de SSRF.
- Nunca ecoe o corpo da resposta sem escapar — é conteúdo externo, trata-se como input.
- Cacheie a resposta em transient (ver `references/performance.md`); evita rate limit e ataque de amplificação.

## 8. Padrões perigosos a evitar

| Função | Porquê | Use em vez |
|---|---|---|
| `eval` com input externo | Code injection | Refatorar — nunca há razão legítima |
| `extract` em superglobal | Variable injection | Atribuir explicitamente |
| `unserialize` em input externo | Object injection | `json_decode()` ou `maybe_unserialize` apenas em dado interno |
| `assert` com string | Eval disfarçado | Refatorar |
| `create_function` | Eval disfarçado, removido PHP 8 | Closures |
| `mt_rand` para tokens | Não criptograficamente seguro | `wp_generate_password()` ou `random_bytes()` |
| `md5` / `sha1` para senhas | Quebráveis | `wp_hash_password()` / `password_hash()` |
| `$_SERVER['HTTP_HOST']` confiável | Spoofável | Use `home_url()` / `site_url()` |
| `$_SERVER['REMOTE_ADDR']` atrás de proxy | Pode ser spoofado | Validar contra proxy conhecido |
| Inclusão de ficheiros por path do utilizador | LFI/RFI | Allowlist de paths permitidos |

### Sintaxe que impede a auditoria

O diretório do WordPress.org **não permite** estas, não por serem inseguras em si, mas porque **os codesniffers não detetam falta de escaping dentro delas** — o código deixa de ser auditável:

| Proibido | Em vez disso |
|---|---|
| HEREDOC / NOWDOC (`<<<HTML`) | Concatenação normal, ou `printf` com placeholders escapados |
| Short tags (`<?` e `<?=`) | `<?php` e `<?php echo esc_html( $v ); ?>` |

Na mesma linha, três padrões que quebram o site inteiro do utilizador e são causa de rejeição:

```php
ini_set( 'memory_limit', '-1' );          // nunca global; só dentro da função que precisa
date_default_timezone_set( $tz );          // WP espera UTC; parte get_post_time()/get_post_modified_time()
error_reporting( E_ALL );                  // suja o debug de quem usa o plugin
```

## 9. Direct file access

Cada ficheiro PHP do plugin deve começar com:

```php
<?php
defined( 'ABSPATH' ) || exit;
```

Isso previne execução direta caso o ficheiro seja acedido via URL. O risco é maior em ficheiros que **executam** código (chamadas de função, instanciação, `include` de outros ficheiros) do que em ficheiros que só definem classes/funções — mas a guarda custa uma linha, ponha-a em todos. Em pastas que não devem listar (uploads, includes), adicione `index.php` silencioso:

```php
<?php
// Silence is golden.
```

## 10. Logs e exposição de informação

- **Nunca** logue dados sensíveis (senhas, tokens, chaves API) — mesmo em `error_log()`
- **Nunca** exponha `display_errors = On` em produção
- **Nunca** retorne stack traces em respostas — use `WP_Error` ou `wp_die()` com mensagem genérica
- **Nunca** versione `.env`, `wp-config.php`, chaves privadas

## 11. Metadata registada e exposição REST

`register_post_meta()` / `register_term_meta()` / `register_user_meta()` são o ponto onde se define sanitização e quem pode ler/escrever uma meta. Meta não registada não tem sanitização automática nem esquema REST.

```php
add_action( 'init', function () {
    register_post_meta( 'acme_order', '_acme_internal_note', [
        'type'              => 'string',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
            return current_user_can( 'edit_post', $post_id );
        },
        'show_in_rest'      => false, // nota interna — não expor
    ] );
} );
```

### `show_in_rest` é uma decisão de segurança

`'show_in_rest' => true` torna a meta **legível por qualquer pessoa** que consiga ler o post via REST — incluindo visitantes anónimos em posts publicados. Antes de ligar, pergunte: "isto pode aparecer em `/wp-json/wp/v2/posts/123`?"

| Meta | `show_in_rest` |
|---|---|
| Cor de destaque, layout, campo público | `true` |
| Email, IP, telefone, notas internas, tokens | `false` |
| Campo que o editor Gutenberg precisa de gravar | `true` + `auth_callback` correto |

Com esquema (arrays/objetos exigem-no, senão a meta é rejeitada pelo REST):

```php
'show_in_rest' => [
    'schema' => [
        'type'  => 'array',
        'items' => [ 'type' => 'integer' ],
    ],
],
```

### `auth_callback` e meta protegida

- Meta com prefixo `_` é **protegida**: não aparece no metabox de campos personalizados e, por omissão, `auth_callback` devolve `current_user_can( 'edit_post_meta', ... )`.
- Ao registar com `show_in_rest`, o `auth_callback` é quem decide a **escrita** via REST. Default demasiado permissivo é escalada de privilégio.
- `auth_callback` recebe `( $allowed, $meta_key, $object_id, $user_id, $cap, $caps )` — use `$object_id` para meta cap, não uma cap genérica.

### Gravar meta a partir de formulário

```php
add_action( 'save_post_acme_order', function ( int $post_id ): void {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! isset( $_POST['acme_note_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acme_note_nonce'] ) ), 'acme_save_note' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $note = isset( $_POST['acme_note'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['acme_note'] ) )
        : '';

    update_post_meta( $post_id, '_acme_internal_note', $note );
} );
```

Ordem obrigatória: autosave → nonce → capability → unslash → sanitize → gravar. Falhar qualquer passo é o bug de metabox mais comum em plugins.

## 12. Exemplo completo — as cinco camadas

Adaptado do exemplo oficial (https://developer.wordpress.org/apis/security/example/). Uma ação destrutiva no front-end, com **todas** as camadas. Retirar qualquer uma torna isto explorável.

```php
/**
 * Camada 1 — gerar o link com nonce e output escapado.
 */
function acme_delete_link(): string {
    if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) {
        return '';
    }

    $url = add_query_arg(
        [
            'action' => 'acme_frontend_delete',
            'post'   => get_the_ID(),
            'nonce'  => wp_create_nonce( 'acme_frontend_delete' ),
        ],
        home_url()
    );

    return sprintf(
        '<a href="%1$s">%2$s</a>',
        esc_url( $url ),                                       // escaping de URL
        esc_html__( 'Apagar post', 'acme-widgets' )            // escaping de texto
    );
}

/**
 * Camada 2 — validar o request antes de agir.
 */
function acme_handle_delete(): void {
    // 2a. É mesmo esta ação?
    if ( ! isset( $_GET['action'], $_GET['post'], $_GET['nonce'] )
        || 'acme_frontend_delete' !== $_GET['action'] ) {
        return;
    }

    // 2b. Nonce — prova de intenção (CSRF).
    $nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );
    if ( ! wp_verify_nonce( $nonce, 'acme_frontend_delete' ) ) {
        return;
    }

    // 2c. Validar o alvo — não basta sanitizar o ID, tem de existir.
    $post_id = absint( $_GET['post'] );
    if ( ! $post_id || ! get_post( $post_id ) ) {
        return;
    }

    // 2d. Permissão sobre ESTE objeto, não permissão genérica.
    if ( ! current_user_can( 'delete_post', $post_id ) ) {
        return;
    }

    wp_trash_post( $post_id );

    // 2e. Redirect seguro — wp_safe_redirect só aceita hosts próprios (open redirect).
    wp_safe_redirect( home_url() );
    exit;
}

/**
 * Camada 3 — só expor a funcionalidade a quem a pode usar.
 */
function acme_register_delete_feature(): void {
    if ( ! current_user_can( 'edit_others_posts' ) ) {
        return;
    }

    add_filter( 'the_content', function ( string $content ): string {
        return $content . acme_delete_link();
    } );

    add_action( 'wp', 'acme_handle_delete' );
}
add_action( 'init', 'acme_register_delete_feature' );
```

| Camada | Protege contra |
|---|---|
| `esc_url()` / `esc_html__()` no link | XSS |
| `wp_verify_nonce()` | CSRF |
| `absint()` + `get_post()` | ID inválido, ação em objeto inexistente |
| `current_user_can( 'delete_post', $id )` | Privilege escalation (inclui IDOR — apagar post alheio) |
| `wp_safe_redirect()` | Open redirect |
| Esconder a feature sem `edit_others_posts` | Exposição desnecessária de superfície |

**Ponto que o exemplo oficial deixa implícito:** esconder o link (camada 3) não protege nada por si — o handler tem de repetir a verificação de capability. UI escondida não é controlo de acesso.

## 13. Manter-se atual

Vulnerabilidade não é só o código escrito hoje; é o código que envelhece.

- **Dependências Composer/npm**: `composer audit` e `npm audit` no CI. Lib abandonada é dívida de segurança.
- **PHP**: correr em versão suportada. PHP 7.4 está EOL desde nov/2022 — sem patches de segurança.
- **APIs do core**: funções deprecadas costumam sê-lo por motivo de segurança ou correção. `WP_DEBUG` mostra-as.
- **Acompanhar**: notas de release de segurança do WordPress, [WPScan](https://wpscan.com/) / [Patchstack](https://patchstack.com/database/) para ver vulnerabilidades reportadas na sua área.
- **Ter um canal de contacto**: email no readme e resposta rápida a relatórios. A equipa do Plugin Review fecha plugins sem resposta.
- **Corrigir depressa e publicar**: um fix não distribuído não protege ninguém; lance a versão e escreva no changelog o que foi corrigido (sem dar o exploit).

## Checklist

Ao auditar, percorra:

- [ ] Todo ficheiro PHP tem `defined( 'ABSPATH' ) || exit;`
- [ ] Todo formulário/AJAX/REST tem nonce verificado
- [ ] Toda ação tem `current_user_can()` apropriado
- [ ] Todo input de superglobal é sanitizado com função certa para o tipo
- [ ] Todo output é escapado conforme o contexto (html/attr/url/js)
- [ ] Toda query SQL custom usa `$wpdb->prepare()`
- [ ] Sem `eval`, `extract`, `unserialize` em input externo
- [ ] Uploads validam MIME + extensão por allowlist
- [ ] HTTP externo usa `wp_remote_*` com timeout e sslverify
- [ ] URL vinda de input/option usa `wp_safe_remote_*` (+ allowlist de host se aplicável)
- [ ] Meta registada com `sanitize_callback` e `auth_callback`
- [ ] `show_in_rest => true` só em meta sem dado privado
- [ ] Capability verificada é meta cap + ID quando a ação é sobre um objeto (ver `references/capabilities.md`)
- [ ] Zero verificações de role (`$user->roles`) — só capabilities
- [ ] Sem credenciais hardcoded
- [ ] `permission_callback` declarado em todo endpoint REST
- [ ] Erros não vazam paths/SQL/stack traces
- [ ] Dados lidos da BD também são escapados no output (princípio 3)
- [ ] `in_array()` sempre com terceiro argumento `true`; comparações com `===`
- [ ] `ORDER BY` / nome de coluna por allowlist, nunca de input direto
- [ ] Caminhos de ficheiro passam por allowlist + `validate_file()` ou `realpath()`
- [ ] Nonces não são impressos em HTML cacheado
- [ ] Nonce **e** `current_user_can()` — nunca só um dos dois
- [ ] `wp_verify_nonce()` testado por falsy, não por `=== true`
- [ ] String escapada por inteiro, não por segmentos concatenados
- [ ] `esc_url()` para output, `esc_url_raw()`/`sanitize_url()` para storage e redirects
- [ ] Redirects com `wp_safe_redirect()` quando o destino pode vir de input
- [ ] `switch` sobre input não confiável usa `switch ( true )` com `===`
- [ ] Verificação de capability repetida no **handler**, não só ao esconder a UI
- [ ] Dependências auditadas (`composer audit` / `npm audit`) e PHP em versão suportada
- [ ] Nenhuma função de escaping usada para sanitizar (nem o inverso)
- [ ] `filter_input`/`filter_var` sempre com filtro explícito
- [ ] Nonce sanitizado com `wp_unslash` + `sanitize_text_field` antes de `wp_verify_nonce`
- [ ] Nenhum `foreach` sobre `$_POST`/`$_GET`/`$_REQUEST` inteiro
- [ ] `wp_handle_upload()` / `media_handle_upload()` — nunca `move_uploaded_file()`
- [ ] `ALLOW_UNFILTERED_UPLOADS` ausente do código, mesmo em condicional
- [ ] Sem HEREDOC/NOWDOC nem short tags (impedem deteção de escaping em falta)
- [ ] Sem `ini_set()`, `date_default_timezone_set()` ou `error_reporting()` globais
