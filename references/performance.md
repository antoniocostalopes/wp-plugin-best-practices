# Performance em Plugins WordPress

Referência detalhada de otimização. Use ao auditar performance, implementar queries pesadas, lidar com tráfego alto, ou diagnosticar lentidão.

## Modelo mental

Cada hit ao WP carrega: core (~400 arquivos), tema, plugins ativos. Cada plugin **deve** custar o mínimo possível em cada request, especialmente em hooks que rodam sempre (`init`, `wp_loaded`, `plugins_loaded`).

Métricas que importam:

1. **Time to First Byte (TTFB)** — afetado por PHP + queries
2. **Queries por request** — alvo: <50 em página típica
3. **Memory peak** — alvo: <128MB
4. **Page Speed / LCP** — afetado por assets enfileirados

## 1. Queries — o maior culpado

### Use as APIs corretas

```php
// Errado — query manual
$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_status='publish'" );

// Certo — WP_Query (com cache, filtros, hooks)
$query = new WP_Query( [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 10,
    'no_found_rows'  => true,           // pula SQL_CALC_FOUND_ROWS se não pagina
    'update_post_term_cache' => false,  // pula se não usa termos
    'update_post_meta_cache' => false,  // pula se não usa meta
    'fields'         => 'ids',          // só IDs se for tudo que precisa
] );
```

Otimizações de `WP_Query` que poucos usam:

| Argumento | Quando |
|---|---|
| `no_found_rows => true` | Quando não precisa paginar (não chama `SQL_CALC_FOUND_ROWS`) |
| `update_post_term_cache => false` | Quando não acessa categorias/tags |
| `update_post_meta_cache => false` | Quando não acessa post meta |
| `fields => 'ids'` | Quando só precisa dos IDs |
| `cache_results => false` | Raro, só em loops grandes onde memória importa |
| `posts_per_page => -1` | **Evitar** — sem limite, pode travar |

### Evite queries em loops

```php
// Errado — N+1 queries
foreach ( $post_ids as $id ) {
    $title = get_the_title( $id );  // cada chamada pode trigger query
}

// Certo — bulk fetch
_prime_post_caches( $post_ids, false, false );
foreach ( $post_ids as $id ) {
    $title = get_the_title( $id );  // agora cache hit
}
```

Para meta em loop, use `update_meta_cache()`:

```php
update_meta_cache( 'post', $post_ids );
foreach ( $post_ids as $id ) {
    $value = get_post_meta( $id, 'my_key', true );  // cache hit
}
```

### Meta queries são caras

`meta_query` faz JOINs e não é indexada. Em escala, considere:

1. **Custom taxonomy** em vez de meta (filtragem é muito mais rápida)
2. **Custom table** para dados estruturados
3. **Index manual** na meta_key se mantém schema próprio

Se usar `meta_query`, **sempre** combine com `tax_query` ou outro filtro primário para reduzir o set antes do JOIN.

## 2. Caching

### Object Cache (curta duração, in-memory)

```php
$key = 'acme_top_posts_' . $category_id;
$posts = wp_cache_get( $key, 'acme' );

if ( false === $posts ) {
    $posts = expensive_query();
    wp_cache_set( $key, $posts, 'acme', HOUR_IN_SECONDS );
}
```

**Importante**: object cache **não persiste** entre requests sem um drop-in (Redis, Memcached). Em sites sem cache persistente, prefira **transients**.

### Transients (persistente)

```php
$key = 'acme_feed_' . md5( $url );
$data = get_transient( $key );

if ( false === $data ) {
    $data = wp_remote_get( $url );
    set_transient( $key, $data, 12 * HOUR_IN_SECONDS );
}
```

Constantes de tempo úteis: `MINUTE_IN_SECONDS`, `HOUR_IN_SECONDS`, `DAY_IN_SECONDS`, `WEEK_IN_SECONDS`, `MONTH_IN_SECONDS`, `YEAR_IN_SECONDS`.

**Cuidados com transients:**

- Sem object cache persistente, transients são gravados em `wp_options` — não armazene MBs
- Sempre limpe ao invalidar: `delete_transient( $key )`
- Use site_transients para multisite global: `set_site_transient()`

### Invalidação

Hooks comuns para invalidar cache:

```php
add_action( 'save_post', 'acme_clear_feed_cache' );
add_action( 'deleted_post', 'acme_clear_feed_cache' );
add_action( 'updated_post_meta', 'acme_clear_feed_cache' );
```

## 3. Options API

`get_option()` é cacheado em memória dentro do mesmo request — barato após o primeiro hit. Mas tem armadilha: **autoload**.

Toda option com `autoload=yes` é carregada **em toda request** via single query `SELECT option_name, option_value FROM wp_options WHERE autoload='yes'`. Se você guarda 5MB de JSON aí, todo hit paga isso.

```php
// Option grande ou raramente acessada — autoload=no
add_option( 'acme_big_data', $data, '', 'no' );

// Update mantém o autoload existente, então setar na criação
update_option( 'acme_big_data', $data, false );  // false = autoload=no
```

Audite com:

```sql
SELECT option_name, LENGTH(option_value) AS size
FROM wp_options
WHERE autoload = 'yes'
ORDER BY size DESC
LIMIT 20;
```

## 4. Hooks — escolha o certo

| Hook | Dispara | Use para |
|---|---|---|
| `plugins_loaded` | Após todos plugins carregarem | Inicialização leve, registrar classes |
| `init` | Após plugins+tema carregarem | Registrar CPT, taxonomies, shortcodes, traduções |
| `wp_loaded` | Após init, antes do query | Raro |
| `template_redirect` | Antes do template carregar | Redirects, headers customizados |
| `wp_enqueue_scripts` | Front-end enqueue | Scripts/styles do front |
| `admin_enqueue_scripts` | Admin enqueue | Scripts/styles do admin |
| `admin_init` | Toda página admin | Processar forms, registrar settings |
| `wp_footer` / `wp_head` | Render | Output inline (último recurso) |
| `shutdown` | Pós-response | Logging, cleanup |

**Antipadrão**: rodar lógica pesada em `init` ou `plugins_loaded` em **toda** request. Se o código só faz sentido no admin, hookeie em `admin_init`. Se só em REST, em `rest_api_init`. Se só em uma URL específica, condicione:

```php
add_action( 'init', function() {
    if ( ! is_admin() ) return;
    // ...
} );
```

Melhor ainda, hook apenas no contexto certo:

```php
add_action( 'admin_init', 'acme_admin_setup' );  // só admin
add_action( 'rest_api_init', 'acme_rest_routes' );  // só REST
```

## 5. Enqueue de scripts/styles

### Sempre use `wp_enqueue_script` / `wp_enqueue_style`

Nunca emita `<script>` ou `<link>` diretamente em `wp_head` — você quebra cache, dependências, deferring.

```php
add_action( 'wp_enqueue_scripts', function() {
    // Só enfileira onde precisa
    if ( ! is_singular( 'product' ) ) {
        return;
    }

    wp_enqueue_script(
        'acme-product',
        plugins_url( 'assets/product.js', __FILE__ ),
        [ 'jquery' ],
        ACME_VERSION,
        true  // footer
    );

    wp_enqueue_style(
        'acme-product',
        plugins_url( 'assets/product.css', __FILE__ ),
        [],
        ACME_VERSION
    );
} );
```

### Otimizações modernas

```php
// Defer / async (WP 6.3+)
wp_enqueue_script( 'acme', $src, [], ACME_VERSION, [
    'strategy'  => 'defer',  // ou 'async'
    'in_footer' => true,
] );

// Pass data ao JS sem inline echo
wp_localize_script( 'acme', 'acmeData', [
    'apiUrl' => esc_url_raw( rest_url( 'acme/v1' ) ),
    'nonce'  => wp_create_nonce( 'wp_rest' ),
] );

// Module scripts (WP 6.5+)
wp_enqueue_script_module( 'acme/main', $src, [], ACME_VERSION );
```

### Versionamento

Sempre passe versão para cache-busting:

```php
define( 'ACME_VERSION', '1.2.3' );

// Em dev, force reload
$ver = defined( 'WP_DEBUG' ) && WP_DEBUG
    ? filemtime( plugin_dir_path( __FILE__ ) . 'assets/product.js' )
    : ACME_VERSION;
```

### Não enfileire jQuery se não precisa

`wp_enqueue_script( 'meu-script', ..., [ 'jquery' ] )` força jQuery no front. Use vanilla JS quando possível.

## 6. AJAX e REST — escolha certa

**AJAX legacy (`admin-ajax.php`):**

- Bloqueia (não cacheável)
- Cada hit carrega WP inteiro
- Use só para handlers admin simples ou compatibilidade

**REST API:**

- Pode ser cacheada por CDN/proxy em endpoints `GET`
- Mais limpa, padrão moderno
- Use para tudo novo

```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'acme/v1', '/items', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'acme_get_items',
        'permission_callback' => '__return_true',
        'args'                => [
            'per_page' => [
                'default'           => 10,
                'sanitize_callback' => 'absint',
                'validate_callback' => fn( $v ) => $v > 0 && $v <= 100,
            ],
        ],
    ] );
} );
```

## 7. Tarefas pesadas — não bloqueie request

Para jobs que demoram (envio de email em massa, processamento de imagens, sync de API):

### WP-Cron

```php
// Agendar
if ( ! wp_next_scheduled( 'acme_sync_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'acme_sync_event' );
}

// Handler
add_action( 'acme_sync_event', 'acme_run_sync' );

// Cleanup no deactivate
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'acme_sync_event' );
} );
```

**Cuidado**: WP-Cron roda em request real do usuário (a menos que `DISABLE_WP_CRON` + cron real). Não é pontual nem garantido.

### Action Scheduler

Para volumes grandes (>50 jobs/min), use [Action Scheduler](https://actionscheduler.org/) (vem com WooCommerce). Tem fila, retry, batch processing.

```php
as_schedule_single_action( time(), 'acme_process_item', [ $item_id ] );
```

## 8. Imagens e mídia

- Use `wp_get_attachment_image()` (gera srcset, lazyload, sizes) em vez de `<img>` manual
- Para imagens custom (não-attachment), inclua `width`, `height`, `loading="lazy"` (WP faz automaticamente para attachments)
- Gere thumbnails sob demanda com `add_image_size()` apenas se for usar — cada tamanho ocupa disco

## 9. Database — schema próprio

Se criar tabela custom:

```php
register_activation_hook( __FILE__, 'acme_install' );

function acme_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'acme_log';

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        action varchar(64) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    add_option( 'acme_db_version', '1.0' );
}
```

**Sempre indexe** colunas usadas em WHERE/JOIN/ORDER BY. Sempre versione o schema (`acme_db_version`) para fazer migrations.

## 10. Profiling

Ferramentas obrigatórias durante dev:

- **Query Monitor** (plugin) — queries, hooks, HTTP requests, AJAX, REST
- **Debug Bar** + add-ons
- **`SAVEQUERIES`** em `wp-config.php`:

```php
define( 'SAVEQUERIES', true );
// Depois: print_r( $wpdb->queries );
```

- **New Relic / Blackfire** para produção

## Checklist de auditoria de performance

- [ ] Nenhuma query em loop sem `_prime_post_caches` ou `update_meta_cache`
- [ ] `WP_Query` usa `no_found_rows` quando não pagina
- [ ] `posts_per_page` tem limite (nunca -1 em produção)
- [ ] Options grandes têm `autoload=no`
- [ ] Hooks pesados condicionados por contexto (admin/front/REST/CLI)
- [ ] Scripts/styles enfileirados só onde usados
- [ ] Versionamento em assets para cache-busting
- [ ] Sem jQuery se não precisa
- [ ] REST com cache de transient onde aplicável
- [ ] Jobs pesados em wp-cron ou action scheduler, não inline
- [ ] Tabelas custom têm índices em colunas filtradas
- [ ] Sem `meta_query` standalone (sempre combinado)
- [ ] HTTP externo com `timeout` curto e cache
- [ ] Sem `file_get_contents()` em URLs
