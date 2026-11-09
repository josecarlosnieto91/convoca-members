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
			'dni'       => self::dni_descifrado_de_ficha( $ficha ),
			'direccion' => (string) get_post_meta( $ficha, '_convoca_direccion', true ),
			'municipio' => (string) get_post_meta( $ficha, '_convoca_municipio', true ),
		);
	}

	/**
	 * DNI de una ficha para un gestor, con capability y auditoría.
	 *
	 * Es la única puerta para que seguros, accidentes, PDFs y exportaciones obtengan el DNI. Sin
	 * permiso devuelve cadena vacía. No se llama a `Cifrado::descifrar()` desde fuera de esta clase
	 * para el DNI.
	 *
	 * @param int    $ficha  ID de la ficha.
	 * @param string $motivo Motivo para la auditoría (seguro, accidente, pdf, export...).
	 * @return string
	 */
	public static function dni( int $ficha, string $motivo = 'consulta' ): string {
		if ( $ficha <= 0 ) {
			return '';
		}

		$autorizado = current_user_can( 'gestionar_miembros' ) || ( defined( 'WP_CLI' ) && WP_CLI );

		if ( ! $autorizado ) {
			return '';
		}

		$dni = self::dni_descifrado_de_ficha( $ficha );

		if ( '' === $dni ) {
			return '';
		}

		/**
		 * Se dispara cada vez que un gestor descifra un DNI.
		 *
		 * @param int    $ficha  Ficha.
		 * @param string $motivo Motivo declarado por el consumidor.
		 * @param int    $user_id Usuario que lo pidió.
		 */
		do_action( 'convoca_members_dni_descifrado', $ficha, $motivo, get_current_user_id() );

		\Convoca\Core\Logger::info(
			sprintf( 'DNI descifrado para la ficha #%d. Motivo: %s', $ficha, $motivo ),
			'Members/Audit',
			$ficha
		);

		return $dni;
	}

	/**
	 * DNI del propio socio, o de un gestor que consulta su ficha.
	 *
	 * @param int    $user_id Usuario.
	 * @param string $motivo  Motivo.
	 * @return string
	 */
	public static function dni_de_usuario( int $user_id, string $motivo = 'perfil' ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$autorizado = get_current_user_id() === $user_id
			|| current_user_can( 'gestionar_miembros' )
			|| ( defined( 'WP_CLI' ) && WP_CLI );

		if ( ! $autorizado ) {
			return '';
		}

		$dni = (string) get_user_meta( $user_id, '_convoca_dni', true );

		if ( '' === $dni ) {
			$dni = (string) get_user_meta( $user_id, '_convoca_shifts_dni', true );
		}

		$dni = self::descifrar( $dni );

		if ( '' === $dni ) {
			return '';
		}

		do_action( 'convoca_members_dni_descifrado', 0, $motivo, $user_id );

		return $dni;
	}

	/**
	 * DNI descifrado de una ficha, sin comprobar capability.
	 *
	 * Solo para el puente de cursos (el socio viendo sus propios datos) y para `dni()`. No es una
	 * puerta pública: no se expone fuera de esta clase.
	 *
	 * @param int $ficha Ficha.
	 * @return string
	 */
	private static function dni_descifrado_de_ficha( int $ficha ): string {
		$dni = (string) get_post_meta( $ficha, '_convoca_dni', true );

		if ( '' === $dni ) {
			$user_id = (int) get_post_meta( $ficha, '_convoca_user_id', true );

			if ( $user_id > 0 ) {
				$dni = (string) get_user_meta( $user_id, '_convoca_dni', true );

				if ( '' === $dni ) {
					$dni = (string) get_user_meta( $user_id, '_convoca_shifts_dni', true );
				}
			}
		}

		return self::descifrar( $dni );
	}

	/**
	 * Descifra si hay librería; si no, devuelve el valor tal cual.
	 *
	 * @param string $valor Valor almacenado.
	 * @return string
	 */
	private static function descifrar( string $valor ): string {
		if ( '' === $valor || ! class_exists( '\\Convoca\\Core\\Cifrado' ) ) {
			return $valor;
		}

		return \Convoca\Core\Cifrado::descifrar( $valor );
	}

	/**
	 * Ficha de socio ligada a un usuario.
	 *
	 * El buscador se puede sustituir para probar el respaldo sin depender de `get_posts`: el arnés
	 * del ecosistema define `get_posts` en el bootstrap de core y no se puede reemplazar por prueba.
	 *
	 * @param int           $user_id Usuario de WordPress.
	 * @param callable|null $buscar  Recibe (meta_key, meta_value) y devuelve el ID de la ficha o 0.
	 * @return int ID de la ficha, o 0.
	 */
	public static function ficha_de_usuario( int $user_id, ?callable $buscar = null ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		if ( null === $buscar ) {
			$buscar = static function ( string $meta_key, string $meta_value ): int {
				$fichas = get_posts(
					array(
						'post_type'      => 'miembro',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_key'       => $meta_key,
						'meta_value'     => $meta_value,
					)
				);

				return isset( $fichas[0] ) ? (int) $fichas[0] : 0;
			};
		}

		$vinculada = (int) $buscar( '_convoca_user_id', (string) $user_id );

		if ( $vinculada > 0 ) {
			return $vinculada;
		}

		// Hay sitios donde el vínculo no llegó a escribirse (fichas importadas o anteriores al alta
		// con cuenta). El correo es la clave de la identidad en todo el ecosistema, así que se usa
		// como respaldo: mismo socio, sin duplicar nada y sin escribir en la ficha desde una lectura.
		$usuario = get_userdata( $user_id );

		if ( ! is_object( $usuario ) || empty( $usuario->user_email ) ) {
			return 0;
		}

		return (int) $buscar( '_convoca_email', (string) $usuario->user_email );
	}
}
