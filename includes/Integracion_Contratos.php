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
