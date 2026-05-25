<?php
/**
 * Uninstall handler.
 *
 * Executado quando o usuário deleta o plugin via UI do WordPress.
 * Apenas remove dados se o usuário marcou "delete data on uninstall" nas settings.
 *
 * @package {{NAMESPACE}}
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Apenas usuários com permissão devem trigger isso.
if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

/**
 * Configure abaixo os nomes específicos do plugin:
 * - {{OPTION_PREFIX}}   → ex: acme_widgets
 * - {{TABLE_NAME}}      → ex: acme_log (sem prefix do wpdb)
 * - {{POST_TYPE}}       → ex: acme_widget
 * - {{TRANSIENT_PREFIX}}→ ex: acme_widgets
 * - {{CAPABILITY}}      → ex: manage_acme_widgets
 * - {{CRON_HOOKS}}      → adicionar à lista
 */

$settings = get_option( '{{OPTION_PREFIX}}_settings', [] );

// Remoção destrutiva apenas com opt-in.
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// 1. Apagar options do plugin.
$options = [
	'{{OPTION_PREFIX}}_settings',
	'{{OPTION_PREFIX}}_db_version',
	'{{OPTION_PREFIX}}_install_date',
];
foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

// 2. Apagar transients (formato: _transient_{key}, _transient_timeout_{key}).
$prefix = $wpdb->esc_like( '_transient_{{TRANSIENT_PREFIX}}_' ) . '%';
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$prefix
	)
);
$prefix_timeout = $wpdb->esc_like( '_transient_timeout_{{TRANSIENT_PREFIX}}_' ) . '%';
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$prefix_timeout
	)
);

// 3. Apagar posts do plugin e seus meta/terms.
$posts = get_posts(
	[
		'post_type'   => '{{POST_TYPE}}',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	]
);
foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}

// 4. Apagar tabelas custom.
$table = $wpdb->prefix . '{{TABLE_NAME}}';
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

// 5. Remover capabilities customizadas.
$capabilities = [ '{{CAPABILITY}}' ];
foreach ( wp_roles()->roles as $role_name => $role_data ) {
	$role = get_role( $role_name );
	if ( ! $role ) {
		continue;
	}
	foreach ( $capabilities as $cap ) {
		$role->remove_cap( $cap );
	}
}

// 6. Limpar cron events (de novo, por garantia).
$cron_hooks = [
	'{{OPTION_PREFIX}}_sync_event',
	'{{OPTION_PREFIX}}_cleanup_event',
];
foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// 7. Limpar user meta do plugin (se houver).
delete_metadata( 'user', 0, '{{OPTION_PREFIX}}_user_pref', '', true );
