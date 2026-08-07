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
 * Phone verification provider contract.
 *
 * Any provider (Telegram, WhatsApp Business, SMS gateway, …) must
 * implement this interface. The registry picks the first available
 * provider at runtime; admins choose which one is enabled.
 */
interface Phone_Verifier_Provider {

	/**
	 * Unique provider slug (e.g. 'telegram', 'whatsapp').
	 */
	public function get_slug(): string;

	/**
	 * Human-readable label (e.g. 'Verificar con Telegram').
	 */
	public function get_label(): string;

	/**
	 * Whether the provider is configured and usable.
	 */
	public function is_available(): bool;

	/**
	 * Start a phone verification for a member.
	 *
	 * Generates a pending token and returns instructions for the member
	 * (e.g. "open the bot and share your contact").
	 *
	 * @param int $member_id Member post ID.
	 * @return array{instructions: string, deep_link?: string} UI payload.
	 */
	public function request_verification( int $member_id ): array;

	/**
	 * Handle an inbound callback from the provider (webhook / bot update).
	 *
	 * Providers that need server→provider outbound flows (e.g. SMS) may
	 * instead return null here and verify via request_verification().
	 *
	 * @param array $payload Raw inbound payload.
	 * @return array{success: bool, message?: string}
	 */
	public function handle_callback( array $payload ): array;

	/**
	 * Admin settings fields for this provider (key => label/desc).
	 *
	 * @return array<string, array{label: string, desc: string, type?: string}>
	 */
	public function get_settings_fields(): array;
}
