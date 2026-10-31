<?php
/**
 * Datos de la ficha del socio para no pedirlos otra vez al matricularse en un curso.
 *
 * `courses` no conoce las fichas de `members`: lanza el filtro `convoca_datos_socio` y, si nadie
 * contesta, el formulario de matrícula pide correo y nombre como siempre. Solo se contestan los
 * datos de una ficha **activa** y ligada a un usuario de WordPress.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puente de lectura hacia `courses`.
 */
class Datos_Socio {

	/**
	 * Filtro que consume `courses`.
	 */
	public const FILTER = 'convoca_datos_socio';

	/**
	 * Engancha el filtro.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( self::FILTER, array( __CLASS__, 'datos' ), 10, 2 );
	}

	/**
	 * Devuelve los datos de la ficha del usuario.
	 *
	 * @param mixed $datos   Lo que traiga otro (vacío por defecto).
	 * @param int   $user_id Usuario de WordPress.
	 * @return array{email:string,nombre:string,dni:string,direccion:string,municipio:string}|array{}
	 */
	public static function datos( $datos, $user_id ): array {
		if ( is_array( $datos ) && array() !== $datos ) {
			return $datos;
		}

		$user_id = (int) $user_id;
		$ficha   = self::ficha_de_usuario( $user_id );

		if ( $ficha <= 0 ) {
			return array();
		}

		return self::datos_de_ficha( $ficha );
	}

	/**
	 * Datos de una ficha concreta, si está activa.
	 *
	 * Va aparte para poder probarlo sin depender de la consulta que busca la ficha por usuario.
	 *
	 * @param int $ficha ID de la ficha.
	 * @return array{email:string,nombre:string,dni:string,direccion:string,municipio:string}|array{}
	 */
	public static function datos_de_ficha( int $ficha ): array {
		if ( $ficha <= 0 ) {
			return array();
		}

		// Solo un socio activo: una ficha de baja o pendiente de pago no debe matricular a nadie
		// sin pasar por el formulario.
		if ( 'activo' !== (string) get_post_meta( $ficha, '_convoca_estado_miembro', true ) ) {
			return array();
		}

		$email = sanitize_email( (string) get_post_meta( $ficha, '_convoca_email', true ) );

		if ( '' === $email ) {
			return array();
		}

		$nombre = (string) get_post_meta( $ficha, '_convoca_nombre', true );

		if ( '' === $nombre ) {
			$nombre = (string) get_the_title( $ficha );
		}

		return array(
			'email'     => $email,
			'nombre'    => $nombre,
			'dni'       => (string) get_post_meta( $ficha, '_convoca_dni', true ),
			'direccion' => (string) get_post_meta( $ficha, '_convoca_direccion', true ),
			'municipio' => (string) get_post_meta( $ficha, '_convoca_municipio', true ),
		);
	}

	/**
	 * Ficha de socio ligada a un usuario.
	 *
	 * @param int $user_id Usuario de WordPress.
	 * @return int ID de la ficha, o 0.
	 */
	public static function ficha_de_usuario( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		$fichas = get_posts(
			array(
				'post_type'      => 'miembro',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_convoca_user_id',
				'meta_value'     => (string) $user_id,
			)
		);

		if ( isset( $fichas[0] ) ) {
			return (int) $fichas[0];
		}

		// Hay sitios donde el vínculo no llegó a escribirse (fichas importadas o anteriores al alta
		// con cuenta). El correo es la clave de la identidad en todo el ecosistema, así que se usa
		// como respaldo: mismo socio, sin duplicar nada y sin escribir en la ficha desde una lectura.
		$usuario = get_userdata( $user_id );

		if ( ! is_object( $usuario ) || empty( $usuario->user_email ) ) {
			return 0;
		}

		$por_correo = get_posts(
			array(
				'post_type'      => 'miembro',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_convoca_email',
				'meta_value'     => (string) $usuario->user_email,
			)
		);

		return isset( $por_correo[0] ) ? (int) $por_correo[0] : 0;
	}
}
