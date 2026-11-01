<?php
/**
 * Convoca Members — carné y certificado del socio por REST.
 *
 * Tercer trozo sacado de `Rest_API`. Tres lecturas del área de socio: el carné en HTML, el
 * estado del certificado (horas aprobadas frente al objetivo) y su descarga en PDF. El
 * contrato REST y los textos no cambian.
 *
 * @package    Convoca\Members
 * @subpackage Rest
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members\Rest;

use Convoca\Members\Certificate_Generator;
use Convoca\Members\Member_Auth;
use Convoca\Members\PDF_Card;
use Convoca\Members\Voluntariado_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carné y certificado del socio autenticado.
 */
class Certificados {

	/**
	 * El carné del socio en HTML.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function carne( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$theme     = sanitize_key( (string) $request->get_param( 'theme' ) );
		$html      = PDF_Card::get_html( $member_id, $theme );
		return new \WP_REST_Response( array( 'html' => $html ) );
	}

	/**
	 * Estado del certificado: si procede y con qué horas.
	 *
	 * Recalcula las horas aprobadas de verdad, no se fía del metadato.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function info( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$post      = get_post( $member_id );

		if ( ! $post || $post->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		$horas_aprobadas = Voluntariado_Manager::get_horas_aprobadas( $member_id );
		$objetivo        = Voluntariado_Manager::get_horas_objetivo( $member_id );
		$completado      = $horas_aprobadas >= $objetivo;

		if ( ! $completado ) {
			return new \WP_REST_Response(
				array(
					'success'  => false,
					'message'  => __( 'Aún no has completado las horas mínimas requeridas para generar el certificado.', 'convoca-members' ),
					'progress' => array(
						'aprobadas' => $horas_aprobadas,
						'objetivo'  => $objetivo,
					),
				),
				400
			);
		}

		$cert_id = get_post_meta( $member_id, '_convoca_certificado_id', true );

		return new \WP_REST_Response(
			array(
				'success'        => true,
				'certificado_id' => $cert_id,
				'horas'          => $horas_aprobadas,
				'objetivo'       => $objetivo,
				'download_url'   => rest_url( 'convoca-members/v1/me/certificate/download' ),
			)
		);
	}

	/**
	 * Descarga el certificado en PDF.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function descargar( \WP_REST_Request $request ): void {
		$member_id = Member_Auth::get_current_member_id();
		$post      = get_post( $member_id );

		if ( ! $member_id || ! $post || $post->post_type !== 'miembro' ) {
			wp_send_json_error( __( 'Miembro no encontrado o no autorizado', 'convoca-members' ), 404 );
		}

		Certificate_Generator::serve_pdf( $member_id );
	}
}
