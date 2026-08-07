<?php
/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Providers
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default email provider using WordPress wp_mail().
 *
 * Always available. Uses the site's configured mailer (PHP mail, SMTP
 * plugin, transactional service hooked into wp_mail, etc.).
 */
class WPMail_Provider implements Email_Verifier_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'wpmail';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'WordPress (wp_mail)', 'convoca-members' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_fields(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $to, string $subject, string $body, array $headers = array() ): bool {
		// wp_mail expects string[] headers; our contract receives associative.
		$header_lines = array();
		foreach ( $headers as $key => $value ) {
			if ( is_int( $key ) ) {
				$header_lines[] = $value;
			} else {
				$header_lines[] = $key . ': ' . $value;
			}
		}
		return wp_mail( $to, $subject, $body, $header_lines );
	}
}
