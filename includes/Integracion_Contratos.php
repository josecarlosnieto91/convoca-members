<?php
/**
 * Convoca Members — Contratos con el resto del ecosistema.
 *
 * @package    Convoca\Members
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lo que Members aporta a los demás plugins: **las personas**.
 *
 * El vocabulario (roles y capacidades) es del producto y lo mantiene Convoca Core. Lo que sólo Members
 * puede saber es quién es voluntario aprobado, quién es monitor y quién puede acreditar horas: eso son sus
 * fichas y sus aprobaciones. Aquí se contesta, en lugar de que cada plugin averigüe esas cosas leyendo
 * roles por su cuenta (que es lo que hacía Shifts, y por eso en un sitio sin gestión de personas aparecían
 * personas asignables de la nada).
 *
 * @since 2.9.15
 */
class Integracion_Contratos {

	/**
	 * Engancha los dos contratos.
	 */
	public static function init(): void {
		add_filter( 'convoca_can_credit_hours', array( __CLASS__, 'filtrar_puede_acreditar_horas' ), 10, 2 );
		add_filter( 'convoca_shifts_personas_asignables', array( __CLASS__, 'filtrar_personas_asignables' ), 10, 2 );
		add_filter( 'convoca_member_identity', array( __CLASS__, 'filtrar_identidad' ) );
		add_filter( 'convoca_member_priority', array( __CLASS__, 'filtrar_prioridad_de_socio' ), 10, 2 );
		add_filter( 'convoca_es_socio_activo', array( __CLASS__, 'filtrar_es_socio_activo' ), 10, 2 );
	}

	/**
	 * ¿Esta persona es socio con la cuota al día?
	 *
	 * Lo pregunta Convoca Courses para aplicar el precio de socio. La respuesta no es «tiene el rol de
	 * socio» sino «tiene ficha de socio **y** su estado es activo»: un socio de baja conserva el rol y
	 * no debe seguir pagando precio de socio, que es justo el defecto que este contrato evita.
	 *
	 * @since 2.9.18
	 *
	 * @param bool $es_socio Respuesta por defecto (falso: sin contrato nadie es socio).
	 * @param int  $user_id  Usuario de WordPress.
	 * @return bool
	 */
	public static function filtrar_es_socio_activo( $es_socio, $user_id ): bool {
		if ( $es_socio ) {
			return true;
		}

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		$ficha = Roles_Miembro::ficha_de_usuario( $user_id );
		if ( $ficha <= 0 || ! Roles_Miembro::es_socio( $ficha ) ) {
			return false;
		}

		$estado = (string) get_post_meta( $ficha, '_convoca_estado_miembro', true );

		// Sin estado guardado se considera activo: son fichas anteriores al circuito de estados, y
		// cobrarles el precio general por un metadato que no existía sería un error caro.
		return '' === $estado || 'activo' === $estado;
	}

	/**
	 * Quién es el socio con la sesión abierta.
	 *
	 * Hasta ahora cada plugin que lo necesitaba llamaba a `Member_Auth` con un `class_exists` delante y su
	 * propio código de reserva detrás. Ahora lo pregunta el núcleo y contesta Members, que es quien tiene la
	 * sesión.
	 *
	 * @param array $identidad Respuesta por defecto (nadie).
	 * @return array{autenticado: bool, member_id: int, datos: array}
	 */
	public static function filtrar_identidad( $identidad ): array {
		if ( ! Member_Auth::is_authenticated() ) {
			// Se devuelve lo que venga: si no hay sesión, el valor por defecto del núcleo ya es «nadie».
			return $identidad;
		}

		return array(
			'autenticado' => true,
			'member_id'   => Member_Auth::get_current_member_id(),
			'datos'       => (array) Member_Auth::get_current_member_data(),
		);
	}

	/**
	 * ¿Esta inscripción tiene prioridad por ser de un socio al día?
	 *
	 * Es el código que vivía dentro de Convoca Enroll, con su `class_exists` delante: se resuelve la ficha
	 * —por el vínculo guardado en la inscripción o, si falta, por el correo— y se mira si la cuota está
	 * activa. Un socio sin ficha vinculada no tiene prioridad: no hay cuota que verificar.
	 *
	 * @param bool $prioridad      Prioridad por defecto (sí).
	 * @param int  $inscripcion_id Inscripción.
	 * @return bool
	 */
	public static function filtrar_prioridad_de_socio( $prioridad, $inscripcion_id ): bool {
		$inscripcion_id = (int) $inscripcion_id;
		if ( $inscripcion_id <= 0 ) {
			return (bool) $prioridad;
		}

		// La inscripción se marcó como de socio: es el primer filtro y es de Enroll, no se discute aquí.
		if ( '1' !== (string) get_post_meta( $inscripcion_id, '_convoca_es_socio', true ) ) {
			return false;
		}

		$member_id = (int) get_post_meta( $inscripcion_id, '_convoca_member_id', true );

		if ( ! $member_id ) {
			$email = (string) get_post_meta( $inscripcion_id, '_convoca_email', true );

			if ( '' !== $email ) {
				$fichas = get_posts(
					array(
						'post_type'      => 'miembro',
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'fields'         => 'ids',
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => '_convoca_email',
								'value' => $email,
							),
						),
					)
				);

				if ( ! empty( $fichas ) ) {
					$member_id = (int) $fichas[0];
				}
			}
		}

		if ( $member_id ) {
			return 'activa' === get_post_meta( $member_id, '_convoca_estado_cuota', true );
		}

		return false;
	}

	/**
	 * ¿Puede esta persona acreditar horas? La regla es la de siempre, la de Members.
	 *
	 * @param bool $puede   Lo que diga el núcleo (por defecto, no).
	 * @param int  $user_id Usuario de WordPress.
	 * @return bool
	 */
	public static function filtrar_puede_acreditar_horas( $puede, $user_id ): bool {
		return Admin_Voluntariado::puede_acreditar_horas( (int) $user_id );
	}

	/**
	 * Personas que se pueden asignar a un turno, por contexto.
	 *
	 * @param array  $personas Lo que haya aportado otro (por defecto, nada).
	 * @param string $contexto 'voluntario' o 'monitor'.
	 * @return array<int, array{id:int, nombre:string}>
	 */
	public static function filtrar_personas_asignables( $personas, $contexto ): array {
		$salida = array();

		if ( 'monitor' === $contexto ) {
			// Monitores: quien tiene el rol. Es de Members decidirlo, no de quien pregunta.
			$usuarios = get_users(
				array(
					'role'    => 'monitor_actividad',
					'orderby' => 'display_name',
				)
			);
		} else {
			// Voluntariado aprobado: el rol, más quien esté aprobado por el meta de aprobación aunque el
			// rol no se le haya puesto todavía (pasa en instalaciones que vienen de antes).
			$usuarios = get_users(
				array(
					'role'    => 'voluntario_aprobado',
					'orderby' => 'display_name',
				)
			);

			$aprobados = get_users(
				array(
					'meta_key'     => Admin_Voluntariado::META_APROBADO, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'orderby'      => 'display_name',
				)
			);

			$usuarios = array_merge( (array) $usuarios, (array) $aprobados );
		}

		// Los administradores del sitio también se pueden asignar, y no es un capricho: los selectores de
		// Shifts usaban `role__in => administrator|voluntario_aprobado`, así que hasta hoy el
		// administrador aparecía en el desplegable. Medido en el sitio de producción: 5 usuarios, 0 con rol
		// de voluntario o monitor → sin esta línea el desplegable se queda vacío y se rompe el trabajo
		// diario en una instalación que hasta ahora funcionaba. Se suman en los dos contextos.
		$usuarios = array_merge(
			(array) $usuarios,
			(array) get_users(
				array(
					'role'    => 'administrator',
					'orderby' => 'display_name',
				)
			)
		);

		foreach ( (array) $usuarios as $usuario ) {
			if ( ! is_object( $usuario ) || empty( $usuario->ID ) ) {
				continue;
			}

			$id = (int) $usuario->ID;

			// Sin duplicados: quien tiene rol y meta a la vez entra una sola vez.
			$salida[ $id ] = array(
				'id'     => $id,
				'nombre' => (string) $usuario->display_name,
			);
		}

		return array_values( $salida );
	}
}
