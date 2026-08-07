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
 * Telegram phone verification provider.
 *
 * Flow:
 * 1. Member enters their phone in the profile and clicks "verify".
 * 2. The plugin stores a pending token and shows the bot username + deep link.
 * 3. The member opens the bot in Telegram and shares their contact.
 * 4. Telegram sends an update to our webhook with the contact (phone_number).
 * 5. We match phone_number against the pending member's stored phone.
 * 6. On match we mark _convoca_telefono_verificado = 1 and reply to the chat.
 *
 * Settings (stored in convoca_members_settings):
 * - telegram_bot_token:  bot token from @BotFather.
 * - telegram_bot_username: public @username of the bot.
 */
class Telegram_Provider implements Phone_Verifier_Provider {

	public const META_VERIFIED = '_convoca_telefono_verificado';
	public const META_PENDING  = '_convoca_telefono_pendiente';
	public const META_TOKEN    = '_convoca_telefono_token';

	/** REST namespace for the bot webhook. */
	public const WEBHOOK_NAMESPACE = 'convoca-telegram/v1';

	/** Max seconds a pending verification stays valid. */
	public const TOKEN_TTL = HOUR_IN_SECONDS;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'telegram';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Verificar con Telegram', 'convoca-members' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		$settings = get_option( 'convoca_members_settings', array() );
		return ! empty( $settings['telegram_bot_token'] ) && ! empty( $settings['telegram_bot_username'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_fields(): array {
		return array(
			'telegram_bot_token'    => array(
				'label' => __( 'Token del bot de Telegram', 'convoca-members' ),
				'desc'  => __( 'Creado con @BotFather. Ej: 123456789:AAF...', 'convoca-members' ),
				'type'  => 'password',
			),
			'telegram_bot_username' => array(
				'label' => __( 'Usuario del bot (sin @)', 'convoca-members' ),
				'desc'  => __( 'Ej: convoca_verify_bot', 'convoca-members' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function request_verification( int $member_id ): array {
		$settings  = get_option( 'convoca_members_settings', array() );
		$bot_user  = $settings['telegram_bot_username'] ?? '';
		$telefono  = get_post_meta( $member_id, '_convoca_telefono', true );
		$token     = wp_generate_password( 24, false );

		if ( empty( $telefono ) ) {
			return array(
				'instructions' => __( 'Guarda primero tu número de teléfono en el perfil.', 'convoca-members' ),
			);
		}

		// Store pending verification (normalized digits only, no country prefix).
		$telefono_norm = $this->normalize_phone( $telefono );
		update_post_meta( $member_id, self::META_PENDING, $telefono_norm );
		update_post_meta( $member_id, self::META_TOKEN, $token );
		update_post_meta( $member_id, self::META_TOKEN . '_exp', time() + self::TOKEN_TTL );

		$deep_link = 'https://t.me/' . $bot_user;

		return array(
			'instructions' => sprintf(
				/* translators: %1$s bot username, %2$s phone */
				__( '1. Abre Telegram y busca <strong>@%1$s</strong>.<br>2. Pulsa <strong>Iniciar</strong>.<br>3. Comparte tu contacto con el bot (adjuntar → contacto).<br>4. Verificaremos que tu número <strong>%2$s</strong> coincide.', 'convoca-members' ),
				esc_html( $bot_user ),
				esc_html( $telefono )
			),
			'deep_link'    => $deep_link,
			'bot_username' => $bot_user,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle_callback( array $payload ): array {
		// Telegram update: { "message": { "chat": { "id": ... }, "contact": { "phone_number": "+34600..." } } }
		$message = $payload['message'] ?? array();
		if ( empty( $message['contact']['phone_number'] ) || empty( $message['chat']['id'] ) ) {
			// Not a contact-share update; nothing to do.
			return array( 'success' => false, 'message' => 'no_contact' );
		}

		$chat_id      = (int) $message['chat']['id'];
		$phone_clean  = $this->normalize_phone( $message['contact']['phone_number'] );
		$bot_token    = $this->get_token();

		// Find the member whose pending phone matches (try full and last-9-digits).
		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_PENDING,
						'value'   => $phone_clean,
						'compare' => '=',
					),
					array(
						'key'     => self::META_PENDING,
						'value'   => substr( $phone_clean, -9 ),
						'compare' => '=',
					),
				),
			)
		);

		if ( empty( $members ) ) {
			$this->send_telegram_message( $bot_token, $chat_id, __( 'No hay ninguna verificación pendiente para ese número. Asegúrate de haber iniciado la verificación desde tu perfil.', 'convoca-members' ) );
			return array( 'success' => false, 'message' => 'no_pending' );
		}

		$member_id = (int) $members[0];
		$stored    = get_post_meta( $member_id, self::META_TOKEN, true );
		$exp       = (int) get_post_meta( $member_id, self::META_TOKEN . '_exp', true );

		if ( empty( $stored ) || time() > $exp ) {
			$this->send_telegram_message( $bot_token, $chat_id, __( 'La verificación ha caducado. Iníciala de nuevo desde tu perfil.', 'convoca-members' ) );
			return array( 'success' => false, 'message' => 'expired' );
		}

		// Mark verified and clean up.
		update_post_meta( $member_id, self::META_VERIFIED, '1' );
		delete_post_meta( $member_id, self::META_PENDING );
		delete_post_meta( $member_id, self::META_TOKEN );
		delete_post_meta( $member_id, self::META_TOKEN . '_exp' );

		$this->send_telegram_message( $bot_token, $chat_id, __( '✅ ¡Teléfono verificado correctamente!', 'convoca-members' ) );

		\Convoca\Core\Logger::info( "Teléfono verificado por Telegram para el miembro #{$member_id}.", 'Members/PhoneVerify', $member_id );

		return array( 'success' => true, 'member_id' => $member_id );
	}

	/**
	 * Register the Telegram webhook REST route.
	 */
	public function register_webhook(): void {
		register_rest_route(
			self::WEBHOOK_NAMESPACE,
			'/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_callback' ),
				'permission_callback' => '__return_true', // Telegram calls this without auth.
			)
		);
	}

	/**
	 * REST callback for Telegram updates.
	 */
	public function rest_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->handle_callback( $request->get_json_params() ?: array() );
		// Telegram expects 200 always; respond fast.
		return new \WP_REST_Response( array( 'ok' => true, 'result' => $result['success'] ?? false ) );
	}

	/**
	 * Normalize an international phone to digits-only (E.164 digits).
	 */
	private function normalize_phone( string $phone ): string {
		return preg_replace( '/\D+/', '', $phone );
	}

	/**
	 * Send a Telegram message via Bot API.
	 */
	private function send_telegram_message( string $token, int $chat_id, string $text ): bool {
		$url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => array(
					'chat_id' => $chat_id,
					'text'    => $text,
				),
			)
		);
		return ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) === 200;
	}

	/**
	 * Current bot token from settings.
	 */
	private function get_token(): string {
		$settings = get_option( 'convoca_members_settings', array() );
		return (string) ( $settings['telegram_bot_token'] ?? '' );
	}
}
