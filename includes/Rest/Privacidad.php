<?php
/**
 * Convoca Members — privacidad del socio por REST (members#42).
 *
 * Segundo trozo sacado de `Rest_API`. Los tres manejadores solo hablan con `GDPR_Tools` y
 * con la sesión del socio: ver el estado de privacidad, descargar sus datos y pedir la
 * supresión. El contrato REST y los textos no cambian.
 *
 * @package    Convoca\Members
 * @subpackage Rest
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members\Rest;

use Convoca\Members\GDPR_Tools;
use Convoca\Members\Member_Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derechos de privacidad del socio autenticado.
 */
class Privacidad {

	/**
	 * El estado de privacidad del socio para su panel.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function estado( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		return new \WP_REST_Response( GDPR_Tools::estado_privacidad( $member_id ) );
	}

	/**
	 * Los datos del socio, para que se los descargue.
	 *
	 * Se sirve el MISMO exportador que usa Herramientas → Exportar datos personales: así el
	 * socio recibe exactamente lo que la asociación guarda, sin una segunda lista de campos
	 * que se quede atrás.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function datos( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		return new \WP_REST_Response( GDPR_Tools::datos_para_descargar( $member_id ) );
	}

	/**
	 * Registra la solicitud de supresión del socio.
	 *
	 * No borra nada: deja la solicitud anotada en su ficha, avisa a la junta por correo y
	 * responde con el estado nuevo. La supresión la resuelve una persona con el borrador que
	 * ya existe.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function solicitar( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$fecha     = GDPR_Tools::solicitar_supresion( $member_id );

		if ( '' === $fecha ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'mensaje' => __( 'No se ha podido registrar la solicitud. Escríbenos y lo arreglamos.', 'convoca-members' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'fecha'   => $fecha,
				'mensaje' => __( 'Solicitud registrada. La junta la revisará y borrará tus datos personales; recibirás aviso cuando esté.', 'convoca-members' ),
			)
		);
	}
}
