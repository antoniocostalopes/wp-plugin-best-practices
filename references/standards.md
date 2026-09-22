# Padrões de Código WordPress

Referência de coding standards, naming, namespaces, PHPDoc e i18n.

Handbook: https://developer.wordpress.org/coding-standards/

## Conteúdo

- [WPCS — WordPress Coding Standards](#wpcs--wordpress-coding-standards)
- [Regras de formatação principais](#regras-de-formatação-principais)
- [Nomenclatura](#nomenclatura)
- [Namespaces e autoloading](#namespaces-e-autoloading)
- [PHPDoc](#phpdoc)
- [Type declarations (PHP 7.4/8.x)](#type-declarations-php-748x)
- [Internacionalização (i18n)](#internacionalização-i18n)
- [Caminhos e URLs — nunca hardcode](#caminhos-e-urls--nunca-hardcode)
- [Constantes vs Options vs Config](#constantes-vs-options-vs-config)
- [Organização de hooks](#organização-de-hooks)
- [Erros e exceptions](#erros-e-exceptions)
- [Enqueue: funções corretas](#enqueue-funções-corretas)
- [Ferramentas recomendadas](#ferramentas-recomendadas)
- [.editorconfig recomendado](#editorconfig-recomendado)

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

Toda função, classe, constante, hook personalizado, option key, post meta key, e ficheiro deve ser prefixado.

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

#### Regras que a Plugin Review Team aplica ao prefixo

- **Dois ou três caracteres já não chega.** São ~100 mil plugins no WP.org; `ecp_`, `aw_` e afins colidem. Use ≥4 e distinto.
- **Proibido** usar `wp_`, `_` ou `__` como prefixo de funções/classes/constantes globais — são reservados ao core. Dentro de classes, à vontade; `__()` e `_n()` do core são outra coisa.
- Evite também o prefixo do plugin de que depende (`woo_` para extensões WooCommerce) — pertence a esse projeto.

#### `if ( ! function_exists() )` é armadilha, não proteção

```php
// ERRADO — se outro plugin definir acme_render() primeiro, o SEU código nunca corre
if ( ! function_exists( 'acme_render' ) ) {
    function acme_render() { /* ... */ }
}
```

Falha silenciosa: a função existe, mas é a do outro, com outro comportamento. O plugin parte de formas impossíveis de depurar. Prefixo único resolve o problema de raiz. `function_exists()` só se justifica para **bibliotecas partilhadas** que vários plugins podem carregar.

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

### Nome do ficheiro principal

O ficheiro com o header `Plugin Name:` tem de ter o **mesmo nome da pasta**, que é o **mesmo nome do slug**. Plugin com slug `acme-widgets` → pasta `acme-widgets/` → ficheiro `acme-widgets.php`. Nomes genéricos (`index.php`, `plugin.php`, `main.php`) causam problemas em algumas configurações e são motivo de pedido de alteração na revisão.

Os headers ficam **só** nesse ficheiro — nunca repetidos noutros PHP.

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

Para hooks personalizados:

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

### Nunca use variáveis em funções gettext

O parser de traduções **lê o código sem o executar**. Variável no texto, no contexto ou no text domain é invisível para quem traduz.

```php
// ERRADO — tradutor não vê string nenhuma
esc_html__( $greeting, 'acme-widgets' );
esc_html__( 'Olá', $plugin_slug );
_e( $label, TEXT_DOMAIN );

// CERTO — literais em ambos os parâmetros
esc_html__( 'Olá, como está?', 'acme-widgets' );
```

Text domain é sempre string literal, igual ao slug do plugin. Nem constante, nem variável.

### Placeholders

Use `printf` com placeholders, **nunca** concatene:

```php
// Errado — quebra tradução
echo __( 'Olá, ', 'acme-widgets' ) . $name . __( '! Você tem ', 'acme-widgets' ) . $n . __( ' mensagens', 'acme-widgets' );

// Certo
printf(
    /* translators: 1: nome do utilizador, 2: número de mensagens */
    esc_html( _n(
        'Olá, %1$s! Você tem %2$d mensagem.',
        'Olá, %1$s! Você tem %2$d mensagens.',
        $n,
        'acme-widgets'
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
    esc_html__( 'Adicionado %s ao carrinho.', 'acme-widgets' ),
    esc_html( $product_name )
);
```

### Carregando traduções (regras atuais)

**Plugin no WP.org:** não chame `load_plugin_textdomain()`. Desde WP 4.6 o core carrega traduções do translate.wordpress.org automaticamente, just-in-time, na primeira chamada a `__()`. A chamada manual é ruído.

**Plugin fora do diretório** (premium, cliente, .mo próprio): aí sim é preciso.

```php
add_action( 'init', function () {
    load_plugin_textdomain(
        'acme-widgets',
        false,
        dirname( plugin_basename( ACME_FILE ) ) . '/languages'
    );
} );
```

`Domain Path: /languages` no header tem de apontar para a mesma pasta.

### Nunca traduza antes do `init`

WP 6.7 passou a emitir o notice `_load_textdomain_just_in_time was called incorrectly` quando uma string é traduzida antes do hook `init`. Erros típicos:

```php
// ERRADO — corre no load do ficheiro, muito antes do init
$acme_labels = [ 'title' => __( 'Encomendas', 'acme-widgets' ) ];

class Acme_Admin {
    private string $title;

    public function __construct() {
        // ERRADO se a classe for instanciada em plugins_loaded
        $this->title = __( 'Definições Acme', 'acme-widgets' );
    }
}
```

```php
// CERTO — traduzir só quando a string é usada, dentro de hooks >= init
class Acme_Admin {
    public function title(): string {
        return __( 'Definições Acme', 'acme-widgets' );
    }
}

add_action( 'init', function () {
    $labels = [ 'title' => __( 'Encomendas', 'acme-widgets' ) ];
} );
```

Regra prática: **nada de `__()` no corpo de ficheiros, em propriedades default, em construtores chamados cedo, nem em `register_activation_hook`.** Registo de CPT/taxonomia fica no `init`, onde já é seguro.

### Traduções em JavaScript

`__()` do pacote `@wordpress/i18n` precisa de ser ligado ao handle do script:

```php
wp_enqueue_script(
    'acme-admin',
    plugins_url( 'build/admin.js', ACME_FILE ),
    [ 'wp-i18n' ],   // dependência obrigatória
    ACME_VERSION,
    true
);

wp_set_script_translations(
    'acme-admin',
    'acme-widgets',
    plugin_dir_path( ACME_FILE ) . 'languages' // omitir para plugins do WP.org
);
```

```js
import { __, sprintf, _n } from '@wordpress/i18n';
__( 'Guardar', 'acme-widgets' );
```

Gerar os ficheiros `.json` que o WordPress carrega para JS:

```bash
wp i18n make-pot . languages/acme-widgets.pot
wp i18n make-json languages/ --no-purge   # .po -> .json por script handle
```

Sem o `make-json`, as strings JS aparecem sempre em inglês por muito que o `.po` esteja traduzido.

### Segurança em i18n — tradução é input não confiável

https://developer.wordpress.org/plugins/internationalization/security/

Quem traduz pode injetar. Três regras:

**1. Escape strings traduzidas.** A tradução pode conter JavaScript.

```php
_e( 'A API REST chegou no WordPress 4.7.', 'acme-widgets' );          // inseguro
esc_html_e( 'A API REST chegou no WordPress 4.7.', 'acme-widgets' );  // seguro
```

**2. Nunca ponha URLs dentro da string.** Um tradutor malicioso muda o destino do link.

```php
// ERRADO — o href viaja dentro do texto traduzível
_e( 'Por favor <a href="https://exemplo.com/registo">registe-se</a>.', 'acme-widgets' );

// CERTO — as tags são placeholders, o URL fica no código
printf(
    /* translators: 1: abertura da tag <a>, 2: fecho da tag </a> */
    esc_html__( 'Por favor %1$sregiste-se%2$s.', 'acme-widgets' ),
    '<a href="https://exemplo.com/registo">',
    '</a>'
);
```

**3. Compile o seu próprio `.mo`.** Se o tradutor enviar `.po` **e** `.mo`, descarte o `.mo` — não há como saber se foi compilado a partir daquele `.po`.

```bash
msgfmt -cv -o languages/acme-widgets-pt_PT.mo languages/acme-widgets-pt_PT.po
```

Linha de comando e não PoEdit, porque o PoEdit reescreve os headers do `.po`.

Traduções vindas do translate.wordpress.org passam por editores da equipa Polyglots — é esse o mecanismo de verificação que substitui parte do risco. Para `.po` recebidos diretamente, verifique o conteúdo (traduzir de volta com um tradutor automático apanha spam).

### Gerar ficheiro POT

```bash
wp i18n make-pot . languages/acme-widgets.pot
```

## Caminhos e URLs — nunca hardcode

O utilizador pode mover e renomear a pasta `wp-content`. E `__FILE__` resolve symlinks, por isso um caminho montado à mão parte quando a pasta do plugin é um link simbólico.

```php
plugins_url( 'assets/app.js', __FILE__ );   // URL de um ficheiro do plugin
plugin_dir_path( __FILE__ );                 // caminho absoluto, com barra final
plugin_dir_url( __FILE__ );                  // URL base, com barra final
plugin_basename( __FILE__ );                 // "acme-widgets/acme-widgets.php"
```

| Preciso de | Função |
|---|---|
| URL/caminho do plugin | `plugins_url()`, `plugin_dir_url()`, `plugin_dir_path()`, `plugin_basename()` |
| Pasta de uploads | `wp_upload_dir()` |
| URL do admin | `admin_url()` (multisite: `get_admin_url()`, `network_admin_url()`) |
| URL do site / home | `site_url()`, `home_url()` (multisite: `get_site_url()`, `get_home_url()`, `network_home_url()`) |
| `wp-content` e `wp-includes` | `content_url()`, `includes_url()` |
| Tema ativo | `get_stylesheet_directory()`, `get_template_directory()` e variantes `_uri` |

As constantes `WP_CONTENT_DIR`, `WP_CONTENT_URL`, `WP_PLUGIN_DIR`, `WP_PLUGIN_URL` e `UPLOADS` existem, mas **não devem ser usadas diretamente** por plugins — são detalhe interno do core.

## Constantes vs Options vs Config

| Tipo de dado | Onde guardar |
|---|---|
| Versão do plugin, paths, URLs | Constante (`define()` no main file) |
| Configurações do utilizador | Option (`get_option()`) |
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

**Não** chame `add_action` fora de bootstrap controlado. Hooks dispersos por ficheiros viram pesadelo de debug.

**Evite closures anónimas em hooks que terceiros possam querer remover.** Closure registada é irremovível por `remove_action()` — quem integra com o seu plugin fica sem saída. Métodos nomeados (ou estáticos) de uma classe com singleton acessível são removíveis. Closure só para código interno e trivial. Detalhe em `references/hooks-catalog.md` (secção 15).

## Erros e exceptions

Use `WP_Error` para erros de negócio (esperados):

```php
function acme_get_widget( int $id ): WP_Error|Widget {
    $post = get_post( $id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', __( 'Widget não encontrado.', 'acme-widgets' ) );
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

## Enqueue: funções corretas

| Quero | Função |
|---|---|
| JS de ficheiro | `wp_register_script()` + `wp_enqueue_script()` |
| JS inline ligado a um handle | `wp_add_inline_script()` |
| CSS de ficheiro | `wp_register_style()` + `wp_enqueue_style()` |
| CSS inline ligado a um handle | `wp_add_inline_style()` |
| `defer` / `async` / rodapé | WP 6.3+: 5.º argumento como array — `[ 'in_footer' => true, 'strategy' => 'defer' ]` (substitui o antigo `$in_footer` booleano) |
| Outros atributos (`nonce`, `type`) | `wp_script_add_data()` ou o filtro `script_loader_tag` |
| Enqueue na página de login | `login_enqueue_scripts` |
| Enqueue no admin | `admin_enqueue_scripts` (e `admin_print_scripts`/`admin_print_styles` para casos específicos) |

```php
wp_enqueue_script(
    'acme-admin',
    plugins_url( 'js/admin.js', ACME_FILE ),
    [ 'jquery' ],
    ACME_VERSION,
    [ 'in_footer' => true, 'strategy' => 'defer' ]   // WP 6.3+
);
```

Hooks de enqueue: `wp_enqueue_scripts` (front), `admin_enqueue_scripts` (admin, recebe `$hook_suffix` — use-o para condicionar), `login_enqueue_scripts` (login). Enqueue fora destes hooks não funciona.

Pedidos AJAX vão **sempre** por `wp-admin/admin-ajax.php` (ou por um endpoint REST). Nunca aponte o pedido diretamente a um ficheiro PHP do plugin — esse ficheiro não tem o WordPress carregado nem as verificações do core.

`<script>`/`<link>` escritos à mão no `wp_head` são motivo de rejeição — quebram dependências, versionamento e plugins de otimização.

### Bibliotecas já incluídas no core

jQuery, SimplePie, PHPMailer, PHPass, Atom Lib e muitas outras vêm com o WordPress. **Incluir a sua própria cópia é motivo de rejeição** — por segurança (a cópia não recebe patches) e por estabilidade. Use `wp_enqueue_script( 'jquery' )` e companhia.

Add-ons que não estão no core podem ser incluídos, mas **só os ficheiros necessários** — não a jQuery UI inteira por causa de um componente. Se o código não funciona com a jQuery do core, o problema é quase sempre `noConflict`, não a versão.

Da mesma forma, **use a HTTP API em vez de `curl_*` no seu código** (`wp_remote_get` e afins; para opções cURL específicas existe o hook `http_api_curl`). cURL dentro de bibliotecas de terceiros é tolerado; no código próprio do plugin não.

## Ferramentas recomendadas

```bash
composer require --dev \
    wp-coding-standards/wpcs \
    phpcompatibility/phpcompatibility-wp \
    szepeviktor/phpstan-wordpress \
    php-stubs/wordpress-stubs

# Correr
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
