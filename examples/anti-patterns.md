# Anti-Patterns: Errado vs Certo

Pares lado a lado dos erros mais comuns em plugins WordPress. Use durante auditoria para apontar problemas com referência clara, ou ao escrever código novo para evitar a armadilha.

Cada par tem:
- **Errado** — código vulnerável/ruim
- **Certo** — correção
- **Porquê** — o que dá errado e em que cenário

---

## Conteúdo

- [1. Output sem escape (XSS)](#1-output-sem-escape-xss)
- [2. SQL com concatenação](#2-sql-com-concatenação)
- [3. Form sem nonce](#3-form-sem-nonce)
- [4. REST endpoint sem permission_callback](#4-rest-endpoint-sem-permission_callback)
- [5. AJAX sem nonce verificado](#5-ajax-sem-nonce-verificado)
- [6. Input sem unslash + sanitize](#6-input-sem-unslash--sanitize)
- [7. Query em loop (N+1)](#7-query-em-loop-n1)
- [8. posts_per_page => -1](#8-posts_per_page---1)
- [9. WP_Query sem otimização](#9-wp_query-sem-otimização)
- [10. Option grande com autoload](#10-option-grande-com-autoload)
- [11. Script enfileirado em todas as páginas](#11-script-enfileirado-em-todas-as-páginas)
- [12. Inline script no wp_head](#12-inline-script-no-wp_head)
- [13. Shortcode com echo](#13-shortcode-com-echo)
- [14. CDN externo hardcoded](#14-cdn-externo-hardcoded)
- [15. wp_remote_get sem timeout](#15-wp_remote_get-sem-timeout)
- [16. unserialize em input externo](#16-unserialize-em-input-externo)
- [17. String sem text domain](#17-string-sem-text-domain)
- [18. Capability genérica demais](#18-capability-genérica-demais)
- [19. wp_kses com tags demais](#19-wp_kses-com-tags-demais)
- [20. SVG upload sem sanitização](#20-svg-upload-sem-sanitização)
- [21. Verificar role em vez de capability](#21-verificar-role-em-vez-de-capability)
- [22. Meta exposta ao REST sem pensar](#22-meta-exposta-ao-rest-sem-pensar)
- [23. Traduzir antes do `init`](#23-traduzir-antes-do-init)
- [24. Allowlist com comparação frouxa](#24-allowlist-com-comparação-frouxa)
- [25. Path traversal em include](#25-path-traversal-em-include)
- [26. `remove_menu_page()` como controlo de acesso](#26-remove_menu_page-como-controlo-de-acesso)
- [27. URL dentro de string traduzível](#27-url-dentro-de-string-traduzível)
- [Como usar este ficheiro durante auditoria](#como-usar-este-ficheiro-durante-auditoria)

## 1. Output sem escape (XSS)

```php
// ❌ Errado
echo '<h1>' . $title . '</h1>';
echo '<a href="' . $url . '">link</a>';

// ✅ Certo
echo '<h1>' . esc_html( $title ) . '</h1>';
echo '<a href="' . esc_url( $url ) . '">link</a>';
```

**Porquê**: `$title` pode vir de input do utilizador (post title, user meta, option). Sem escape, qualquer `<script>` no valor executa. `esc_url` ainda valida o protocolo (bloqueia `javascript:`).

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

**Porquê**: `$user_id` pode conter SQL malicioso (tautology injection, ex.: `1 OR 1 = 1 --`). SQL injection clássica. `prepare()` faz binding seguro com tipos (`%d`/`%s`/`%f`).

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
        wp_die( esc_html__( 'Sem permissão.', 'acme-widgets' ), '', 403 );
    }
    $settings = isset( $_POST['settings'] ) ? acme_sanitize_settings( wp_unslash( $_POST['settings'] ) ) : [];
    update_option( 'acme_settings', $settings );
} );

// E no form HTML:
wp_nonce_field( 'acme_save_settings' );
```

**Porquê**: sem nonce, qualquer site externo pode forçar admin a submeter form (CSRF). Sem capability, qualquer subscriber pode mudar settings.

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

**Porquê**: `__return_true` em endpoint que muda estado é catastrófico. Visitante anônimo pode invocar.

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

**Porquê**: `wp_ajax_*` autentica que o utilizador está logado, mas não verifica intenção. CSRF cross-site faria victim's browser disparar.

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

**Porquê**: WP adiciona slashes em superglobals (legado de magic_quotes). Sanitizar sem `wp_unslash` guarda dados com `\'` literais. `isset` previne notice se campo não veio.

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

**Porquê**: 100 posts = 200 queries vs 2 queries. Em listas grandes, é a diferença entre 50ms e 5s.

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

**Porquê**: `-1` em site com 50k posts trava o servidor (OOM ou timeout). Sempre pagine.

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

**Porquê**: defaults do WP_Query fazem `SQL_CALC_FOUND_ROWS` (para paginação) e prefetch de terms/meta de **todos** os posts. Se você não usa, é trabalho jogado fora.

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

**Porquê**: options com `autoload=yes` são carregadas em **toda** request. 2MB de JSON = +50ms em todo hit do site.

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

**Porquê**: script só usado em single de produto não tem porque ser baixado na home, blog, etc. Custa banda + parse + bloqueia render.

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

**Porquê**: inline em wp_head bloqueia render, não tem cache, e não respeita defer/async. `wp_localize_script` injeta no `<head>` mas atrelado ao script com deps corretas.

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

**Porquê**: shortcode com `echo` aparece **antes** do parágrafo onde foi colocado, quebrando layout. Shortcodes **sempre** retornam string.

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

**Porquê**: CDN externo (1) viola GDPR (envia IP do visitante a terceiro sem consentimento), (2) é razão de rejeição no WP.org, (3) sai do controle de versão/cache. Sempre bundle local.

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

**Porquê**: default timeout é 5s — mas servidor remoto travado durante um hook crítico bloqueia toda a request do utilizador. Sempre defina timeout curto + trate erro.

---

## 16. unserialize em input externo

```php
// ❌ Errado (PHP Object Injection)
$untrusted = $_POST['data'] ?? '';
$data      = unserialize( $untrusted );

// ✅ Certo (use JSON)
$data = json_decode( wp_unslash( $_POST['data'] ?? '' ), true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
    wp_die( esc_html__( 'Dados inválidos.', 'acme-widgets' ) );
}
```

**Porquê**: `unserialize` em input controlado pelo atacante pode instanciar classes mágicas (`__destruct`, `__wakeup`) levando a RCE. JSON é seguro porque só constrói arrays/scalars.

---

## 17. String sem text domain

```php
// ❌ Errado
echo __( 'Olá mundo' );
_e( 'Submit', 'wp' );

// ✅ Certo
echo esc_html__( 'Olá mundo', 'acme-widgets' );
esc_html_e( 'Submit', 'acme-widgets' );
```

**Porquê**: sem text domain certo, tradução não carrega (cai no domain `default`/WP core). Tools de WP.org rejeitam strings sem domain do plugin.

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

**Porquê**: `manage_options` é a capability "deus" — qualquer feature atrás dela exige admin completo. Use a capability **mínima** que faz sentido pra ação.

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

**Porquê**: `wp_kses_allowed_html('post')` permite ~30 tags incluindo `<iframe>` em alguns contextos. Para input de utilizador não-admin, allowlist mínima é mais seguro.

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
            $file['error'] = __( 'SVG contém código não permitido.', 'acme-widgets' );
            return $file;
        }
    }
    return $file;
} );
```

**Porquê**: SVG é XML que pode conter `<script>` ou event handlers. Upload sem sanitize = XSS armazenado executado quando alguém vê a imagem.

## 21. Verificar role em vez de capability

```php
// ❌ Errado — role é configurável; qualquer plugin de gestão de roles quebra isto
$user = wp_get_current_user();
if ( in_array( 'editor', (array) $user->roles, true ) ) {
    acme_delete_order( $order_id );
}

// ❌ Errado — cap genérica: Contributor apaga encomenda alheia
if ( current_user_can( 'edit_posts' ) ) {
    acme_delete_order( $order_id );
}

// ✅ Certo — meta cap resolvida contra o objeto
if ( current_user_can( 'delete_acme_order', $order_id ) ) {
    acme_delete_order( $order_id );
}
```

**Porquê**: roles são só um saco de capabilities guardado na BD e sites reais alteram-nas. Cap é o contrato; role não é. E cap sem o ID do objeto ignora autoria e estado — é escalada de privilégio. Ver `references/capabilities.md`.

---

## 22. Meta exposta ao REST sem pensar

```php
// ❌ Errado — email do cliente legível por anónimos em /wp-json/wp/v2/acme_order/123
register_post_meta( 'acme_order', 'customer_email', [
    'single'       => true,
    'show_in_rest' => true,
] );

// ✅ Certo — dado privado fica fora do REST, com sanitização e auth
register_post_meta( 'acme_order', '_acme_customer_email', [
    'type'              => 'string',
    'single'            => true,
    'sanitize_callback' => 'sanitize_email',
    'auth_callback'     => static fn( $allowed, $key, $post_id ): bool =>
        current_user_can( 'edit_post', $post_id ),
    'show_in_rest'      => false,
] );
```

**Porquê**: `show_in_rest => true` torna a meta legível por quem consegue ler o post — incluindo visitantes anónimos se o post estiver publicado. Sem `sanitize_callback` a meta guarda o que vier; sem `auth_callback` e com `show_in_rest`, terceiros escrevem-na via REST. Prefixo `_` marca a meta como protegida.

---

## 23. Traduzir antes do `init`

```php
// ❌ Errado — corre no load do ficheiro; WP 6.7 emite
// "_load_textdomain_just_in_time was called incorrectly"
class Acme_Admin {
    private string $title;

    public function __construct() {
        $this->title = __( 'Definições Acme', 'acme-widgets' );
    }
}
add_action( 'plugins_loaded', fn() => new Acme_Admin() );

// ✅ Certo — traduzir no momento do uso, dentro de hooks >= init
class Acme_Admin {
    public function title(): string {
        return __( 'Definições Acme', 'acme-widgets' );
    }
}
```

**Porquê**: o carregamento just-in-time do text domain só está disponível a partir do `init`. Traduzir antes devolve a string por traduzir **e** enche o `debug.log` de notices. Nada de `__()` no corpo de ficheiros, em propriedades default, em construtores chamados cedo, nem em `register_activation_hook`.

---

## 24. Allowlist com comparação frouxa

```php
// ❌ Errado — in_array sem strict: in_array( 0, [ 'date', 'title' ] ) é true em PHP 7
$allowed = [ 'date', 'title', 'menu_order' ];
$orderby = in_array( $_GET['orderby'], $allowed ) ? $_GET['orderby'] : 'date';

// ❌ Errado — denylist: nunca se apanha tudo
$orderby = str_replace( [ ';', '--', 'UNION' ], '', $_GET['orderby'] );

// ✅ Certo — allowlist com comparação estrita
$allowed = [ 'date', 'title', 'menu_order' ];
$raw     = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
$orderby = in_array( $raw, $allowed, true ) ? $raw : 'date';
```

**Porquê**: sem o terceiro argumento, `in_array` compara com `==` — em PHP 7 uma string não numérica iguala `0`, e a allowlist passa a deixar passar valores que não estão lá. Denylist é pior: é uma lista do que já se conhece, e o atacante escreve o que não está nela. Em `ORDER BY` isto é SQL injection direta, porque `$wpdb->prepare()` não trata identificadores.

---

## 25. Path traversal em include

```php
// ❌ Errado — ?tpl=../../../../wp-config lê o ficheiro de configuração
include plugin_dir_path( __FILE__ ) . 'templates/' . $_GET['tpl'] . '.php';

// ❌ Ainda errado — remover "../" uma vez não chega ("....//" sobrevive)
$tpl = str_replace( '../', '', $_GET['tpl'] );

// ✅ Certo — allowlist + validate_file
$allowed = [ 'list', 'single', 'empty' ];
$tpl     = isset( $_GET['tpl'] ) ? sanitize_key( wp_unslash( $_GET['tpl'] ) ) : '';

if ( ! in_array( $tpl, $allowed, true ) ) {
    return;
}

$path = plugin_dir_path( __FILE__ ) . 'templates/' . $tpl . '.php';

if ( 0 !== validate_file( $path ) || ! file_exists( $path ) ) {
    return; // validate_file devolve 0 só quando o caminho é seguro
}

include $path;
```

**Porquê**: input em `include`/`require`/`file_get_contents`/`unlink` é Local File Inclusion — leitura de `wp-config.php` (credenciais da BD, salts) e, com upload combinado, execução remota. Filtrar substrings é sempre contornável; a única defesa fiável é allowlist do valor + `validate_file()` ou `realpath()` contra a pasta base.

---

## 26. `remove_menu_page()` como controlo de acesso

```php
// ❌ Errado — esconde o menu, mas /wp-admin/tools.php continua acessível
add_action( 'admin_menu', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        remove_menu_page( 'tools.php' );
        remove_menu_page( 'acme-widgets' );
    }
}, 99 );

// ✅ Certo — a capability na própria página é que decide
add_action( 'admin_menu', function () {
    add_menu_page(
        __( 'Acme', 'acme-widgets' ),
        __( 'Acme', 'acme-widgets' ),
        'acme_manage_settings',        // quem não tem, não vê nem acede
        'acme-widgets',
        'acme_render_settings_page'
    );
} );

function acme_render_settings_page() {
    if ( ! current_user_can( 'acme_manage_settings' ) ) {
        wp_die( esc_html__( 'Sem permissão.', 'acme-widgets' ) );
    }
    // ...
}
```

**Porquê**: o menu é só navegação. Quem souber o URL entra na mesma, e o handler do form continua a aceitar POST. UI escondida nunca é controlo de acesso — a capability certa em `add_menu_page` já esconde **e** bloqueia.

---

## 27. URL dentro de string traduzível

```php
// ❌ Errado — um tradutor pode mudar o destino do link
_e( 'Leia a <a href="https://acme.test/docs">documentação</a>.', 'acme-widgets' );

// ❌ Errado — string sem escape, tradução pode injetar JavaScript
_e( 'Bem-vindo ao Acme.', 'acme-widgets' );

// ✅ Certo — URL no código, tags como placeholders, output escapado
printf(
    /* translators: 1: abertura da tag <a>, 2: fecho da tag </a> */
    esc_html__( 'Leia a %1$sdocumentação%2$s.', 'acme-widgets' ),
    '<a href="' . esc_url( 'https://acme.test/docs' ) . '">',
    '</a>'
);
```

**Porquê**: tradução é input de terceiros. Com o URL dentro da string, quem traduz reaponta o link (phishing) sem tocar no código. Sem escape, pode injetar JavaScript. Pela mesma razão: compile o seu próprio `.mo` a partir do `.po` recebido (`msgfmt -cv`), nunca aceite o binário já compilado.

---

---

## Como usar este ficheiro durante auditoria

Ao revisar código, mapeie cada problema para o número do anti-pattern acima:

> "Linha 42: anti-pattern #2 (SQL com concatenação). Use `$wpdb->prepare()`."

Isso dá ao desenvolvedor referência clara + correção exata.
