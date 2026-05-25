<?php
/**
 * Main plugin class.
 *
 * @package {{NAMESPACE}}
 */

declare( strict_types=1 );

namespace {{NAMESPACE}};

defined( 'ABSPATH' ) || exit;

/**
 * Singleton container do plugin.
 */
final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	private function __construct() {}

	public function __clone() {
		throw new \LogicException( 'Cannot clone singleton.' );
	}

	public function __wakeup() {
		throw new \LogicException( 'Cannot unserialize singleton.' );
	}

	/**
	 * Inicialização. Chamado em `plugins_loaded`.
	 */
	public function run(): void {
		$this->load_textdomain();
		$this->register_services();
	}

	private function load_textdomain(): void {
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain(
					'{{TEXT_DOMAIN}}',
					false,
					dirname( {{CONST_PREFIX}}_BASENAME ) . '/languages'
				);
			}
		);
	}

	private function register_services(): void {
		// Front-end.
		( new Frontend\Enqueue() )->register();

		// Admin.
		if ( is_admin() ) {
			( new Admin\Menu() )->register();
			( new Admin\Settings() )->register();
		}

		// REST API.
		( new REST\Controller() )->register();

		// Custom Post Types / Taxonomies.
		( new PostTypes\Widget() )->register();
	}

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, {{CONST_PREFIX}}_MIN_PHP, '<' ) ) {
			deactivate_plugins( {{CONST_PREFIX}}_BASENAME );
			wp_die(
				esc_html__( 'PHP version incompatible.', '{{TEXT_DOMAIN}}' ),
				esc_html__( 'Plugin activation failed', '{{TEXT_DOMAIN}}' ),
				[ 'back_link' => true ]
			);
		}

		// Schema (criar/migrar tabelas).
		// ( new Database\Installer() )->install();

		// Defaults.
		if ( false === get_option( '{{OPTION_PREFIX}}_settings' ) ) {
			add_option(
				'{{OPTION_PREFIX}}_settings',
				self::default_settings(),
				'',
				'no'
			);
		}

		// Capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( '{{CAPABILITY}}' );
		}

		// CPT precisam ser registrados em `init`, então só damos flush aqui.
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		// Limpar cron.
		$hooks = [
			'{{OPTION_PREFIX}}_sync_event',
			'{{OPTION_PREFIX}}_cleanup_event',
		];
		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}

		flush_rewrite_rules();
	}

	private static function default_settings(): array {
		return [
			'enabled'                   => true,
			'delete_data_on_uninstall'  => false,
		];
	}
}
