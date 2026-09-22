# Roles e Capabilities

Referência de controlo de acesso. Use ao decidir quem pode fazer o quê, criar capabilities próprias ou auditar verificações de permissão.

Handbook: https://developer.wordpress.org/plugins/users/roles-and-capabilities/

## Conteúdo

- [Modelo mental](#modelo-mental)
- [Roles default](#roles-default)
- [Primitive caps vs meta caps](#primitive-caps-vs-meta-caps)
- [Criar caps custom — na ativação, nunca no `init`](#criar-caps-custom--na-ativação-nunca-no-init)
- [Role custom](#role-custom)
- [CPT com caps próprias](#cpt-com-caps-próprias)
- [Filtro `map_meta_cap` — regras de negócio](#filtro-map_meta_cap--regras-de-negócio)
- [`current_user_can` vs `user_can`](#current_user_can-vs-user_can)
- [Caps por contexto](#caps-por-contexto)
- [Erros comuns](#erros-comuns)
- [Checklist](#checklist)

## Modelo mental

WordPress **não** tem permissões por role no código — tem **capabilities**. Role é só um saco de caps guardado na tabela `options` (`wp_user_roles`).

Regra: **nunca** verifique role (`in_array( 'editor', $user->roles )`). Verifique **capability** (`current_user_can( 'edit_others_posts' )`). Role muda, cap é contrato.

## Roles default

| Role | Caps-chave | Uso |
|---|---|---|
| Super Admin | `manage_network`, `manage_sites` | Só multisite |
| Administrator | `manage_options`, `activate_plugins`, `edit_users`, `unfiltered_html` | Dono do site |
| Editor | `edit_others_posts`, `publish_posts`, `moderate_comments` | Gere conteúdo alheio |
| Author | `publish_posts`, `upload_files`, `edit_published_posts` | Publica o próprio |
| Contributor | `edit_posts` (sem `publish_posts`, sem `upload_files`) | Submete para revisão |
| Subscriber | `read` | Só lê |

Em multisite, `unfiltered_html` é retirada de Admin/Editor (só Super Admin tem).

## Primitive caps vs meta caps

| Tipo | Exemplo | Como se usa |
|---|---|---|
| **Primitive** | `edit_posts`, `manage_options` | Guardada na role. `current_user_can( 'edit_posts' )` |
| **Meta** | `edit_post`, `delete_post`, `read_post` | **Nunca** guardada. Resolvida em runtime por `map_meta_cap()` contra um objeto: `current_user_can( 'edit_post', $post_id )` |

Erro clássico:

```php
// ERRADO — verifica se pode editar posts em geral, não ESTE post
if ( current_user_can( 'edit_posts' ) ) {
    wp_update_post( [ 'ID' => $post_id, 'post_title' => $title ] );
}

// CERTO — meta cap com o objeto
if ( current_user_can( 'edit_post', $post_id ) ) {
    wp_update_post( [ 'ID' => $post_id, 'post_title' => $title ] );
}
```

Vale o mesmo para `edit_term`/`delete_term` (+ term_id), `edit_user`/`delete_user` (+ user_id), `edit_comment` (+ comment_id).

## Criar caps custom — na ativação, nunca no `init`

`add_role()` e `add_cap()` **escrevem na base de dados**. Chamar a cada request é query de escrita em toda a página.

```php
// includes/class-capabilities.php

const CAPS_VERSION = 2; // incrementar ao mudar o mapa de caps

/**
 * Mapa único de caps. Fonte de verdade para install, upgrade e uninstall.
 */
function acme_caps_map(): array {
    return [
        'administrator' => [ 'acme_manage_settings', 'acme_view_reports', 'acme_export_data' ],
        'editor'        => [ 'acme_view_reports' ],
    ];
}

function acme_install_caps(): void {
    foreach ( acme_caps_map() as $role_name => $caps ) {
        $role = get_role( $role_name );
        if ( ! $role ) {
            continue; // role pode não existir (site personalizado)
        }
        foreach ( $caps as $cap ) {
            $role->add_cap( $cap );
        }
    }
    update_option( 'acme_caps_version', CAPS_VERSION, false );
}

function acme_remove_caps(): void {
    foreach ( acme_caps_map() as $role_name => $caps ) {
        $role = get_role( $role_name );
        if ( ! $role ) {
            continue;
        }
        foreach ( $caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
    delete_option( 'acme_caps_version' );
}

register_activation_hook( ACME_FILE, 'acme_install_caps' );
```

### Upgrade: caps novas não aparecem sem isto

`register_activation_hook` **não** corre em update do plugin. Sem guard de versão, quem atualiza fica sem as caps novas:

```php
add_action( 'admin_init', function () {
    if ( (int) get_option( 'acme_caps_version' ) !== CAPS_VERSION ) {
        acme_install_caps();
    }
} );
```

Barato: um `get_option` autoloaded, escreve só quando a versão muda.

### Uninstall

Remova as caps em `uninstall.php` (ou `register_uninstall_hook`). Caps órfãs ficam na BD para sempre e aparecem em plugins de gestão de roles.

## Role custom

```php
add_role(
    'acme_manager',
    __( 'Gestor Acme', 'acme-widgets' ),
    [
        'read'                => true,
        'upload_files'        => true,
        'acme_manage_settings' => true,
    ]
);
```

Gotchas:

- `add_role()` **não faz nada** se a role já existir. Para alterar: `remove_role()` + `add_role()`, com guard de versão.
- Nunca remova `administrator` nem `subscriber`.
- Se remover a role default de registo, atualize: `update_option( 'default_role', 'acme_manager' )`.
- Em multisite, roles são **por site**. Instalar em todos os sites exige loop sobre `get_sites()` + `switch_to_blog()` — ou usar `wp_roles()->add_role` no hook `wp_initialize_site` para sites novos.

## CPT com caps próprias

Por omissão, CPT usa as caps de `post`. Para controlo separado:

```php
register_post_type( 'acme_order', [
    'capability_type' => [ 'acme_order', 'acme_orders' ], // singular, plural
    'map_meta_cap'    => true,  // OBRIGATÓRIO — gera edit_acme_order, delete_acme_order, read_acme_order
    'capabilities'    => [
        'create_posts' => 'acme_create_orders', // só se quiser separar criação de edição
    ],
    // ...
] );
```

`map_meta_cap => true` é o que faz `current_user_can( 'edit_acme_order', $id )` funcionar (respeita autoria, estado publicado, etc.). Sem isso, as meta caps nunca resolvem e o utilizador fica bloqueado ou tudo passa.

Caps geradas (com plural `acme_orders`): `edit_acme_orders`, `edit_others_acme_orders`, `publish_acme_orders`, `read_private_acme_orders`, `delete_acme_orders`, `delete_private/published/others_acme_orders`, `edit_private/published_acme_orders`.

Todas precisam de ser atribuídas a alguma role — **inclusive ao administrator**, que não as recebe automaticamente.

## Filtro `map_meta_cap` — regras de negócio

Para lógica própria ("só edita encomendas da própria loja"):

```php
add_filter( 'map_meta_cap', function ( array $caps, string $cap, int $user_id, array $args ): array {
    if ( 'edit_acme_order' !== $cap || empty( $args[0] ) ) {
        return $caps;
    }

    $order_id = (int) $args[0];
    $store_id = (int) get_post_meta( $order_id, '_acme_store_id', true );

    if ( $store_id !== (int) get_user_meta( $user_id, 'acme_store_id', true ) ) {
        return [ 'do_not_allow' ]; // nega — nem admin passa
    }

    return $caps;
}, 10, 4 );
```

`do_not_allow` é a cap que ninguém tem. É assim que se nega em WordPress — não devolva `[]` (array vazio = permitido).

## `current_user_can` vs `user_can`

```php
current_user_can( 'acme_view_reports' );          // utilizador atual
current_user_can( 'edit_post', $post_id );        // meta cap, objeto
user_can( $user_id, 'acme_view_reports' );        // outro utilizador
user_can( $user, 'edit_post', $post_id );         // outro utilizador, meta cap
current_user_can_for_blog( $blog_id, 'edit_posts' ); // multisite, outro site
```

## Caps por contexto

| Contexto | Cap típica |
|---|---|
| Página de settings do plugin | `manage_options` |
| Menu em CPT | a cap do CPT (`edit_acme_orders`), não `manage_options` |
| Ação sobre um post | `edit_post` / `delete_post` + ID |
| Upload | `upload_files` |
| Ver dados de outro utilizador | `edit_users` ou cap custom |
| Endpoint REST público de leitura | `__return_true` — mas então não devolva dados privados |
| AJAX no admin | a cap da ação, nunca só `is_user_logged_in()` |

## Erros comuns

| Erro | Porquê é mau | Fix |
|---|---|---|
| Verificar `$user->roles` | Role é configurável; sites reais alteram-nas | `current_user_can( cap )` |
| `current_user_can( 'edit_posts' )` para editar post X | Contributor edita post alheio | `current_user_can( 'edit_post', $id )` |
| `add_role()` no `init` | Escrita na BD a cada request | Ativação + guard de versão |
| CPT com `map_meta_cap` em falta | Meta caps nunca resolvem | `'map_meta_cap' => true` |
| Caps novas sem bump de versão | Quem atualiza fica sem acesso | `acme_caps_version` + `admin_init` |
| Devolver `[]` no filtro `map_meta_cap` | Array vazio = permitido a todos | `[ 'do_not_allow' ]` |
| `manage_options` para tudo | Só admin consegue usar o plugin | Cap custom por função |
| Caps deixadas no uninstall | Lixo permanente na BD | Remover em `uninstall.php` |

## Checklist

- [ ] Zero verificações de role no código (`grep -rn "->roles" .`)
- [ ] Ações sobre objeto usam meta cap + ID
- [ ] `add_role`/`add_cap` só na ativação/upgrade, nunca em `init`
- [ ] Guard de versão de caps para upgrades
- [ ] CPT com caps próprias tem `map_meta_cap => true` e caps atribuídas ao administrator
- [ ] Negações usam `do_not_allow`
- [ ] Caps removidas no uninstall
- [ ] Multisite: caps instaladas por site, não só no atual
