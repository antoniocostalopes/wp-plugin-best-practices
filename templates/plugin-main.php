<?php
/**
 * Plugin Name:       {{PLUGIN_NAME}}
 * Plugin URI:        {{PLUGIN_URI}}
 * Description:       {{PLUGIN_DESCRIPTION}}
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            {{AUTHOR_NAME}}
 * Author URI:        {{AUTHOR_URI}}
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       {{TEXT_DOMAIN}}
 * Domain Path:       /languages
 * Update URI:        false
 *
 * Headers opcionais (descomente conforme aplicável):
 *   Network: true                    — Plugin network-only (multisite).
 *   Requires Plugins: woocommerce    — WP 6.5+. Lista slugs do WP.org separados por vírgula.
 *
 * @package {{NAMESPACE}}
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Constantes do plugin.
 *
 * Substitua os placeholders {{...}}:
 * - {{CONST_PREFIX}}  → ex: ACME_WIDGETS
 * - {{NAMESPACE}}     → ex: Acme\Widgets
 * - {{TEXT_DOMAIN}}   → ex: acme-widgets
 */
define( '{{CONST_PREFIX}}_VERSION', '1.0.0' );
define( '{{CONST_PREFIX}}_FILE', __FILE__ );
define( '{{CONST_PREFIX}}_DIR', plugin_dir_path( __FILE__ ) );
define( '{{CONST_PREFIX}}_URL', plugin_dir_url( __FILE__ ) );
define( '{{CONST_PREFIX}}_BASENAME', plugin_basename( __FILE__ ) );
define( '{{CONST_PREFIX}}_MIN_PHP', '8.0' );
define( '{{CONST_PREFIX}}_MIN_WP', '6.2' );

/**
 * Verifica requisitos antes de carregar.
 */
if ( version_compare( PHP_VERSION, {{CONST_PREFIX}}_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( '{{PLUGIN_NAME}} requires PHP %s or higher.', '{{TEXT_DOMAIN}}' ),
						{{CONST_PREFIX}}_MIN_PHP
					)
				)
			);
		}
	);
	return;
}

/**
 * Autoloader PSR-4 manual (caso não use Composer).
 * Se usar Composer, substitua por: require __DIR__ . '/vendor/autoload.php';
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = '{{NAMESPACE}}\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
		$file     = {{CONST_PREFIX}}_DIR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Hooks de ciclo de vida.
 */
register_activation_hook( __FILE__, [ '{{NAMESPACE}}\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '{{NAMESPACE}}\\Plugin', 'deactivate' ] );

/**
 * Bootstrap.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		\{{NAMESPACE}}\Plugin::instance()->run();
	}
);
