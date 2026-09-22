# Scaffolding de Plugins WordPress

Referência para criar plugins do zero com estrutura correta.

Handbook: https://developer.wordpress.org/plugins/plugin-basics/

## Conteúdo

- [Decisão inicial: que tipo de plugin?](#decisão-inicial-que-tipo-de-plugin)
- [Anatomia mínima](#anatomia-mínima)
- [Header obrigatório](#header-obrigatório)
- [Bootstrap pattern (OOP recomendado)](#bootstrap-pattern-oop-recomendado)
- [Classe principal (singleton + service container)](#classe-principal-singleton--service-container)
- [Ativação — o que fazer](#ativação--o-que-fazer)
- [Desativação — limpar runtime](#desativação--limpar-runtime)
- [Uninstall — limpeza completa](#uninstall--limpeza-completa)
- [Registrando Custom Post Types](#registrando-custom-post-types)
- [Registrando taxonomias](#registrando-taxonomias)
- [Registrando metadados (register_post_meta)](#registrando-metadados-register_post_meta)
- [Registrando blocks Gutenberg](#registrando-blocks-gutenberg)
- [Shortcodes (legado, ainda útil)](#shortcodes-legado-ainda-útil)
- [REST API endpoints](#rest-api-endpoints)
- [Settings page (Settings API)](#settings-page-settings-api)
- [Estrutura completa de exemplo (OOP)](#estrutura-completa-de-exemplo-oop)
- [Próximos passos após scaffold](#próximos-passos-após-scaffold)

## Decisão inicial: que tipo de plugin?

| Tipo | Quando | Estrutura |
|---|---|---|
| **Single-file** | Hook ou snippet pequeno (<200 linhas) | 1 ficheiro PHP + readme.txt |
| **Procedural multi-file** | Plugin médio com várias features | `acme-plugin.php` + `includes/` |
| **OOP com classes** | Plugin com 5+ módulos, equipa grande | Namespace + PSR-4 autoload |
| **Composer-based** | Plugin com dependências externas | `composer.json` + vendor |

Para qualquer tipo acima de single-file, prefira OOP a partir do início — refatorar depois é doloroso.

## Anatomia mínima

```
acme-plugin/
├── acme-plugin.php         # ficheiro principal (header)
├── uninstall.php           # cleanup ao desinstalar (opcional mas recomendado)
├── readme.txt              # obrigatório se publicar no WP.org
├── index.php               # silence is golden
├── includes/
│   ├── index.php
│   ├── class-acme-plugin.php
│   ├── class-acme-admin.php
│   └── class-acme-frontend.php
├── assets/
│   ├── index.php
│   ├── css/
│   │   ├── index.php
│   │   └── admin.css
│   └── js/
│       ├── index.php
│       └── admin.js
├── languages/
│   ├── index.php
│   └── acme-plugin.pot
└── templates/
    ├── index.php
    └── single-acme.php
```

Cada subdiretório recebe um `index.php` silencioso (`<?php // Silence is golden.`) para prevenir directory listing.

## Header obrigatório

O ficheiro principal precisa do header WP. Sem isso, WP não reconhece o plugin:

```php
<?php
/**
 * Plugin Name:       Acme Widgets
 * Plugin URI:        https://acme.example/widgets
 * Description:       Adiciona widgets personalizados ao painel.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Acme Inc.
 * Author URI:        https://acme.example
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-widgets
 * Domain Path:       /languages
 * Update URI:        false
 * Requires Plugins:  woocommerce
 *
 * @package AcmeWidgets
 */

defined( 'ABSPATH' ) || exit;
```

Campos obrigatórios: `Plugin Name`. Todos os outros são recomendados (especialmente `Text Domain`, `Version`, `Requires at least`, `Requires PHP`, `License`).

**Notas sobre versões mínimas:**

- **PHP 7.4 está EOL desde nov/2022.** Para plugins novos, prefira `Requires PHP: 8.0` ou superior. Só use 7.4 se tiver requisito explícito de compatibilidade legada.
- **`Requires at least: 6.2`** habilita o placeholder `%i` em `$wpdb->prepare()` (para identificadores). Versões anteriores são suportadas pelo core mas perdem features modernas.

**Headers úteis menos conhecidos:**

- `Update URI: false` — previne que WP.org tente atualizar um plugin que não vem do diretório oficial (essencial para plugins privados/comerciais).
- `Requires Plugins:` — **WP 6.5+**. Lista slugs do WP.org de plugins dependentes, separados por vírgula (ex: `woocommerce, wordpress-seo`). WP impede ativação se dependências não estiverem instaladas+ativas.
- `Network: true` — plugin só pode ser ativado a nível de network em multisite.

## Bootstrap pattern (OOP recomendado)

```php
<?php
/**
 * Plugin Name: Acme Widgets
 * ... (header completo acima)
 */

defined( 'ABSPATH' ) || exit;

// Constantes do plugin
define( 'ACME_VERSION', '1.0.0' );
define( 'ACME_PLUGIN_FILE', __FILE__ );
define( 'ACME_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACME_MIN_PHP', '8.0' );
define( 'ACME_MIN_WP', '6.2' );

// Compatibility check antes de carregar
if ( version_compare( PHP_VERSION, ACME_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %s: PHP version required */
                    __( 'Acme Widgets requer PHP %s ou superior.', 'acme-widgets' ),
                    ACME_MIN_PHP
                )
            )
        );
    } );
    return;
}

// Autoloader (PSR-4 simples)
spl_autoload_register( function( $class ) {
    $prefix = 'Acme\\Widgets\\';
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
    $file = ACME_PLUGIN_DIR . 'src/' . $relative . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

// Ativação / desativação / uninstall
register_activation_hook( __FILE__, [ 'Acme\\Widgets\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Acme\\Widgets\\Plugin', 'deactivate' ] );
// uninstall vai em uninstall.php (não usar hook)

// Bootstrap
add_action( 'plugins_loaded', function() {
    \Acme\Widgets\Plugin::instance()->run();
} );
```

## Classe principal (singleton + service container)

```php
<?php
namespace Acme\Widgets;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {}
    private function __clone() {}

    public function run(): void {
        $this->load_textdomain();
        $this->register_hooks();

        if ( is_admin() ) {
            ( new Admin() )->register();
        }
        ( new Frontend() )->register();
    }

    private function load_textdomain(): void {
        add_action( 'init', function() {
            load_plugin_textdomain(
                'acme-widgets',
                false,
                dirname( plugin_basename( ACME_PLUGIN_FILE ) ) . '/languages'
            );
        } );
    }

    private function register_hooks(): void {
        // hooks globais
    }

    public static function activate(): void {
        // criar tabelas, options padrão, capabilities, flush rewrites
        if ( ! get_option( 'acme_db_version' ) ) {
            ( new Installer() )->install();
        }
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        // limpar cron, flush rewrites
        wp_clear_scheduled_hook( 'acme_sync_event' );
        flush_rewrite_rules();
    }
}
```

## Ativação — o que fazer

```php
public static function activate(): void {
    // 1. Verificar compatibilidade (de novo, caso ative em ambiente errado)
    if ( version_compare( PHP_VERSION, ACME_MIN_PHP, '<' ) ) {
        deactivate_plugins( plugin_basename( ACME_PLUGIN_FILE ) );
        wp_die( esc_html__( 'PHP incompatível.', 'acme-widgets' ) );
    }

    // 2. Criar/migrar tabelas se houver
    self::install_or_upgrade_schema();

    // 3. Options padrão
    add_option( 'acme_settings', self::default_settings(), '', 'no' );

    // 4. Custom capabilities
    $role = get_role( 'administrator' );
    if ( $role ) {
        $role->add_cap( 'manage_acme_widgets' );
    }

    // 5. Flush rewrites (registre CPT/taxonomies antes, no init)
    flush_rewrite_rules();
}
```

**Importante**: `register_activation_hook` roda **antes** dos hooks normais — o `init` não disparou ainda. Portanto **não** chame `register_post_type()` aqui. Ele já estará registrado quando WP correr próximo `init`, e `flush_rewrite_rules()` aqui pega as rules.

## Desativação — limpar runtime

```php
public static function deactivate(): void {
    // Limpar wp-cron
    $hooks = [ 'acme_sync_event', 'acme_cleanup_event' ];
    foreach ( $hooks as $hook ) {
        $timestamp = wp_next_scheduled( $hook );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook );
        }
    }

    // Flush rewrites
    flush_rewrite_rules();
}
```

**Não** apague dados em deactivate — utilizador pode estar só trocando de versão. Apague em `uninstall.php`.

## Uninstall — limpeza completa

`uninstall.php` na raiz do plugin é chamado quando utilizador deleta (não desativa) o plugin:

```php
<?php
/**
 * Uninstall handler — chamado quando o plugin é deletado.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Apenas com permissão
if ( ! current_user_can( 'activate_plugins' ) ) {
    return;
}

// 1. Apagar options
$options = [
    'acme_settings',
    'acme_db_version',
    'acme_install_date',
];
foreach ( $options as $option ) {
    delete_option( $option );
    delete_site_option( $option ); // multisite
}

// 2. Apagar tabelas custom (apenas se opção "delete all data" estiver marcada)
$settings = get_option( 'acme_settings', [] );
if ( ! empty( $settings['delete_data_on_uninstall'] ) ) {
    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}acme_log" );

    // Apagar posts/meta do plugin
    $posts = get_posts( [
        'post_type'      => 'acme_widget',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
    ] );
    foreach ( $posts as $id ) {
        wp_delete_post( $id, true );
    }

    // Apagar transients
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_acme_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_acme_%'" );
}

// 3. Limpar cron (caso ainda tenha algo)
wp_clear_scheduled_hook( 'acme_sync_event' );

// 4. Remover capabilities personalizadas
$role = get_role( 'administrator' );
if ( $role ) {
    $role->remove_cap( 'manage_acme_widgets' );
}
```

**Importante**: dê ao utilizador opção "delete all data on uninstall" no settings page (desligado por padrão). Não destrua dados sem opt-in explícito.

## Registrando Custom Post Types

> Se o CPT precisa de permissões próprias (`capability_type` + `map_meta_cap`), ver `references/capabilities.md` antes de registar — caps mal configuradas bloqueiam até o administrador.

Sempre no hook `init`, nunca em `plugins_loaded` ou `activate`:

```php
add_action( 'init', function() {
    register_post_type( 'acme_widget', [
        'labels' => [
            'name'          => __( 'Widgets', 'acme-widgets' ),
            'singular_name' => __( 'Widget', 'acme-widgets' ),
            // ... (todos os labels)
        ],
        'public'              => true,
        'show_in_rest'        => true,        // Gutenberg
        'supports'            => [ 'title', 'editor', 'thumbnail' ],
        'has_archive'         => true,
        'rewrite'             => [ 'slug' => 'widgets' ],
        'capability_type'     => 'post',
        'menu_icon'           => 'dashicons-screenoptions',
        'show_in_graphql'     => true,        // se usa WPGraphQL
    ] );
} );
```

## Registrando taxonomias

```php
add_action( 'init', function (): void {
    register_taxonomy( 'acme_region', [ 'acme_order' ], [
        'labels'            => [ /* ... traduzidos, dentro do init ... */ ],
        'public'            => true,
        'hierarchical'      => true,          // true = tipo categoria; false = tipo tag
        'show_in_rest'      => true,          // necessário para o editor de blocos
        'show_admin_column' => true,
        'rewrite'           => [ 'slug' => 'regiao' ],
        'capabilities'      => [
            'manage_terms' => 'manage_acme_orders',
            'edit_terms'   => 'manage_acme_orders',
            'delete_terms' => 'manage_acme_orders',
            'assign_terms' => 'edit_acme_orders',
        ],
    ] );
} );
```

Slug da taxonomia: ≤32 caracteres, minúsculas, sem hífen no identificador interno. Registar depois do `init` faz os termos não aparecerem; registar sem `show_in_rest` tira-a do editor de blocos.

### Term IDs guardados: o split de termos (WP 4.2+)

Antes do WP 4.2, termos com o mesmo slug em taxonomias diferentes **partilhavam o mesmo term ID**. Desde 4.2, ao atualizar um desses termos ele é dividido e **recebe um ID novo**.

Se o plugin guarda term IDs em options, post meta ou user meta, esses IDs podem ficar a apontar para o nada:

```php
add_action( 'split_shared_term', function ( int $term_id, int $new_term_id, int $term_taxonomy_id, string $taxonomy ): void {
    if ( 'acme_region' !== $taxonomy ) {
        return;
    }

    $featured = (array) get_option( 'acme_featured_regions', [] );
    $key      = array_search( $term_id, $featured, true );

    if ( false !== $key ) {
        $featured[ $key ] = $new_term_id;
        update_option( 'acme_featured_regions', $featured );
    }
}, 10, 4 );
```

Para IDs guardados em post meta, procure com `get_posts()` pelo `meta_value` antigo e atualize. Para verificar um ID isolado depois do facto existe `wp_get_split_term( $old_term_id, $taxonomy )`.

Instalações modernas já fizeram o split há muito; isto importa quando se herda um plugin antigo ou se suportam sites que vieram de antes de 2015.

## Registrando metadados (register_post_meta)

Meta não registada não tem tipo, sanitização, default nem esquema REST. Registe **toda** a meta que o plugin usa, no `init`.

```php
add_action( 'init', function () {
    register_post_meta( 'acme_order', '_acme_total', [
        'type'              => 'number',
        'description'       => __( 'Total da encomenda.', 'acme-widgets' ),
        'single'            => true,
        'default'           => 0,
        'sanitize_callback' => static fn( $v ): float => (float) $v,
        'auth_callback'     => static fn( $allowed, $meta_key, $post_id ): bool =>
            current_user_can( 'edit_post', $post_id ),
        'show_in_rest'      => true,  // decisão de segurança — ver references/security.md
    ] );
} );
```

| Arg | Nota |
|---|---|
| `type` | `string`, `boolean`, `integer`, `number`, `array`, `object`. Obrigatório para REST |
| `single` | `true` = um valor; `false` = lista. Muda o retorno de `get_post_meta` |
| `default` | WP 5.5+. Devolvido quando a meta não existe (sem gravar nada) |
| `sanitize_callback` | Corre em `update_post_meta`. Não substitui sanitização do input do formulário |
| `auth_callback` | Decide quem escreve via REST. Meta `_` protegida usa `edit_post_meta` por omissão |
| `show_in_rest` | `true`, ou array com `schema` (obrigatório para `array`/`object`) |

Existem equivalentes: `register_term_meta()`, `register_user_meta()` (WP 6.4+), `register_comment_meta()`, e o genérico `register_meta( $object_type, ... )`.

**Meta pública vs privada:** prefixo `_` esconde dos "Campos personalizados" e ativa o `auth_callback` protegido. Use `_` para tudo o que é interno do plugin.

Para o editor de blocos gravar a meta, ela tem de ter `show_in_rest => true` **e** o `auth_callback` tem de deixar passar quem edita o post.

## Registrando blocks Gutenberg

Use `block.json` (WP 5.8+) — não registre programaticamente:

```
acme-plugin/
├── blocks/
│   └── my-block/
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       ├── save.js
│       └── style.css
```

```php
add_action( 'init', function() {
    register_block_type( __DIR__ . '/blocks/my-block' );
} );
```

`block.json` é a fonte da verdade — define attributes, supports, assets, render callback.

## Shortcodes (legado, ainda útil)

```php
add_action( 'init', function() {
    add_shortcode( 'acme_widget', 'acme_widget_render' );
} );

function acme_widget_render( $atts ) {
    $atts = shortcode_atts( [
        'id'    => 0,
        'style' => 'default',
    ], $atts, 'acme_widget' );

    $id    = absint( $atts['id'] );
    $style = sanitize_key( $atts['style'] );

    ob_start();
    // ... render seguro (escape tudo)
    return ob_get_clean();
}
```

Shortcodes **sempre retornam string**, nunca ecoam. Use `ob_start()` para capturar templates.

### Atributos: as quatro regras

O utilizador escreve o shortcode à mão — pode passar atributos a mais, a menos, ou com maiúsculas. O handler recebe 3 parâmetros:

```php
function acme_widget_render( $atts = [], $content = null, $tag = '' ): string {
    // 1. Normalizar as chaves para minúsculas ([acme_widget ID="3"] funciona)
    $atts = array_change_key_case( (array) $atts, CASE_LOWER );

    // 2. Defaults + merge com o que o utilizador passou ($tag permite filtrar por shortcode)
    $atts = shortcode_atts( [ 'id' => 0, 'style' => 'default' ], $atts, $tag );

    // 3. Sanitizar cada valor pelo tipo esperado
    $id    = absint( $atts['id'] );
    $style = sanitize_key( $atts['style'] );

    $out = '<div class="acme-widget acme-widget--' . esc_attr( $style ) . '">';

    // 4. Conteúdo entre tags (shortcode envolvente) — nunca o imprima cru
    if ( null !== $content ) {
        $out .= apply_filters( 'the_content', $content );
    }

    return $out . '</div>';
}
```

`shortcode_atts` com o 3.º argumento (`$tag`) expõe o filtro `shortcode_atts_{$tag}`, que permite a terceiros mudar os defaults — é a forma de tornar o shortcode extensível.

Registe todos os shortcodes num único sítio, no `init`. E lembre-se: o valor devolvido entra no conteúdo do post, por isso escapar é obrigatório, mesmo quando o atributo "só pode" ter dois valores.

## REST API endpoints

```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'acme/v1', '/widgets', [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'acme_get_widgets',
            'permission_callback' => '__return_true',
            'args'                => [
                'per_page' => [
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => fn( $v ) => $v > 0 && $v <= 100,
                ],
            ],
        ],
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'acme_create_widget',
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
            'args'                => [
                'title' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ],
    ] );
} );
```

## Settings page (Settings API)

```php
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'Acme Widgets', 'acme-widgets' ),
        __( 'Acme Widgets', 'acme-widgets' ),
        'manage_options',
        'acme-widgets',
        'acme_render_settings_page'
    );
} );

add_action( 'admin_init', function() {
    register_setting( 'acme_widgets', 'acme_settings', [
        'sanitize_callback' => 'acme_sanitize_settings',
        'default'           => [],
    ] );

    add_settings_section(
        'acme_main',
        __( 'Configurações Principais', 'acme-widgets' ),
        '__return_false',
        'acme-widgets'
    );

    add_settings_field(
        'api_key',
        __( 'API Key', 'acme-widgets' ),
        'acme_render_api_key_field',
        'acme-widgets',
        'acme_main'
    );
} );

function acme_sanitize_settings( $input ) {
    $output = [];
    $output['api_key'] = isset( $input['api_key'] )
        ? sanitize_text_field( $input['api_key'] )
        : '';
    return $output;
}
```

## Estrutura completa de exemplo (OOP)

```
acme-widgets/
├── acme-widgets.php
├── uninstall.php
├── readme.txt
├── composer.json (opcional)
├── src/
│   ├── Plugin.php              # Acme\Widgets\Plugin
│   ├── Admin/
│   │   ├── Menu.php
│   │   ├── Settings.php
│   │   └── Notices.php
│   ├── Frontend/
│   │   ├── Shortcodes.php
│   │   └── Enqueue.php
│   ├── REST/
│   │   └── WidgetController.php
│   ├── PostTypes/
│   │   └── Widget.php
│   ├── Database/
│   │   ├── Installer.php
│   │   └── Schema.php
│   └── Util/
│       └── Helpers.php
├── assets/
│   ├── src/                    # source (TS, SCSS)
│   └── build/                  # compilado (versionado se sem CI)
├── blocks/
│   └── widget-block/
│       └── block.json
├── languages/
│   └── acme-widgets.pot
└── templates/
    ├── single-widget.php
    └── archive-widget.php
```

## Próximos passos após scaffold

1. Inicializar git (`.gitignore` para `vendor/`, `node_modules/`, `*.zip`, `.DS_Store`)
2. Adicionar PHPCS com ruleset WPCS (`composer require --dev wp-coding-standards/wpcs`)
3. Adicionar PHPStan (`composer require --dev szepeviktor/phpstan-wordpress`)
4. Gerar `acme-widgets.pot` com `wp i18n make-pot . languages/acme-widgets.pot` (WP-CLI)
5. Se for público, ler `references/checklist.md`
