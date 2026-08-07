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
 * Email sending provider contract.
 *
 * Any transport (wp_mail, SMTP, Mailgun, SendGrid, …) must implement this
 * interface. The Email_Verifier registry picks the configured provider,
 * defaulting to WPMail_Provider when no transport is selected.
 */
interface Email_Verifier_Provider {

	/**
	 * Unique provider slug (e.g. 'wpmail', 'mailgun', 'smtp').
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label.
	 */
	public function get_label(): string;

	/**
	 * Whether the provider is configured and usable.
	 */
	public function is_available(): bool;

	/**
	 * Send an email.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Subject.
	 * @param string $body    HTML body.
	 * @param array  $headers Headers (associative).
	 * @return bool True on success.
	 */
	public function send( string $to, string $subject, string $body, array $headers = array() ): bool;

	/**
	 * Admin settings fields for this provider.
	 *
	 * @return array<string, array{label: string, desc: string, type?: string}>
	 */
	public function get_settings_fields(): array;
}
