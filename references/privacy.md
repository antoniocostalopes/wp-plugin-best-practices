# Privacidade e Dados Pessoais

APIs de privacidade do core (WP 4.9.6+). Use quando o plugin guarda, envia ou expõe dados pessoais, ou contacta serviços externos.

Handbook: https://developer.wordpress.org/plugins/privacy/

## Conteúdo

- [Quando aplica](#quando-aplica)
- [Três obrigações](#três-obrigações)
- [1. Texto sugerido para a política de privacidade](#1-texto-sugerido-para-a-política-de-privacidade)
- [2. Exporter](#2-exporter)
- [3. Eraser](#3-eraser)
- [4. Anonimizar em vez de guardar](#4-anonimizar-em-vez-de-guardar)
- [5. Consentimento e serviços externos](#5-consentimento-e-serviços-externos)
- [6. Higiene de dados pessoais](#6-higiene-de-dados-pessoais)
- [Outros hooks e options de privacidade](#outros-hooks-e-options-de-privacidade)
- [Testar](#testar)
- [Checklist](#checklist)

## Quando aplica

Se o plugin guarda, envia ou expõe **qualquer** destes, aplica:

email, nome, IP, user agent, user ID ligado a pessoa, cookies próprios, localStorage, telemetria/analytics, pixels ou iframes de terceiros, logs com dados de request, submissões de formulário, dados de encomenda/subscrição.

"Guardo só o IP no log" **é** dado pessoal no RGPD.

## Três obrigações

| Obrigação | API | Hook |
|---|---|---|
| Declarar o que se recolhe | `wp_add_privacy_policy_content()` | `admin_init` |
| Exportar dados de uma pessoa | filtro `wp_privacy_personal_data_exporters` | `init`/`plugins_loaded` |
| Apagar dados de uma pessoa | filtro `wp_privacy_personal_data_erasers` | `init`/`plugins_loaded` |

Nomes errados comuns: `wp_privacy_personal_data_export_file` e `wp_privacy_personal_data_erase` **não** são hooks de registo (o primeiro é a ação de gerar o ficheiro, o segundo não existe como registo). Registe pelos filtros `..._exporters` / `..._erasers`.

## 1. Texto sugerido para a política de privacidade

```php
add_action( 'admin_init', function () {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return; // WP < 4.9.6
    }

    $content = sprintf(
        '<p class="privacy-policy-tutorial">%s</p><strong class="privacy-policy-tutorial">%s</strong> %s',
        esc_html__( 'Sugestão: adapte este texto ao uso real do plugin.', 'acme-widgets' ),
        esc_html__( 'Acme Widgets', 'acme-widgets' ),
        esc_html__( 'Ao submeter o formulário de contacto, guardamos o nome, email e endereço IP durante 12 meses para prevenção de spam. Estes dados não são partilhados com terceiros.', 'acme-widgets' )
    );

    wp_add_privacy_policy_content( 'Acme Widgets', wp_kses_post( wpautop( $content ) ) );
} );
```

`.privacy-policy-tutorial` marca parágrafos de instrução — não são copiados para a política publicada.

O texto deve cobrir: **que** dados, **porquê**, **onde** ficam, **quanto tempo**, **com quem** são partilhados, e **que serviços externos** são contactados.

## 2. Exporter

Callback recebe `$email_address` e `$page` (1-based) e devolve `[ 'data' => [...], 'done' => bool ]`.

```php
add_filter( 'wp_privacy_personal_data_exporters', function ( array $exporters ): array {
    $exporters['acme-widgets'] = [
        'exporter_friendly_name' => __( 'Submissões Acme Widgets', 'acme-widgets' ),
        'callback'               => 'acme_export_personal_data',
    ];
    return $exporters;
} );

/**
 * @param string $email_address Email da pessoa.
 * @param int    $page          Página 1-based.
 * @return array{data: array, done: bool}
 */
function acme_export_personal_data( string $email_address, int $page = 1 ): array {
    $per_page = 100;
    $page     = max( 1, $page );

    $rows = acme_get_submissions_by_email( $email_address, $per_page, ( $page - 1 ) * $per_page );

    $export_items = [];
    foreach ( $rows as $row ) {
        $export_items[] = [
            'group_id'    => 'acme-submissions',
            'group_label' => __( 'Submissões Acme', 'acme-widgets' ),
            'item_id'     => 'acme-submission-' . $row->id,
            'data'        => [
                [ 'name' => __( 'Nome', 'acme-widgets' ),  'value' => $row->name ],
                [ 'name' => __( 'Email', 'acme-widgets' ), 'value' => $row->email ],
                [ 'name' => __( 'IP', 'acme-widgets' ),    'value' => $row->ip ],
                [ 'name' => __( 'Data', 'acme-widgets' ),  'value' => $row->created_at ],
            ],
        ];
    }

    return [
        'data' => $export_items,
        'done' => count( $rows ) < $per_page, // false => core chama outra vez com $page+1
    ];
}
```

**Paginação é obrigatória.** Devolver `done => true` sempre com um `SELECT *` sem limite rebenta em memória com volume real.

## 3. Eraser

Devolve `[ 'items_removed' => bool, 'items_retained' => bool, 'messages' => string[], 'done' => bool ]`.

```php
add_filter( 'wp_privacy_personal_data_erasers', function ( array $erasers ): array {
    $erasers['acme-widgets'] = [
        'eraser_friendly_name' => __( 'Submissões Acme Widgets', 'acme-widgets' ),
        'callback'             => 'acme_erase_personal_data',
    ];
    return $erasers;
} );

function acme_erase_personal_data( string $email_address, int $page = 1 ): array {
    $per_page = 100;
    $rows     = acme_get_submissions_by_email( $email_address, $per_page, 0 );

    $removed  = false;
    $retained = false;
    $messages = [];

    foreach ( $rows as $row ) {
        if ( acme_is_legally_required( $row ) ) {
            // Ex.: fatura — obrigação fiscal sobrepõe-se ao pedido de apagamento.
            $retained   = true;
            $messages[] = __( 'Registos de faturação mantidos por obrigação legal (10 anos).', 'acme-widgets' );
            continue;
        }
        acme_delete_submission( $row->id );
        $removed = true;
    }

    return [
        'items_removed'  => $removed,
        'items_retained' => $retained,
        'messages'       => array_unique( $messages ),
        'done'           => count( $rows ) < $per_page,
    ];
}
```

Se retiver dados, **tem de** explicar porquê em `messages` — aparece ao admin no ecrã Erase Personal Data.

Alternativa a apagar: anonimizar. `wp_privacy_anonymize_data( 'ip', $ip )` e `wp_privacy_anonymize_ip( $ip )` fazem-no de forma consistente com o core.

## 4. Anonimizar em vez de guardar

```php
// Guardar IP anonimizado desde a origem, quando o IP exato não é necessário.
$ip = wp_privacy_anonymize_ip( $_SERVER['REMOTE_ADDR'] ?? '' ); // 192.168.1.42 -> 192.168.1.0
```

Tipos suportados por `wp_privacy_anonymize_data()`: `email`, `url`, `ip`, `date`, `text`, `longtext`.

## 5. Consentimento e serviços externos

Guideline #7 do WP.org: **contactar servidor externo ou recolher dados exige opt-in explícito.** Não vale:

- opt-in ativo por omissão;
- "ao usar o plugin aceita...";
- telemetria ligada logo na ativação.

Padrão correto: option `acme_telemetry_optin` a `false`, checkbox no settings, e todo o envio dentro de `if ( get_option( 'acme_telemetry_optin' ) )`.

O mesmo vale para carregar fontes, scripts, iframes ou pixels de domínios terceiros no front-end — é transferência de IP para terceiros.

## 6. Higiene de dados pessoais

- **Logs**: nunca `error_log( print_r( $_POST, true ) )` — apanha emails, passwords, tokens. Logue IDs.
- **Retenção**: defina prazo e apague com cron. Dados eternos são risco sem valor.
- **REST/front**: `show_in_rest` em meta com dado pessoal expõe publicamente — ver `references/security.md`.
- **Uninstall**: apague tabelas, options, meta e ficheiros com dados pessoais (ver `references/scaffolding.md`).
- **Acesso**: dados pessoais no admin atrás de capability adequada, não `read` (ver `references/capabilities.md`).
- **Cookies**: documente nome, finalidade e duração. Cookie próprio antes de consentimento é problema.

## Outros hooks e options de privacidade

https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/

| Hook / option | Para quê |
|---|---|
| `wp_page_for_privacy_policy` (option) | ID da página de política de privacidade do site |
| `privacy_policy_url` / `the_privacy_policy_link` (filtros) | Alterar o URL ou o HTML do link da política |
| `wp_get_default_privacy_policy_content` (filtro) | Alterar o conteúdo sugerido no guia de política |
| `wp_privacy_additional_user_profile_data` (filtro) | Acrescentar dados do perfil ao exportador **do core** — mais simples que registar exportador próprio para campos de utilizador |
| `wp_privacy_personal_data_erased` (ação) | Corre depois da última página do último eraser — bom sítio para limpar caches |
| `wp_privacy_personal_data_export_file` / `_export_file_created` | Geração do ficheiro de exportação |
| `wp_privacy_export_expiration` (filtro) | Validade dos ficheiros de exportação (default 3 dias) |
| `wp_privacy_delete_old_export_files` (ação agendada) | Limpeza automática dos exports antigos |
| `user_request_action_confirmed` (ação) | O utilizador confirmou o pedido por email — dispare aqui o seu processamento |
| `user_request_key_expiration` (filtro) | Validade da chave de confirmação |
| `user_request_*_email_content/_headers/_subject/_to` | Personalizar os emails de pedido e de confirmação |

Se só precisa de expor campos de perfil já existentes, `wp_privacy_additional_user_profile_data` evita escrever um exportador completo.

## Testar

Admin → Ferramentas → **Export Personal Data** / **Erase Personal Data**. Introduza um email com dados do plugin e confirme:

1. O grupo do plugin aparece no zip exportado.
2. Paginação corre até ao fim sem timeout com >100 registos.
3. O eraser apaga e a mensagem de retenção (se houver) aparece.

## Checklist

- [ ] `wp_add_privacy_policy_content()` registado no `admin_init` se há recolha de dados
- [ ] Exporter registado no filtro `wp_privacy_personal_data_exporters`, paginado
- [ ] Eraser registado no filtro `wp_privacy_personal_data_erasers`, com `messages` ao reter
- [ ] IP anonimizado quando o valor exato não é preciso
- [ ] Zero dados pessoais em logs
- [ ] Serviços externos/telemetria com opt-in explícito (default off)
- [ ] Prazo de retenção definido e aplicado
- [ ] Uninstall apaga dados pessoais
- [ ] Cookies documentados no readme e na política
