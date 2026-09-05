<?php
/**
 * PHPStan stubs for Convoca\Core and Convoca\Gateway classes.
 *
 * This plugin extends/uses classes provided by the separate convoca-core and
 * convoca-gateway plugins at runtime, which are NOT present in this repo's CI.
 * Signature-accurate stubs keep PHPStan able to resolve the class hierarchy
 * without depending on ../convoca-core or ../convoca-gateway.
 *
 * Signatures mirror the real implementations (read from convoca-core/includes
 * and convoca-gateway/includes) so PHPStan catches real type errors instead of
 * false "class not found" noise.
 *
 * Analysis-only: never loaded at runtime (namespace-guarded under PHPStan).
 */

namespace Convoca\Core {
	abstract class Upgrade_Manager {
		public function init(): void {}
		public function maybe_upgrade(): void {}
		public function force_version_check(): void {}
		protected function run_upgrades( string $from, string $to ): void {}
		protected function run_data_migration( callable $callback, string $description ): void {}
		protected function update_db_version( string $version ): void {}
		protected function get_saved_version(): string {
			return '';
		}
		protected function is_cached(): bool {
			return false;
		}
		protected function set_cache(): void {}
		protected function clear_cache(): void {}

		abstract protected function get_db_version(): string;
		abstract protected function get_option_name(): string;
		abstract protected function get_transient_prefix(): string;
		abstract protected function get_upgrade_callbacks(): array;
	}

	final class Logger {
		public static function log( string $message, string $level = 'info', string $context = 'General', ?int $object_id = null ): void {}
		public static function info( string $message, string $context = 'General', ?int $object_id = null ): void {}
		public static function warning( string $message, string $context = 'General', ?int $object_id = null ): void {}
		public static function error( string $message, string $context = 'General', ?int $object_id = null ): void {}
		public static function debug( string $message, string $context = 'General' ): void {}
		public static function get_logs( array $args = array() ): array {
			return array();
		}
	}

	final class Utils {
		public static function validate_dni( string $dni ): bool { return true; }
		public static function validar_dni( string $dni ): bool { return true; }
		public static function format_date( string $date, string $format = 'd/m/Y' ): string { return ''; }
		public static function escape_csv_field( $field ): string { return ''; }
		public static function generate_access_code( int $length = 8 ): string { return ''; }
		public static function do_action( string $new_hook, string $old_hook = '', ...$args ): void {}
		public static function check_rate_limit( string $action, int $max = 10, int $window = 300 ): bool { return true; }
		public static function acquire_lock( string $key, int $ttl = 60 ): bool { return true; }
		public static function release_lock( string $key ): bool { return true; }
		public static function get_branding_html( string $filter_suffix = 'common', string $css_class = '', string $style = 'color:#ffffff;margin:0;font-size:24px;' ): string { return ''; }
		public static function get_persistent_salt(): string { return ''; }
		public static function render_diagnostic_panel( array $checks, string $title = '' ): void {}
		public static function render_log_level_badge( string $level ): string { return ''; }
		public static function admin_notice( string $message, string $type = 'success' ): void {}
		public static function render_stored_notices(): void {}
		public static function is_plugin_active_safe( string $plugin ): bool { return true; }
		public static function rest_cache_get( string $key, int $ttl, callable $callback ): array { return array(); }
	}

	final class Features {
		public static function is_gateway_active(): bool {
			return class_exists( '\\Convoca\\Gateway\\Payment_Handler' ) || defined( 'CONVOCA_GATEWAY_VERSION' );
		}
		public static function is_members_active(): bool { return true; }
		public static function is_enroll_active(): bool { return true; }
		public static function is_shifts_active(): bool { return true; }
	}

	final class License_Manager {
		public static function has_pro( string $feature ): bool { return true; }
	}

	final class Webhook_Manager {
		public static function events(): array { return array(); }
		public static function get_webhooks(): array { return array(); }
		public static function get_webhook( string $id ): ?array { return null; }
		public static function add_webhook( array $data ): string { return ''; }
		public static function update_webhook( string $id, array $data ): bool { return true; }
		public static function delete_webhook( string $id ): bool { return true; }
		public static function test_webhook( string $id ): bool { return true; }
		public static function get_delivery_logs( string $webhook_id, int $limit = 20 ): array { return array(); }
		public static function clear_delivery_logs( string $webhook_id ): void {}
	}

	final class Installer {
		public static function db_init(): void {}
	}

	final class Email_Layout {
		public static function render( string $body, string $subject = '', array $opts = array() ): string { return ''; }
		public static function meta_table( array $rows ): string { return ''; }
		public static function button_html( string $url, string $text ): string { return ''; }
	}

	class Signature {
		public function __construct() {}
		public function get_last_error(): string { return ''; }
		public function get_acceptance_stamp_html( $acceptor_name, $ip, $timestamp, $content_to_hash ) { return ''; }
		public function create_hash( $content, $ip, $timestamp ) { return ''; }
		public function generate_pdf( $template_content, $data, $output_path, $options = array() ): string|false { return false; }
	}

	final class Notifications {
		public static function get_member( int $member_id, int $limit = 10 ): array { return array(); }
		public static function count_member_unread( int $member_id ): int { return 0; }
		public static function mark_member_read( int $member_id, string $notification_id ): void {}
		public static function mark_member_all_read( int $member_id ): void {}
	}
}

namespace Convoca\Gateway {
	final class Payment_Handler {
		public static function get_member_token( int $member_id ): string { return ''; }
		public static function create_payment( array $data ): array|\WP_Error { return array(); }
		public static function get_payment_link( int $pago_id, string $token = '', ?int $expires_ts = null ): string { return ''; }
	}

	final class CPT_Pago {
		public static function get_meta( int $post_id ): array { return array(); }
	}
}

namespace {
	// Funciones globales del plugin convoca-core (ausente en análisis aislado).
	if ( ! function_exists( 'convoca_export_pdf' ) ) {
		/**
		 * Export a CSV and trigger a download.
		 *
		 * @param string $title    Document title.
		 * @param array  $headers  Column headers.
		 * @param array  $rows     Data rows.
		 * @param string $filename Base filename without extension.
		 */
		function convoca_export_pdf( string $title, array $headers, array $rows, string $filename ): void {}
	}
}
