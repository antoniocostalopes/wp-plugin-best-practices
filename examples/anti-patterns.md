# Anti-Patterns: Errado vs Certo

Pares lado a lado dos erros mais comuns em plugins WordPress. Use durante auditoria para apontar problemas com referência clara, ou ao escrever código novo para evitar a armadilha.

Cada par tem:
- **Errado** — código vulnerável/ruim
- **Certo** — correção
- **Por quê** — o que dá errado e em que cenário

---

## 1. Output sem escape (XSS)

```php
// ❌ Errado
echo '<h1>' . $title . '</h1>';
echo '<a href="' . $url . '">link</a>';

// ✅ Certo
echo '<h1>' . esc_html( $title ) . '</h1>';
echo '<a href="' . esc_url( $url ) . '">link</a>';
```

**Por quê**: `$title` pode vir de input do usuário (post title, user meta, option). Sem escape, qualquer `<script>` no valor executa. `esc_url` ainda valida o protocolo (bloqueia `javascript:`).

---

## 2. SQL com concatenação

```php
// ❌ Errado
$wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_author = $user_id" );

// ✅ Certo
$wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_author = %d",
        $user_id
    )
);
```

**Por quê**: `$user_id` pode conter SQL malicioso (tautology injection, ex.: `1 OR 1 = 1 --`). SQL injection clássica. `prepare()` faz binding seguro com tipos (`%d`/`%s`/`%f`).

---

## 3. Form sem nonce

```php
// ❌ Errado
add_action( 'admin_post_save_settings', function() {
    update_option( 'acme_settings', $_POST['settings'] );
} );

// ✅ Certo
add_action( 'admin_post_save_settings', function() {
    check_admin_referer( 'acme_save_settings' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sem permissão.', 'acme' ), '', 403 );
    }
    $settings = isset( $_POST['settings'] ) ? acme_sanitize_settings( wp_unslash( $_POST['settings'] ) ) : [];
    update_option( 'acme_settings', $settings );
} );

// E no form HTML:
wp_nonce_field( 'acme_save_settings' );
```

**Por quê**: sem nonce, qualquer site externo pode forçar admin a submeter form (CSRF). Sem capability, qualquer subscriber pode mudar settings.

---

## 4. REST endpoint sem permission_callback

```php
// ❌ Errado
register_rest_route( 'acme/v1', '/delete', [
    'methods'             => 'POST',
    'callback'            => 'acme_delete_item',
    'permission_callback' => '__return_true',
] );

// ✅ Certo
register_rest_route( 'acme/v1', '/delete', [
    'methods'             => WP_REST_Server::DELETABLE,
    'callback'            => 'acme_delete_item',
    'permission_callback' => function( $request ) {
        $id = absint( $request['id'] );
        return current_user_can( 'delete_post', $id );
    },
    'args'                => [
        'id' => [
            'required'          => true,
            'sanitize_callback' => 'absint',
        ],
    ],
] );
```

**Por quê**: `__return_true` em endpoint que muda estado é catastrófico. Visitante anônimo pode invocar.

---

## 5. AJAX sem nonce verificado

```php
// ❌ Errado
add_action( 'wp_ajax_acme_action', function() {
    update_user_meta( get_current_user_id(), 'acme_pref', $_POST['value'] );
    wp_send_json_success();
} );

// ✅ Certo
add_action( 'wp_ajax_acme_action', function() {
    check_ajax_referer( 'acme_nonce' );
    if ( ! current_user_can( 'edit_user', get_current_user_id() ) ) {
        wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
    }
    $value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
    update_user_meta( get_current_user_id(), 'acme_pref', $value );
    wp_send_json_success();
} );
```

**Por quê**: `wp_ajax_*` autentica que o usuário está logado, mas não verifica intenção. CSRF cross-site faria victim's browser disparar.

---

## 6. Input sem unslash + sanitize

```php
// ❌ Errado
$name = $_POST['name'];

// ❌ Quase certo (faltou unslash)
$name = sanitize_text_field( $_POST['name'] );

// ✅ Certo
$name = isset( $_POST['name'] )
    ? sanitize_text_field( wp_unslash( $_POST['name'] ) )
    : '';
```

**Por quê**: WP adiciona slashes em superglobals (legado de magic_quotes). Sanitizar sem `wp_unslash` salva dados com `\'` literais. `isset` previne notice se campo não veio.

---

## 7. Query em loop (N+1)

```php
// ❌ Errado
foreach ( $post_ids as $id ) {
    $title = get_the_title( $id );        // pode trigger SQL
    $meta  = get_post_meta( $id, 'key', true ); // outra query
    echo esc_html( $title . ': ' . $meta );
}

// ✅ Certo
_prime_post_caches( $post_ids, false, false );
update_meta_cache( 'post', $post_ids );
foreach ( $post_ids as $id ) {
    $title = get_the_title( $id );        // cache hit
    $meta  = get_post_meta( $id, 'key', true ); // cache hit
    echo esc_html( $title . ': ' . $meta );
}
```

**Por quê**: 100 posts = 200 queries vs 2 queries. Em listas grandes, é a diferença entre 50ms e 5s.

---

## 8. posts_per_page => -1

```php
// ❌ Errado
$all = get_posts( [
    'post_type'      => 'product',
    'posts_per_page' => -1,
] );

// ✅ Certo
$page = 1;
do {
    $batch = get_posts( [
        'post_type'      => 'product',
        'posts_per_page' => 100,
        'paged'          => $page,
        'no_found_rows'  => true,
    ] );
    foreach ( $batch as $post ) {
        // processar
    }
    $page++;
} while ( count( $batch ) === 100 );
```

**Por quê**: `-1` em site com 50k posts trava o servidor (OOM ou timeout). Sempre pagine.

---

## 9. WP_Query sem otimização

```php
// ❌ Errado
$q = new WP_Query( [
    'post_type'      => 'product',
    'posts_per_page' => 10,
] );

// ✅ Certo (se não pagina e não usa terms/meta no template)
$q = new WP_Query( [
    'post_type'              => 'product',
    'posts_per_page'         => 10,
    'no_found_rows'          => true,
    'update_post_term_cache' => false,
    'update_post_meta_cache' => false,
] );
```

**Por quê**: defaults do WP_Query fazem `SQL_CALC_FOUND_ROWS` (para paginação) e prefetch de terms/meta de **todos** os posts. Se você não usa, é trabalho jogado fora.

---

## 10. Option grande com autoload

```php
// ❌ Errado
add_option( 'acme_big_cache', $two_mb_of_json );
// ou
update_option( 'acme_big_cache', $two_mb_of_json );  // autoload=yes implícito se não existia

// ✅ Certo
add_option( 'acme_big_cache', $two_mb_of_json, '', 'no' );
// ou
update_option( 'acme_big_cache', $two_mb_of_json, false );  // false = autoload=no
```

**Por quê**: options com `autoload=yes` são carregadas em **toda** request. 2MB de JSON = +50ms em todo hit do site.

---

## 11. Script enfileirado em todas as páginas

```php
// ❌ Errado
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script( 'acme-product', plugins_url( 'product.js', __FILE__ ), [], ACME_VERSION );
} );

// ✅ Certo
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'product' ) ) {
        return;
    }
    wp_enqueue_script( 'acme-product', plugins_url( 'product.js', __FILE__ ), [], ACME_VERSION, true );
} );
```

**Por quê**: script só usado em single de produto não tem porque ser baixado na home, blog, etc. Custa banda + parse + bloqueia render.

---

## 12. Inline script no wp_head

```php
// ❌ Errado
add_action( 'wp_head', function() {
    echo '<script>var acmeApiUrl = "' . rest_url( 'acme/v1' ) . '";</script>';
} );

// ✅ Certo
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script( 'acme-main', plugins_url( 'main.js', __FILE__ ), [], ACME_VERSION, true );
    wp_localize_script( 'acme-main', 'acmeData', [
        'apiUrl' => esc_url_raw( rest_url( 'acme/v1' ) ),
        'nonce'  => wp_create_nonce( 'wp_rest' ),
    ] );
} );
```

**Por quê**: inline em wp_head bloqueia render, não tem cache, e não respeita defer/async. `wp_localize_script` injeta no `<head>` mas atrelado ao script com deps corretas.

---

## 13. Shortcode com echo

```php
// ❌ Errado
add_shortcode( 'acme_box', function( $atts ) {
    echo '<div class="acme-box">conteúdo</div>';  // ecoa direto
} );

// ✅ Certo
add_shortcode( 'acme_box', function( $atts ) {
    return '<div class="acme-box">conteúdo</div>';  // retorna
} );

// Com template via ob_start:
add_shortcode( 'acme_box', function( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'acme_box' );
    ob_start();
    ?>
    <div class="acme-box">
        <?php echo esc_html( get_the_title( absint( $atts['id'] ) ) ); ?>
    </div>
    <?php
    return ob_get_clean();
} );
```

**Por quê**: shortcode com `echo` aparece **antes** do parágrafo onde foi colocado, quebrando layout. Shortcodes **sempre** retornam string.

---

## 14. CDN externo hardcoded

```php
// ❌ Errado
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'fonts', 'https://fonts.googleapis.com/css?family=Roboto' );
    wp_enqueue_script( 'chart', 'https://cdn.jsdelivr.net/npm/chart.js' );
} );

// ✅ Certo — bundle local
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'acme-fonts', plugins_url( 'assets/fonts.css', __FILE__ ), [], ACME_VERSION );
    wp_enqueue_script( 'acme-chart', plugins_url( 'assets/chart.min.js', __FILE__ ), [], '4.4.0', true );
} );
```

**Por quê**: CDN externo (1) viola GDPR (envia IP do visitante a terceiro sem consentimento), (2) é razão de rejeição no WP.org, (3) sai do controle de versão/cache. Sempre bundle local.

---

## 15. wp_remote_get sem timeout

```php
// ❌ Errado
$response = wp_remote_get( $url );

// ✅ Certo
$response = wp_remote_get( $url, [
    'timeout'     => 10,
    'redirection' => 3,
    'sslverify'   => true,
] );

if ( is_wp_error( $response ) ) {
    return $response;
}
$code = wp_remote_retrieve_response_code( $response );
if ( 200 !== $code ) {
    return new WP_Error( 'http_error', 'API retornou ' . $code );
}
```

**Por quê**: default timeout é 5s — mas servidor remoto travado durante um hook crítico bloqueia toda a request do usuário. Sempre defina timeout curto + trate erro.

---

## 16. unserialize em input externo

```php
// ❌ Errado (PHP Object Injection)
$untrusted = $_POST['data'] ?? '';
$data      = unserialize( $untrusted );

// ✅ Certo (use JSON)
$data = json_decode( wp_unslash( $_POST['data'] ?? '' ), true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
    wp_die( esc_html__( 'Dados inválidos.', 'acme' ) );
}
```

**Por quê**: `unserialize` em input controlado pelo atacante pode instanciar classes mágicas (`__destruct`, `__wakeup`) levando a RCE. JSON é seguro porque só constrói arrays/scalars.

---

## 17. String sem text domain

```php
// ❌ Errado
echo __( 'Olá mundo' );
_e( 'Submit', 'wp' );

// ✅ Certo
echo esc_html__( 'Olá mundo', 'acme' );
esc_html_e( 'Submit', 'acme' );
```

**Por quê**: sem text domain certo, tradução não carrega (cai no domain `default`/WP core). Tools de WP.org rejeitam strings sem domain do plugin.

---

## 18. Capability genérica demais

```php
// ❌ Errado
if ( current_user_can( 'manage_options' ) ) {
    // qualquer admin pode... mas a feature é de editor
}

// ✅ Certo
if ( current_user_can( 'edit_posts' ) ) {
    // editor é o nível certo
}

// Melhor ainda — capability específica:
if ( current_user_can( 'manage_acme_widgets' ) ) {
    // capability custom registrada no activate
}
```

**Por quê**: `manage_options` é a capability "deus" — qualquer feature atrás dela exige admin completo. Use a capability **mínima** que faz sentido pra ação.

---

## 19. wp_kses com tags demais

```php
// ❌ Errado (libera tudo, defeats the purpose)
echo wp_kses( $user_html, wp_kses_allowed_html( 'post' ) );  // permite muita coisa

// ❌ Pior ainda
echo $user_html;  // sem kses

// ✅ Certo (allowlist mínima do que você realmente precisa)
$allowed = [
    'a'      => [ 'href' => [], 'title' => [] ],
    'strong' => [],
    'em'     => [],
    'br'     => [],
    'p'      => [],
];
echo wp_kses( $user_html, $allowed );
```

**Por quê**: `wp_kses_allowed_html('post')` permite ~30 tags incluindo `<iframe>` em alguns contextos. Para input de usuário não-admin, allowlist mínima é mais seguro.

---

## 20. SVG upload sem sanitização

```php
// ❌ Errado
add_filter( 'upload_mimes', function( $mimes ) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
} );

// ✅ Certo — use plugin como Safe SVG, ou sanitize manualmente
add_filter( 'upload_mimes', function( $mimes ) {
    if ( current_user_can( 'manage_options' ) ) {  // só admin
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
} );

add_filter( 'wp_handle_upload_prefilter', function( $file ) {
    if ( 'image/svg+xml' === $file['type'] ) {
        // Use uma biblioteca dedicada — `enshrined/svg-sanitize` via Composer.
        // Validação manual (mostrada de forma reduzida) é frágil e dispara falsos
        // positivos em scanners AV. A regra real bloqueia tags executáveis,
        // handlers `on*` e o esquema `javascript:` dentro do XML do SVG.
        if ( ! \Acme\Widgets\Svg\Sanitizer::is_safe( $file['tmp_name'] ) ) {
            $file['error'] = __( 'SVG contém código não permitido.', 'acme' );
            return $file;
        }
    }
    return $file;
} );
```

**Por quê**: SVG é XML que pode conter `<script>` ou event handlers. Upload sem sanitize = XSS armazenado executado quando alguém vê a imagem.

---

## Como usar este arquivo durante auditoria

Ao revisar código, mapeie cada problema para o número do anti-pattern acima:

> "Linha 42: anti-pattern #2 (SQL com concatenação). Use `$wpdb->prepare()`."

Isso dá ao desenvolvedor referência clara + correção exata.
