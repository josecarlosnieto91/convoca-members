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
 * WhatsApp phone verification provider (stub).
 *
 * WhatsApp requires the Meta WhatsApp Business API: a verified business
 * account, an app on developers.facebook.com, and a webhook configured.
 * It is intentionally unavailable until those credentials are provided.
 *
 * When enabled, the flow would mirror Telegram: the member shares their
 * phone with the business number, and the webhook matches the number.
 */
class WhatsApp_Provider implements Phone_Verifier_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'whatsapp';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Verificar con WhatsApp', 'convoca-members' );
	}

	/**
	 * WhatsApp Business API credentials are not configured by default.
	 *
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		$settings = get_option( 'convoca_members_settings', array() );
		return ! empty( $settings['whatsapp_token'] ) && ! empty( $settings['whatsapp_phone_id'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_fields(): array {
		return array(
			'whatsapp_token'    => array(
				'label' => __( 'Token de WhatsApp Business API', 'convoca-members' ),
				'desc'  => __( 'Token permanente de la API de Meta (requiere cuenta Business verificada).', 'convoca-members' ),
				'type'  => 'password',
			),
			'whatsapp_phone_id' => array(
				'label' => __( 'ID del teléfono de negocio', 'convoca-members' ),
				'desc'  => __( 'Phone number ID de la app de Meta.', 'convoca-members' ),
			),
			'whatsapp_verify_token' => array(
				'label' => __( 'Verify token del webhook', 'convoca-members' ),
				'desc'  => __( 'El que configures en el webhook de Meta.', 'convoca-members' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function request_verification( int $member_id ): array {
		return array(
			'instructions' => __( 'WhatsApp requiere la API Business de Meta (cuenta verificada). Configura las credenciales en los ajustes del plugin para activarlo.', 'convoca-members' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle_callback( array $payload ): array {
		// WhatsApp Cloud API webhook: {"entry": [{"changes": [{"value": {"contacts": [...]}}]}]}
		// Placeholder — implement when credentials are configured.
		return array( 'success' => false, 'message' => 'not_configured' );
	}
}
