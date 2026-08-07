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
 * Mailgun email provider (REST API v3).
 *
 * Requires an API key + domain configured in settings. Used when the
 * site needs reliable transactional delivery independent of wp_mail.
 */
class Mailgun_Provider implements Email_Verifier_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'mailgun';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Mailgun', 'convoca-members' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		$settings = get_option( 'convoca_members_settings', array() );
		return ! empty( $settings['mailgun_api_key'] ) && ! empty( $settings['mailgun_domain'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_fields(): array {
		return array(
			'mailgun_api_key' => array(
				'label' => __( 'Mailgun API Key', 'convoca-members' ),
				'desc'  => __( 'Clave privada de Mailgun (formato key-...).', 'convoca-members' ),
				'type'  => 'password',
			),
			'mailgun_domain'  => array(
				'label' => __( 'Mailgun Domain', 'convoca-members' ),
				'desc'  => __( 'Dominio verificado en Mailgun, ej: mg.tudominio.com.', 'convoca-members' ),
			),
			'mailgun_region'  => array(
				'label' => __( 'Mailgun Region', 'convoca-members' ),
				'desc'  => __( 'api.mailgun.net (US) o api.eu.mailgun.net (EU).', 'convoca-members' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function send( string $to, string $subject, string $body, array $headers = array() ): bool {
		$settings = get_option( 'convoca_members_settings', array() );
		$api_key  = (string) ( $settings['mailgun_api_key'] ?? '' );
		$domain   = (string) ( $settings['mailgun_domain'] ?? '' );
		$region   = (string) ( $settings['mailgun_region'] ?? '' );
		$base     = ( $region === 'eu' ) ? 'https://api.eu.mailgun.net/v3' : 'https://api.mailgun.net/v3';

		if ( empty( $api_key ) || empty( $domain ) ) {
			return false;
		}

		$from = '';
		foreach ( $headers as $key => $value ) {
			if ( is_string( $key ) && strtolower( $key ) === 'from' ) {
				$from = $value;
			}
		}
		if ( empty( $from ) ) {
			$from = get_option( 'admin_email' );
		}

		$response = wp_remote_post(
			$base . '/' . $domain . '/messages',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ) ),
				'body'    => array(
					'from'       => $from,
					'to'         => $to,
					'subject'    => $subject,
					'html'       => $body,
					'text'       => wp_strip_all_tags( $body ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			\Convoca\Core\Logger::error( 'Mailgun error: ' . $response->get_error_message(), 'Members/Email' );
			return false;
		}
		return wp_remote_retrieve_response_code( $response ) === 200;
	}
}
