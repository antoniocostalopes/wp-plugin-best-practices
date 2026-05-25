# Padrões de Código WordPress

Referência de coding standards, naming, namespaces, PHPDoc e i18n.

## WPCS — WordPress Coding Standards

Há dois rulesets oficiais:

- **WordPress** — geral (procedural + OOP, recomendado)
- **WordPress-Extra** — adiciona regras mais rigorosas (recomendado para plugins novos)
- **WordPress-Docs** — comentários PHPDoc

Instalar:

```bash
composer require --dev wp-coding-standards/wpcs phpcompatibility/phpcompatibility-wp dealerdirect/phpcodesniffer-composer-installer
```

`phpcs.xml.dist` mínimo:

```xml
<?xml version="1.0"?>
<ruleset name="Acme Widgets">
    <description>WPCS para Acme Widgets.</description>

    <file>.</file>

    <exclude-pattern>/vendor/*</exclude-pattern>
    <exclude-pattern>/node_modules/*</exclude-pattern>
    <exclude-pattern>/assets/build/*</exclude-pattern>

    <arg value="sp"/>
    <arg name="extensions" value="php"/>

    <rule ref="WordPress-Extra">
        <exclude name="WordPress.Files.FileName"/>
    </rule>
    <rule ref="WordPress-Docs"/>

    <rule ref="PHPCompatibilityWP"/>
    <config name="testVersion" value="8.0-"/>

    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="acme-widgets"/>
            </property>
        </properties>
    </rule>

    <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
        <properties>
            <property name="prefixes" type="array">
                <element value="acme_widgets"/>
                <element value="ACME_WIDGETS"/>
                <element value="Acme\Widgets"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

## Regras de formatação principais

```php
// Tabs para indentação, não spaces
function acme_example() {
	// quatro espaços visuais mas char é \t
	$value = 1;
}

// Espaços dentro de parênteses
if ( $value === 1 ) {           // certo
if ($value === 1) {              // errado

function acme_foo( $arg ) {     // certo
function acme_foo($arg) {        // errado

// Yoda conditions (WPCS exige)
if ( null === $value ) {        // certo
if ( $value === null ) {        // errado

// Comparações estritas
if ( $a === $b ) {              // certo
if ( $a == $b ) {                // errado

// Aspas simples por padrão, duplas se interpola
$msg = 'Olá mundo';             // certo
$msg = "Olá $name";              // certo (interpolação)
$msg = "Olá mundo";              // evitar
```

## Nomenclatura

### Prefixo único

Toda função, classe, constante, hook customizado, option key, post meta key, e arquivo deve ser prefixado.

| Item | Convenção | Exemplo |
|---|---|---|
| Função (procedural) | `acme_widgets_` | `acme_widgets_render_box()` |
| Classe (legado, sem ns) | `Acme_Widgets_` | `class Acme_Widgets_Admin` |
| Classe (com namespace) | namespace só | `namespace Acme\Widgets; class Admin {}` |
| Constante | `ACME_WIDGETS_` | `ACME_WIDGETS_VERSION` |
| Hook (action/filter) | `acme_widgets_` | `do_action( 'acme_widgets_before_render' )` |
| Option key | `acme_widgets_` | `get_option( 'acme_widgets_settings' )` |
| Post meta key | `_acme_widgets_` (underscore prefix oculta no admin) | `update_post_meta( $id, '_acme_widgets_data', $v )` |
| Transient key | `acme_widgets_` | `get_transient( 'acme_widgets_feed' )` |
| CSS class | `acme-widgets-` | `.acme-widgets-card` |
| JS global (se necessário) | `acmeWidgets` | `window.acmeWidgets.init()` |
| Custom Post Type | `acme_widget` (≤20 chars) | `register_post_type( 'acme_widget' )` |
| Taxonomy | `acme_category` (≤32 chars) | `register_taxonomy( 'acme_category', ... )` |
| Capability | `acme_widgets_` | `current_user_can( 'manage_acme_widgets' )` |

### Snake_case vs camelCase

WordPress usa **snake_case** em PHP (funções, variáveis, file names) — siga isso:

```php
function acme_get_widget_data() {}      // certo
function acmeGetWidgetData() {}          // errado (estilo PSR/Laravel, não WP)

$widget_id = 1;                          // certo
$widgetId = 1;                           // errado
```

**Exceção**: classes usam PascalCase mesmo em código WPCS:

```php
class Acme_Widgets_Admin {}              // certo (WPCS clássico)
class Admin {}                            // certo dentro de namespace
```

Em JS no WordPress, **camelCase** é a convenção:

```js
const widgetData = getWidgetData();
```

### File names (regra WPCS clássica)

WPCS clássico exige:

- Classes: `class-acme-widgets-admin.php` (lowercase, hifens, prefixo `class-`)
- Funções: `acme-widgets-functions.php`
- Interfaces: `interface-acme-widgets-renderable.php`

**Em plugins novos com namespaces e PSR-4**, é aceitável usar `PascalCase.php` no `src/` — desde que você `exclude` essa regra no `phpcs.xml`:

```xml
<exclude name="WordPress.Files.FileName"/>
```

## Namespaces e autoloading

Plugin moderno (PHP 7.4+) deve usar namespaces:

```php
<?php
namespace Acme\Widgets\Admin;

use Acme\Widgets\Plugin;
use WP_Post;

class Menu {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
    }
}
```

PSR-4 autoload via Composer (recomendado):

```json
{
    "autoload": {
        "psr-4": {
            "Acme\\Widgets\\": "src/"
        }
    }
}
```

Ou autoloader manual (sem Composer):

```php
spl_autoload_register( function( $class ) {
    $prefix = 'Acme\\Widgets\\';
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file = __DIR__ . '/src/' . $relative . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );
```

## PHPDoc

WordPress segue PHPDoc strict — toda função/método/classe pública precisa de bloco:

```php
/**
 * Renderiza o widget no front-end.
 *
 * @since 1.0.0
 *
 * @param int    $widget_id ID do widget.
 * @param string $style     Estilo de renderização. Aceita 'default', 'compact'.
 * @return string HTML renderizado, ou string vazia se inválido.
 */
function acme_widgets_render( int $widget_id, string $style = 'default' ): string {
    // ...
}
```

Tags importantes:

- `@since X.Y.Z` em toda função pública
- `@param tipo $nome Descrição.`
- `@return tipo Descrição.`
- `@throws ExceptionClass Quando ...`
- `@deprecated X.Y.Z Use foo() em vez disso.`
- `@internal` para coisas que não são API pública
- `@access private` (legado, prefira `private` real)

Para hooks customizados:

```php
/**
 * Filtra o conteúdo do widget antes do render.
 *
 * @since 1.0.0
 *
 * @param string $content   Conteúdo HTML.
 * @param int    $widget_id ID do widget.
 */
$content = apply_filters( 'acme_widgets_content', $content, $widget_id );
```

`wp i18n make-pot` e ferramentas de docs leem essas tags.

## Type declarations (PHP 7.4/8.x)

Aproveite tipos onde possível:

```php
// PHP 7.4+
public function get_widget( int $id ): ?Widget {
    // ...
}

// PHP 8.0+ — union types
public function find( int|string $key ): mixed {
    // ...
}

// PHP 8.1+ — readonly, enums
final class WidgetType {
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
    ) {}
}
```

**Cuidado**: se seu plugin tem `Requires PHP: 7.4`, não use sintaxe 8.x. Use ferramenta tipo `phpstan` para verificar.

## Internacionalização (i18n)

### Text domain consistente

Sempre o mesmo, igual ao slug do plugin:

```php
__( 'Hello', 'acme-widgets' );       // certo
__( 'Hello', 'acme_widgets' );        // errado (underscores)
__( 'Hello' );                        // errado (sem domain)
```

### Funções de tradução

| Função | Uso |
|---|---|
| `__( 'text', 'domain' )` | Retorna string traduzida |
| `_e( 'text', 'domain' )` | Ecoa (sem escape) — evitar; prefira `esc_html_e` |
| `esc_html__()` / `esc_html_e()` | Traduz + escapa HTML |
| `esc_attr__()` / `esc_attr_e()` | Traduz + escapa atributo |
| `_n( 'one', 'many', $n, 'domain' )` | Plural |
| `_x( 'text', 'context', 'domain' )` | Com contexto para desambiguar |
| `_ex( 'text', 'context', 'domain' )` | _x + echo |
| `_nx( ... )` | Plural com contexto |

### Placeholders

Use `printf` com placeholders, **nunca** concatene:

```php
// Errado — quebra tradução
echo __( 'Olá, ', 'acme' ) . $name . __( '! Você tem ', 'acme' ) . $n . __( ' mensagens', 'acme' );

// Certo
printf(
    /* translators: 1: nome do usuário, 2: número de mensagens */
    esc_html( _n(
        'Olá, %1$s! Você tem %2$d mensagem.',
        'Olá, %1$s! Você tem %2$d mensagens.',
        $n,
        'acme'
    ) ),
    esc_html( $name ),
    intval( $n )
);
```

### Comentários para tradutores

Sempre adicione comentário acima de strings com placeholders ou contexto não-óbvio:

```php
printf(
    /* translators: %s: nome do produto */
    esc_html__( 'Adicionado %s ao carrinho.', 'acme' ),
    esc_html( $product_name )
);
```

### Carregando traduções

```php
add_action( 'init', function() {
    load_plugin_textdomain(
        'acme-widgets',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
} );
```

Para WP.org, **não** chame `load_plugin_textdomain` — WP.org carrega automaticamente. Mas inclua para plugins fora do diretório.

### Gerar arquivo POT

```bash
wp i18n make-pot . languages/acme-widgets.pot
```

## Constantes vs Options vs Config

| Tipo de dado | Onde guardar |
|---|---|
| Versão do plugin, paths, URLs | Constante (`define()` no main file) |
| Configurações do usuário | Option (`get_option()`) |
| Secrets (API keys) | Option **criptografada** ou `wp-config.php` |
| Estado runtime, cache | Transient ou object cache |
| Por-post | Post meta |
| Por-user | User meta |
| Per-site (multisite) | `get_blog_option()` / `get_site_option()` |

## Organização de hooks

Pattern recomendado: registrar hooks dentro de classes em método `register()`:

```php
class Admin {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
    }

    public function add_menu(): void { /* ... */ }
    public function register_settings(): void { /* ... */ }
    public function action_links( array $links, string $file ): array { /* ... */ }
}
```

**Não** chame `add_action` fora de bootstrap controlado. Hooks dispersos por arquivos viram pesadelo de debug.

## Erros e exceptions

Use `WP_Error` para erros de negócio (esperados):

```php
function acme_get_widget( int $id ): WP_Error|Widget {
    $post = get_post( $id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', __( 'Widget não encontrado.', 'acme' ) );
    }
    return Widget::from_post( $post );
}

$widget = acme_get_widget( 1 );
if ( is_wp_error( $widget ) ) {
    echo esc_html( $widget->get_error_message() );
    return;
}
```

Use `Exception` apenas para condições verdadeiramente excepcionais (bug, dados corrompidos). **Sempre** capture no boundary — nunca deixe vazar para resposta HTTP.

## Ferramentas recomendadas

```bash
composer require --dev \
    wp-coding-standards/wpcs \
    phpcompatibility/phpcompatibility-wp \
    szepeviktor/phpstan-wordpress \
    php-stubs/wordpress-stubs

# Rodar
vendor/bin/phpcs
vendor/bin/phpstan analyse src --level=6
```

Para testes:

```bash
composer require --dev \
    yoast/phpunit-polyfills \
    wp-phpunit/wp-phpunit \
    brain/monkey  # mock de funções WP
```

## .editorconfig recomendado

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = tab
indent_size = 4

[*.{yml,yaml,json}]
indent_style = space
indent_size = 2

[*.md]
trim_trailing_whitespace = false
```
