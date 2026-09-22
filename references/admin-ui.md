# UI do Admin: menus, settings, meta boxes, perfil

Referência para construir interface no wp-admin. Use ao criar páginas de definições, menus, meta boxes ou campos no perfil de utilizador.

Handbook: [Administration Menus](https://developer.wordpress.org/plugins/administration-menus/) · [Settings](https://developer.wordpress.org/plugins/settings/) · [Metadata](https://developer.wordpress.org/plugins/metadata/custom-meta-boxes/) · [Users](https://developer.wordpress.org/plugins/users/working-with-user-metadata/)

## Conteúdo

- [1. Menus de administração](#1-menus-de-administração)
- [2. Settings API](#2-settings-api)
- [3. Meta boxes](#3-meta-boxes)
- [4. Campos no perfil de utilizador](#4-campos-no-perfil-de-utilizador)
- [Checklist](#checklist)

## 1. Menus de administração

Registo **sempre** no hook `admin_menu`.

```php
add_action( 'admin_menu', function (): void {
    $hook = add_menu_page(
        __( 'Acme Widgets', 'acme-widgets' ),   // <title> da página
        __( 'Acme', 'acme-widgets' ),           // texto no menu
        'manage_options',                        // capability
        'acme-widgets',                          // slug (único)
        'acme_render_settings_page',             // callback de render
        'dashicons-screenoptions',               // ícone: dashicon, URL ou SVG data-uri
        58                                       // posição
    );

    // $hook = "toplevel_page_acme-widgets" — use-o para assets e para processar forms
    add_action( "load-{$hook}", 'acme_handle_settings_post' );
} );
```

### Submenus e os slugs dos menus do core

```php
add_submenu_page( 'tools.php', $page_title, $menu_title, 'manage_options', 'acme-tools', $callback );
```

Helpers que poupam decorar o `$parent_slug`:

| Helper | Pai |
|---|---|
| `add_dashboard_page()` | `index.php` |
| `add_posts_page()` | `edit.php` |
| `add_media_page()` | `upload.php` |
| `add_pages_page()` | `edit.php?post_type=page` |
| `add_comments_page()` | `edit-comments.php` |
| `add_theme_page()` | `themes.php` |
| `add_plugins_page()` | `plugins.php` |
| `add_users_page()` | `users.php` |
| `add_management_page()` | `tools.php` |
| `add_options_page()` | `options-general.php` |
| CPT próprio | `edit.php?post_type=acme_order` |

Para um CPT com capabilities próprias, a cap do submenu deve ser a do CPT (`edit_acme_orders`), não `manage_options` — ver `references/capabilities.md`.

### `remove_menu_page()` não é controlo de acesso

```php
add_action( 'admin_menu', fn() => remove_menu_page( 'tools.php' ), 99 );
```

Esconde o item; **não impede** o acesso direto a `/wp-admin/tools.php`. Prioridade alta (99) porque o menu tem de já estar registado. Restrição real faz-se com capabilities.

### Processar formulários: o callback corre tarde demais

O `$function` de `add_menu_page` só corre no momento de imprimir a página — headers já foram enviados, `wp_redirect()` falha. Use o `$hookname` devolvido:

```php
$hook = add_submenu_page( ... );
add_action( "load-{$hook}", 'acme_handle_settings_post' ); // corre antes de qualquer output
```

O mesmo `$hookname` serve para carregar assets só nesta página:

```php
add_action( 'admin_enqueue_scripts', function ( string $hook_suffix ) use ( $hook ): void {
    if ( $hook_suffix !== $hook ) {
        return;
    }
    wp_enqueue_script( 'acme-admin', /* ... */ );
} );
```

Em página própria (fora da Settings API), o `action` do form aponta para a própria página e **você** trata nonce, capability e sanitização:

```html
<form action="<?php menu_page_url( 'acme-widgets' ); ?>" method="post">
```

## 2. Settings API

Porque usar em vez de página à mão:

- O form faz POST para `wp-admin/options.php`, que **verifica `manage_options`** (e Super Admin em multisite) antes de gravar — verificação que teria de escrever e manter.
- Nonces, retrieval e storage dos `$_POST` tratados pelo core.
- Aspeto consistente com o resto do admin e à prova de updates.

### Funções

| Grupo | Funções |
|---|---|
| Registo | `register_setting()`, `unregister_setting()` |
| Estrutura | `add_settings_section()`, `add_settings_field()` |
| Render no form | `settings_fields()`, `do_settings_sections()`, `do_settings_fields()` |
| Erros e avisos | `add_settings_error()`, `get_settings_errors()`, `settings_errors()` |

Tudo registado no hook `admin_init`.

### Exemplo completo

```php
add_action( 'admin_init', function (): void {
    register_setting( 'acme_options', 'acme_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
        'show_in_rest'      => false,   // chave de API nunca no REST
    ] );

    add_settings_section(
        'acme_section_api',
        __( 'Ligação à API', 'acme-widgets' ),
        function (): void {
            echo '<p>' . esc_html__( 'Credenciais do serviço Acme.', 'acme-widgets' ) . '</p>';
        },
        'acme-widgets'        // slug da PÁGINA
    );

    add_settings_field(
        'acme_api_key',
        __( 'Chave de API', 'acme-widgets' ),
        'acme_field_api_key',
        'acme-widgets',       // página
        'acme_section_api'    // secção
    );
} );

function acme_field_api_key(): void {
    $value = get_option( 'acme_api_key', '' );
    printf(
        '<input type="text" class="regular-text" name="acme_api_key" value="%s">',
        esc_attr( $value )
    );
}

function acme_render_settings_page(): void {
    // Verificação redundante com options.php, mas obrigatória: quem vê a página pode não poder gravar
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'acme_options' );        // nonce + option_page + referer
            do_settings_sections( 'acme-widgets' );   // secções e campos da PÁGINA
            submit_button( __( 'Guardar', 'acme-widgets' ) );
            ?>
        </form>
    </div>
    <?php
}
```

Pontos que falham em código real:

- `$option_group` (1.º arg de `register_setting` e de `settings_fields`) e o **slug da página** (4.º arg de `add_settings_section`) são coisas diferentes. Trocá-los faz os campos não aparecerem — sem erro nenhum.
- Toda a option registada precisa de `sanitize_callback`. Sem ele, entra o que vier.
- Erros de validação comunicam-se com `add_settings_error()` dentro do `sanitize_callback`, e imprimem-se com `settings_errors()`.
- **Adicionar a uma página existente** (`'reading'`, `'general'`, `'writing'`) é preferível a criar página nova quando são duas ou três opções — menos ecrãs para o utilizador aprender.

## 3. Meta boxes

```php
add_action( 'add_meta_boxes', function (): void {
    foreach ( [ 'post', 'acme_order' ] as $screen ) {
        add_meta_box(
            'acme_box_details',                        // ID único
            __( 'Detalhes Acme', 'acme-widgets' ),     // título
            'acme_meta_box_html',                      // callback
            $screen,                                   // post type (ou array)
            'side',                                    // contexto: normal | side | advanced
            'high'                                     // prioridade: high | core | default | low
        );
    }
} );

function acme_meta_box_html( WP_Post $post ): void {
    wp_nonce_field( 'acme_save_details', 'acme_details_nonce' );

    $value = get_post_meta( $post->ID, '_acme_status', true );
    ?>
    <label for="acme_status"><?php esc_html_e( 'Estado', 'acme-widgets' ); ?></label>
    <select name="acme_status" id="acme_status">
        <option value=""><?php esc_html_e( 'Selecionar…', 'acme-widgets' ); ?></option>
        <option value="open" <?php selected( $value, 'open' ); ?>><?php esc_html_e( 'Aberto', 'acme-widgets' ); ?></option>
        <option value="done" <?php selected( $value, 'done' ); ?>><?php esc_html_e( 'Concluído', 'acme-widgets' ); ?></option>
    </select>
    <?php
}
```

Regras:

- **Não ponha botão de submit.** O HTML da meta box já está dentro do `<form>` do editor; os valores viajam no POST do Publicar/Atualizar.
- Helpers de estado: `selected()`, `checked()`, `disabled()`, `wp_readonly()`.
- Muitos campos? Várias meta boxes agrupadas por tema, em vez de uma gigante.
- `save_post` **dispara mais do que uma vez** por atualização (autosave, revisões, `wp_insert_post` de terceiros). O handler tem de ser idempotente e sair cedo.
- Se precisa de meta **e** termos já persistidos, use `wp_after_insert_post` (WP 5.6+).

Handler seguro (ordem obrigatória — detalhe em `references/security.md`):

```php
add_action( 'save_post_acme_order', function ( int $post_id ): void {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! isset( $_POST['acme_details_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acme_details_nonce'] ) ), 'acme_save_details' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $status  = isset( $_POST['acme_status'] ) ? sanitize_key( wp_unslash( $_POST['acme_status'] ) ) : '';
    $allowed = [ 'open', 'done' ];

    update_post_meta( $post_id, '_acme_status', in_array( $status, $allowed, true ) ? $status : '' );
} );
```

### Meta box vs Gutenberg

Em código novo, prefira `register_post_meta( ..., 'show_in_rest' => true )` + painel no editor de blocos (`PluginDocumentSettingPanel`). Meta boxes clássicas continuam a funcionar, mas forçam o editor a carregá-las num iframe de compatibilidade e não participam do estado do editor. Se a meta box é incompatível com o editor de blocos, declare-o com `__back_compat_meta_box` nos `callback_args`.

## 4. Campos no perfil de utilizador

A tabela `wp_users` só guarda os campos essenciais (`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name`). Tudo o resto vai para `wp_usermeta`.

| Hook | Quando |
|---|---|
| `show_user_profile` | Utilizador edita o **próprio** perfil |
| `edit_user_profile` | Utilizador edita o perfil de **outro** |
| `user_new_form` | Ecrã de criar utilizador |
| `personal_options_update` | Gravar o próprio perfil |
| `edit_user_profile_update` | Gravar perfil de outro |

São pares: registar o campo nos dois hooks de display e o save nos dois de update, senão o campo desaparece consoante quem edita.

```php
function acme_user_field( WP_User $user ): void {
    wp_nonce_field( 'acme_save_user', 'acme_user_nonce' );
    ?>
    <h3><?php esc_html_e( 'Acme', 'acme-widgets' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="acme_phone"><?php esc_html_e( 'Telefone', 'acme-widgets' ); ?></label></th>
            <td>
                <input type="text" class="regular-text" id="acme_phone" name="acme_phone"
                       value="<?php echo esc_attr( get_user_meta( $user->ID, 'acme_phone', true ) ); ?>">
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'acme_user_field' );
add_action( 'edit_user_profile', 'acme_user_field' );

function acme_save_user_field( int $user_id ): void {
    if ( ! isset( $_POST['acme_user_nonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acme_user_nonce'] ) ), 'acme_save_user' ) ) {
        return;
    }
    // Capability sobre ESTE utilizador — nunca 'edit_users' genérico
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    update_user_meta( $user_id, 'acme_phone', sanitize_text_field( wp_unslash( $_POST['acme_phone'] ?? '' ) ) );
}
add_action( 'personal_options_update', 'acme_save_user_field' );
add_action( 'edit_user_profile_update', 'acme_save_user_field' );
```

O exemplo do handbook grava sem nonce e sem sanitização — é explicitamente didático. Em produção, os quatro passos (nonce → `edit_user` com o ID → unslash → sanitize) são obrigatórios.

Registe também a user meta (`register_meta( 'user', ... )` ou `register_user_meta()` no WP 6.4+) para ter `sanitize_callback` e controlar `show_in_rest` — dado pessoal num perfil não deve ir para o REST sem pensar (ver `references/privacy.md`).

## Checklist

- [ ] Menus registados em `admin_menu`, com a capability certa (não `manage_options` para tudo)
- [ ] `remove_menu_page()` não usado como restrição de acesso
- [ ] Forms processados em `load-{$hookname}`, não no callback de render
- [ ] Assets carregados só na página certa, comparando `$hook_suffix`
- [ ] Settings API: `$option_group` e slug da página distinguidos; `sanitize_callback` em toda a option
- [ ] Página de settings verifica capability no início do callback
- [ ] Meta box sem botão de submit; handler idempotente com autosave + nonce + `edit_post` + ID
- [ ] Campos de perfil registados nos dois hooks de display e nos dois de update
- [ ] `current_user_can( 'edit_user', $user_id )` — nunca cap genérica
