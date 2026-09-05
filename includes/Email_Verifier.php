<?php
/**
 * Convoca Members
 *
 * @package    Convoca\Members
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Convoca\Members\Providers\Email_Verifier_Provider;
use Convoca\Members\Providers\WPMail_Provider;
use Convoca\Members\Providers\Mailgun_Provider;

/**
 * Email sending registry (provider pluggable).
 *
 * The configured provider wins; otherwise the first available provider
 * (default: wp_mail). Providers can be extended via the
 * 'convoca_email_providers' filter.
 */
class Email_Verifier {

	/**
	 * Registered provider instances.
	 *
	 * @var Email_Verifier_Provider[]|null
	 */
	private static ?array $providers = null;

	/**
	 * Get all registered providers.
	 *
	 * @return Email_Verifier_Provider[]
	 */
	public static function get_providers(): array {
		if ( null === self::$providers ) {
			self::$providers = array(
				new WPMail_Provider(),
				new Mailgun_Provider(),
			);
			self::$providers = apply_filters( 'convoca_email_providers', self::$providers );
		}
		return self::$providers;
	}

	/**
	 * Get the active provider.
	 *
	 * Honors settings['email_provider'] if set and available;
	 * otherwise first available provider (wp_mail always available).
	 */
	public static function get_active_provider(): Email_Verifier_Provider {
		$settings   = get_option( 'convoca_members_settings', array() );
		$configured = $settings['email_provider'] ?? '';

		foreach ( self::get_providers() as $provider ) {
			if ( $configured && $provider->get_slug() === $configured && $provider->is_available() ) {
				return $provider;
			}
		}
		foreach ( self::get_providers() as $provider ) {
			if ( $provider->is_available() ) {
				return $provider;
			}
		}

		// Safety net: always return wp_mail.
		return new WPMail_Provider();
	}

	/**
	 * Send an email through the active provider.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Subject.
	 * @param string $body    HTML body.
	 * @param array  $headers Headers (associative).
	 * @return bool
	 */
	public static function send( string $to, string $subject, string $body, array $headers = array() ): bool {
		return self::get_active_provider()->send( $to, $subject, $body, $headers );
	}
}
