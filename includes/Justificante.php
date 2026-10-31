<?php
/**
 * Subida del justificante de pago con enlace firmado de 3 días (members#86).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enlace de un solo uso con caducidad: el socio sube el justificante y la ficha cambia de estado.
 */
class Justificante {

	/**
	 * Shortcode público.
	 */
	public const SHORTCODE = 'convoca_justificante';

	/**
	 * Parámetro del token en la URL.
	 */
	public const PARAM = 'convoca_justificante';

	/**
	 * Acción del formulario.
	 */
	public const ACCION = 'convoca_justificante_subir';

	/**
	 * Vigencia del enlace: 3 días.
	 */
	public const VIDA = 259200;

	/**
	 * Opción con la página que aloja el shortcode.
	 */
	public const OPCION_PAGINA = 'convoca_members_justificante_page';

	/**
	 * Ruta de esa página.
	 */
	public const SLUG = 'subir-justificante';

	/**
	 * Engancha el shortcode y el proceso.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'admin_post_convoca_justificante_subir', array( __CLASS__, 'procesar' ) );
		add_action( 'admin_post_nopriv_convoca_justificante_subir', array( __CLASS__, 'procesar' ) );
	}

	/**
	 * Firma un enlace para un socio.
	 *
	 * @param int $member_id Socio.
	 * @return string Token.
	 */
	public static function token( int $member_id ): string {
		if ( $member_id <= 0 ) {
			return '';
		}

		$expira = time() + self::VIDA;

		return $expira . '.' . hash_hmac( 'sha256', $member_id . '|' . $expira, wp_salt( 'auth' ) );
	}

	/**
	 * Comprueba el token de un socio.
	 *
	 * @param int    $member_id Socio.
	 * @param string $token     Token recibido.
	 * @return bool
	 */
	public static function token_valido( int $member_id, string $token ): bool {
		$partes = explode( '.', $token );

		if ( 2 !== count( $partes ) ) {
			return false;
		}

		$expira = (int) $partes[0];

		if ( $expira < time() || $expira > time() + self::VIDA + 60 ) {
			return false;
		}

		return hash_equals( hash_hmac( 'sha256', $member_id . '|' . $expira, wp_salt( 'auth' ) ), (string) $partes[1] );
	}

	/**
	 * URL firmada para subir el justificante.
	 *
	 * @param int $member_id Socio.
	 * @return string
	 */
	public static function url( int $member_id ): string {
		$pagina = self::pagina();

		if ( $pagina <= 0 ) {
			return '';
		}

		return add_query_arg(
			array(
				self::PARAM => self::token( $member_id ),
				'id'        => $member_id,
			),
			(string) get_permalink( $pagina )
		);
	}

	/**
	 * Página que aloja el formulario.
	 *
	 * @return int
	 */
	public static function pagina(): int {
		$guardada = (int) get_option( self::OPCION_PAGINA, -1 );

		if ( $guardada > 0 && 'page' === get_post_type( $guardada ) ) {
			return $guardada;
		}

		$existente = get_page_by_path( self::SLUG, 'OBJECT', 'page' );

		if ( $existente instanceof \WP_Post ) {
			update_option( self::OPCION_PAGINA, (int) $existente->ID );

			return (int) $existente->ID;
		}

		return 0;
	}

	/**
	 * Crea la página si no existe. Idempotente.
	 *
	 * @return void
	 */
	public static function asegurar_pagina(): void {
		if ( self::pagina() > 0 ) {
			return;
		}

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Subir justificante de pago', 'convoca-members' ),
				'post_name'    => self::SLUG,
				'post_content' => '[' . self::SHORTCODE . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $id > 0 ) {
			update_option( self::OPCION_PAGINA, (int) $id );
		}
	}

	/**
	 * Pinta el formulario o el resultado.
	 *
	 * @return string
	 */
	public static function render(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el token va firmado en la URL.
		$member_id = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;
		$token     = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::PARAM ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $member_id <= 0 || ! self::token_valido( $member_id, $token ) ) {
			return '<div class="convoca-alert convoca-alert--warning">'
				. esc_html__( 'Este enlace no es válido o ha caducado. Pide uno nuevo a la asociación.', 'convoca-members' )
				. '</div>';
		}

		if ( isset( $_GET['subido'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '<div class="convoca-alert convoca-alert--success">'
				. esc_html__( 'Hemos recibido tu justificante. Tu alta queda registrada como pagada.', 'convoca-members' )
				. '</div>';
		}

		$html  = '<form class="convoca-justificante" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACCION ) . '" />';
		$html .= '<input type="hidden" name="member_id" value="' . esc_attr( (string) $member_id ) . '" />';
		$html .= '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />';
		$html .= wp_nonce_field( self::ACCION, '_convoca_nonce', true, false );
		$html .= '<p><label for="convoca-justificante">' . esc_html__( 'Justificante de pago (PDF o imagen)', 'convoca-members' ) . '</label>';
		$html .= '<input type="file" id="convoca-justificante" name="justificante" accept="application/pdf,image/jpeg,image/png" required /></p>';
		$html .= '<p><button type="submit" class="convoca-btn convoca-btn-primary">' . esc_html__( 'Enviar justificante', 'convoca-members' ) . '</button></p>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Recibe el fichero, lo guarda y cambia el estado.
	 *
	 * @return void
	 */
	public static function procesar(): void {
		$nonce = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACCION ) ) {
			wp_die(
				esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-members' ),
				'',
				array( 'response' => 403 )
			);
		}

		$member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( (string) $_POST['member_id'] ) ) : 0;
		$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';

		if ( $member_id <= 0 || ! self::token_valido( $member_id, $token ) ) {
			wp_die(
				esc_html__( 'Este enlace no es válido o ha caducado.', 'convoca-members' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( empty( $_FILES['justificante']['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'fichero', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		// Rutas de la administración: en el arnés de análisis no existen y falsean el informe.
		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/file.php';
		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/media.php';
		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$adjunto = media_handle_upload( 'justificante', 0 );

		if ( is_wp_error( $adjunto ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'subida', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		update_post_meta( $member_id, '_convoca_justificante_id', (int) $adjunto );
		update_post_meta( $member_id, '_convoca_justificante_fecha', current_time( 'mysql' ) );

		// Subir el justificante es declarar el pago: la ficha pasa a activa.
		$cambio = Estados::change( $member_id, 'activo', 'Justificante de pago subido por el socio' );

		if ( is_wp_error( $cambio ) ) {
			// El fichero está guardado: no se pierde trabajo, pero queda para revisión manual.
			update_post_meta( $member_id, '_convoca_justificante_revision', $cambio->get_error_message() );
		}

		$destino = remove_query_arg( array( self::PARAM, 'id' ) );
		$destino = add_query_arg( 'subido', '1', $destino );

		wp_safe_redirect( $destino );
		exit;
	}
}
